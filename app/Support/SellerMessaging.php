<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerConversation;
use App\Models\SellerMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SellerMessaging
{
    public static function available(): bool
    {
        return Schema::hasTable('seller_conversations') && Schema::hasTable('seller_messages');
    }

    public static function requireStorage(): void
    {
        abort_unless(self::available(), 503, 'Messages are unavailable until the messaging migration is applied.');
    }

    public static function eligibleBuyer(?User $buyer): bool
    {
        return $buyer && $buyer->isBuyer() && BuyerAccess::approved($buyer) && ! $buyer->is_suspended && BuyerAccess::verified($buyer);
    }

    public static function buyer(): User
    {
        $buyer = Auth::user();
        abort_unless(self::eligibleBuyer($buyer), 403, 'An active, verified Buyer account is required.');

        return $buyer;
    }

    // Future internal Buyer contract only. No Buyer route calls this in S-MSG-01.
    public static function startForBuyer(Seller $shop): SellerConversation
    {
        $buyer = self::buyer();
        self::requireStorage();

        return DB::transaction(function () use ($shop, $buyer) {
            $shop = Seller::lockForUpdate()->findOrFail($shop->id);
            $people = User::whereIn('id', [$shop->user_id, $buyer->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $owner = $people->get($shop->user_id);
            abort_unless(SellerMessagingPolicy::eligible($shop, $owner)
                && self::eligibleBuyer($people->get($buyer->id)) && $buyer->id !== $owner->id, 403, 'These participants cannot start a conversation.');

            return SellerConversation::firstOrCreate(['buyer_id' => $buyer->id, 'seller_id' => $shop->id]);
        }, 3);
    }

    public static function owned(Seller $shop, string $id): SellerConversation
    {
        self::requireStorage();
        abort_unless((string) (int) $id === $id && (int) $id > 0, 404);

        return SellerConversation::where('seller_id', $shop->id)->findOrFail($id);
    }

    public static function sendForSeller(Seller $shop, string $conversation, string $body, string $key, array $images = []): SellerMessage
    {
        // Resolve again so a caller cannot substitute another eligible shop.
        $current = SellerAccount::current();
        abort_unless($current->id === $shop->id, 403);
        $conversation = self::owned($current, $conversation);

        return self::send($conversation, 'seller', Auth::id(), $body, $key, $images);
    }

    // Future internal Buyer contract; not an HTTP initiation/send feature.
    public static function sendForBuyer(string $conversation, string $body, string $key, array $images = []): SellerMessage
    {
        $buyer = self::buyer();
        self::requireStorage();
        abort_unless((string) (int) $conversation === $conversation && (int) $conversation > 0, 404);
        $conversation = SellerConversation::where('buyer_id', $buyer->id)->findOrFail($conversation);

        return self::send($conversation, 'buyer', $buyer->id, $body, $key, $images);
    }

    private static function send(SellerConversation $conversation, string $role, int $author, string $body, string $key, array $files = []): SellerMessage
    {
        $body = preg_replace('/^\s+|\s+$/u', '', $body) ?? '';
        Validator::make(['body' => $body, 'submission_key' => $key], ['body' => 'string|max:5000', 'submission_key' => 'required|uuid'])->validate();
        $images = ChatImages::prepare($files);
        if ($body === '' && ! $images) {
            throw ValidationException::withMessages(['body' => 'Enter a message or attach an image.']);
        }

        return DB::transaction(function () use ($conversation, $role, $author, $body, $key, $images) {
            $shop = Seller::lockForUpdate()->findOrFail($conversation->seller_id);
            $people = User::whereIn('id', [$shop->user_id, $conversation->buyer_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $owner = $people->get($shop->user_id);
            $buyer = $people->get($conversation->buyer_id);
            abort_unless(SellerMessagingPolicy::eligible($shop, $owner) && self::eligibleBuyer($buyer)
                && $buyer->id !== $owner->id, 403, 'Conversation participants are unavailable.');
            abort_unless($author === ($role === 'buyer' ? $buyer->id : $owner->id), 403);
            $locked = SellerConversation::lockForUpdate()->findOrFail($conversation->id);
            abort_unless($locked->seller_id === $shop->id && $locked->buyer_id === $buyer->id, 409);
            $message = SellerMessage::firstOrCreate(['conversation_id' => $locked->id, 'sender_role' => $role, 'submission_key' => strtolower($key)],
                ['sender_user_id' => $author, 'body' => $body]);
            abort_unless($message->body === $body && $message->sender_user_id === $author, 409, 'Submission key already used for different content.');
            if ($message->wasRecentlyCreated) {
                ChatImages::store($message, $images);
            } else {
                $saved = Schema::hasTable('seller_message_attachments') ? $message->attachments()->pluck('source_sha256')->all() : [];
                abort_unless($saved === array_column($images, 'source_sha256'), 409, 'Submission key already used for different images.');
            }
            if ($message->wasRecentlyCreated && (! $locked->last_message_at || $message->created_at->greaterThanOrEqualTo($locked->last_message_at))) {
                $locked->update(['last_message_at' => $message->created_at]);
            }
            if ($message->wasRecentlyCreated && $role === 'buyer') {
                SellerNotifications::buyerMessageCreated($message);
            }

            return $message;
        }, 3);
    }

    public static function unread(Seller $shop): int
    {
        return SellerMessage::join('seller_conversations as c', 'c.id', '=', 'seller_messages.conversation_id')->where('c.seller_id', $shop->id)
            ->where('sender_role', 'buyer')->whereNull('read_at')->count();
    }

    public static function shell(): array
    {
        if (! Auth::check() || ! Auth::user()->isSeller()) {
            return ['available' => false, 'unread' => null];
        }
        try {
            $shop = SellerAccount::current();
        } catch (HttpException $exception) {
            return ['available' => false, 'unread' => null];
        }

        return self::available() ? ['available' => true, 'unread' => self::unread($shop)] : ['available' => false, 'unread' => null];
    }

    public static function page(Seller $shop, Request $request): array
    {
        self::requireStorage();
        $criteria = $request->validate(['search' => 'nullable|string|max:100', 'read_status' => 'nullable|in:read,unread', 'sort' => 'nullable|in:recent,oldest', 'page' => 'nullable|integer|min:1']);
        $query = SellerConversation::query()->leftJoin('users as buyer', 'buyer.id', '=', 'seller_conversations.buyer_id')
            ->where('seller_conversations.seller_id', $shop->id)->select('seller_conversations.*', 'buyer.name as buyer_name')
            ->selectSub(SellerMessage::select('body')->whereColumn('conversation_id', 'seller_conversations.id')->orderByDesc('created_at')->orderByDesc('id')->limit(1), 'last_body')
            ->selectSub(SellerMessage::selectRaw('COUNT(*)')->whereColumn('conversation_id', 'seller_conversations.id')->where('sender_role', 'buyer')->whereNull('read_at'), 'unread_count');
        if (($criteria['search'] ?? '') !== '') {
            $query->where('buyer.name', 'like', '%'.$criteria['search'].'%');
        }
        if (! empty($criteria['read_status'])) {
            $method = $criteria['read_status'] === 'unread' ? 'whereExists' : 'whereNotExists';
            $query->$method(fn ($q) => $q->selectRaw('1')->from('seller_messages')->whereColumn('conversation_id', 'seller_conversations.id')->where('sender_role', 'buyer')->whereNull('read_at'));
        }
        $direction = ($criteria['sort'] ?? 'recent') === 'oldest' ? 'asc' : 'desc';
        $paginator = $query->orderByRaw('COALESCE(last_message_at, seller_conversations.created_at) '.$direction)->orderBy('seller_conversations.id', $direction)->paginate(20, ['*'], 'page', (int) ($criteria['page'] ?? 1))
            ->appends(array_diff_key($criteria, ['page' => true]));
        $filterChips = SellerFilterChips::make($criteria, ['read_status' => ['read' => 'No unread incoming messages', 'unread' => 'Unread incoming messages'], 'sort' => ['recent' => 'Most Recent', 'oldest' => 'Oldest']],
            route('seller.messages.index'), ['search', 'read_status', 'sort'], 'recent');

        return compact('criteria', 'paginator', 'filterChips');
    }

    public static function markDisplayedRead(Seller $shop, string $id, array $ids): void
    {
        $conversation = self::owned($shop, $id);
        SellerMessage::where('conversation_id', $conversation->id)->whereIn('id', $ids)->where('sender_role', 'buyer')->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
    }

    public static function details(Seller $shop, string $id): array
    {
        $conversation = self::owned($shop, $id);
        $buyer = User::select('id', 'name', 'role', 'status', 'is_suspended', 'email_verified_at')->find($conversation->buyer_id);
        $buyerName = trim($buyer?->name ?? '') ?: 'Buyer';
        $canReply = self::eligibleBuyer($buyer) && SellerMessagingPolicy::eligible($shop);
        $paginator = SellerMessage::where('conversation_id', $conversation->id)->orderByDesc('created_at')->orderByDesc('id')->paginate(50);
        $messages = $paginator->getCollection()->reverse()->values();
        if (Schema::hasTable('seller_message_attachments')) {
            $messages->load('attachments');
        }
        self::markDisplayedRead($shop, $id, $messages->modelKeys());

        return compact('conversation', 'buyerName', 'canReply', 'paginator', 'messages');
    }

    public static function ownedByBuyer(string $id): SellerConversation
    {
        $buyer = self::buyer();
        self::requireStorage();
        abort_unless((string) (int) $id === $id && (int) $id > 0, 404);

        return SellerConversation::where('buyer_id', $buyer->id)->findOrFail($id);
    }

    public static function buyerUnread(): int
    {
        $buyer = self::buyer();
        self::requireStorage();

        return SellerMessage::join('seller_conversations as c', 'c.id', '=', 'seller_messages.conversation_id')->where('c.buyer_id', $buyer->id)->where('sender_role', 'seller')->whereNull('read_at')->count();
    }

    public static function markBuyerDisplayedRead(string $id, array $ids): void
    {
        $conversation = self::ownedByBuyer($id);
        SellerMessage::where('conversation_id', $conversation->id)->whereIn('id', $ids)->where('sender_role', 'seller')->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
    }

    public static function buyerDetails(string $id): array
    {
        $conversation = self::ownedByBuyer($id);
        $shop = Seller::select('id', 'name', 'status', 'user_id')->findOrFail($conversation->seller_id);
        $canSend = SellerMessagingPolicy::eligible($shop) && $shop->user_id !== Auth::id();
        $paginator = SellerMessage::where('conversation_id', $conversation->id)->orderByDesc('created_at')->orderByDesc('id')->paginate(50);
        $messages = $paginator->getCollection()->reverse()->values();
        if (Schema::hasTable('seller_message_attachments')) {
            $messages->load('attachments');
        }
        self::markBuyerDisplayedRead($id, $messages->modelKeys());

        return compact('conversation', 'shop', 'paginator', 'messages', 'canSend');
    }

    public static function publicShops()
    {
        return SellerMessagingPolicy::shops()->where('user_id', '!=', self::buyer()->id);
    }

    public static function contactShop(string $id): Seller
    {
        $shop = self::publicShops()->findOrFail($id);
        abort_unless($shop->owner->isSeller(), 404);

        return $shop;
    }

    public static function contactProduct(string $id): Product
    {
        self::buyer();
        $product = Product::where('is_active', true)->whereHas('variants', fn ($q) => $q->where('is_active', true))->findOrFail($id);
        self::contactShop((string) $product->seller_id);

        return $product;
    }

    public static function buyerPage(Request $request): array
    {
        $buyer = self::buyer();
        self::requireStorage();
        $criteria = $request->validate(['search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $query = SellerConversation::join('sellers as s', 's.id', '=', 'seller_conversations.seller_id')->where('buyer_id', $buyer->id)
            ->select('seller_conversations.*', 's.name as shop_name')
            ->selectSub(SellerMessage::select('body')->whereColumn('conversation_id', 'seller_conversations.id')->orderByDesc('created_at')->orderByDesc('id')->limit(1), 'last_body')
            ->selectSub(SellerMessage::selectRaw('COUNT(*)')->whereColumn('conversation_id', 'seller_conversations.id')->where('sender_role', 'seller')->whereNull('read_at'), 'unread_count');
        if (($criteria['search'] ?? '') !== '') {
            $query->where('s.name', 'like', '%'.$criteria['search'].'%');
        }
        $paginator = $query->orderByRaw('COALESCE(last_message_at, seller_conversations.created_at) DESC')->orderByDesc('seller_conversations.id')->paginate(20, ['*'], 'page', (int) ($criteria['page'] ?? 1))->appends($criteria);
        $unread = self::buyerUnread();
        $products = Product::where('is_active', true)->whereIn('seller_id', self::publicShops()->select('id'))->whereHas('variants', fn ($q) => $q->where('is_active', true))->with('seller:id,status')->orderBy('name')->limit(20)->get(['id', 'name', 'seller_id']);
        $shops = self::publicShops()->orderBy('name')->limit(20)->get(['id', 'name', 'status']);

        return compact('paginator', 'unread', 'criteria', 'products', 'shops');
    }
}
