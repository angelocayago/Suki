<?php

namespace App\Support;

use App\Models\Seller;
use App\Models\SellerConversation;
use App\Models\SellerMessage;
use App\Models\SellerVoucher;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SellerNotifications
{
    public const VOUCHER_CREATED = 'seller.voucher.created';

    public const BUYER_MESSAGE_CREATED = 'seller.message.received';

    public static function buyerMessageCreated(SellerMessage $message): void
    {
        // Called only after a new authorized message and its images are saved.
        // A transaction is mandatory: notification failure must roll back the send.
        if (! $message->exists || ! $message->wasRecentlyCreated || $message->sender_role !== 'buyer'
            || $message->getConnection()->transactionLevel() === 0) {
            return;
        }
        if (! $message->newQuery()->whereKey($message->id)->where('conversation_id', $message->conversation_id)
            ->where('sender_role', 'buyer')->where('sender_user_id', $message->sender_user_id)
            ->where('submission_key', $message->submission_key)->where('body', $message->body)->exists()) {
            return;
        }
        $conversation = SellerConversation::on($message->getConnectionName())->whereKey($message->conversation_id)
            ->where('buyer_id', $message->sender_user_id)->first();
        if (! $conversation) {
            return;
        }
        $connection = $message->getConnectionName() ?? config('database.default');
        abort_unless(Schema::connection($connection)->hasTable('notifications'), 503, 'Notification storage is unavailable. Your message was not sent; please retry later.');
        $notification = (new DatabaseNotification)->setConnection($connection);
        $notification->newQuery()->firstOrCreate(['id' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'suki:seller:'.$conversation->seller_id.':buyer-message:'.$message->id)->toString()], [
            'notifiable_type' => (new Seller)->getMorphClass(), 'notifiable_id' => $conversation->seller_id,
            'type' => self::BUYER_MESSAGE_CREATED, 'data' => ['title' => 'New Buyer Message',
                'message' => 'You have received a new message from a Buyer.',
                'destination' => ['type' => 'conversation', 'id' => $conversation->id]],
        ]);
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            self::VOUCHER_CREATED => 'Vouchers', self::BUYER_MESSAGE_CREATED => 'Messages', default => 'System',
        };
    }

    public static function voucherCreated(SellerVoucher $voucher): void
    {
        // Only future actual inserts produce notifications. No history scan or scheduler.
        if (! $voucher->exists || ! $voucher->wasRecentlyCreated || ! Schema::hasTable('notifications')) {
            return;
        }
        if (! $voucher->newQuery()->whereKey($voucher->id)->where('seller_id', $voucher->seller_id)->exists()) {
            return;
        }
        DatabaseNotification::firstOrCreate(['id' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'suki:seller:'.$voucher->seller_id.':voucher-created:'.$voucher->id)->toString()], [
            'notifiable_type' => (new Seller)->getMorphClass(), 'notifiable_id' => $voucher->seller_id,
            'type' => self::VOUCHER_CREATED, 'data' => ['title' => 'Voucher created',
                'message' => 'A voucher configuration was saved for your shop. Buyer redemption is not connected.',
                'destination' => ['type' => 'voucher', 'id' => $voucher->id]],
        ]);
    }

    public static function query(Seller $seller)
    {
        return $seller->notifications();
    }

    public static function requireStorage(): void
    {
        abort_unless(Schema::hasTable('notifications'), 503, 'Notifications are unavailable until the notification migration is applied.');
    }

    public static function present(Collection $notifications, Seller $seller): Collection
    {
        // Resolve the whole visible cohort with one scoped lookup, never per row.
        $idsFor = fn ($kind) => $notifications->map(function ($n) use ($kind) {
            $destination = is_array($n->data) ? ($n->data['destination'] ?? null) : null;

            return is_array($destination) && ($destination['type'] ?? null) === $kind ? ($destination['id'] ?? null) : null;
        })->filter(fn ($id) => is_int($id) && $id > 0);
        $ownedVouchers = SellerVoucher::where('seller_id', $seller->id)->whereIn('id', $idsFor('voucher'))->pluck('id')->all();
        $conversationIds = $idsFor('conversation');
        $ownedConversations = $conversationIds->isNotEmpty() && Schema::hasTable('seller_conversations')
            ? SellerConversation::where('seller_id', $seller->id)->whereIn('id', $conversationIds)->pluck('id')->all() : [];

        return $notifications->map(function ($n) use ($ownedVouchers, $ownedConversations) {
            $data = is_array($n->data) ? $n->data : [];
            $destination = is_array($data['destination'] ?? null) ? $data['destination'] : [];
            $id = $destination['id'] ?? null;
            $url = null;
            $linkLabel = null;
            if (is_int($id) && ($destination['type'] ?? null) === 'voucher' && in_array($id, $ownedVouchers, true)) {
                $url = route('seller.vouchers.show', $id);
                $linkLabel = 'View voucher';
            }
            if (is_int($id) && ($destination['type'] ?? null) === 'conversation' && in_array($id, $ownedConversations, true)) {
                $url = route('seller.messages.show', $id);
                $linkLabel = 'View Conversation';
            }

            return (object) ['id' => $n->id, 'title' => is_string($data['title'] ?? null) ? $data['title'] : 'Notification',
                'message' => is_string($data['message'] ?? null) ? $data['message'] : '',
                'type' => self::typeLabel($n->type), 'url' => $url, 'link_label' => $linkLabel,
                'created_at' => $n->created_at, 'read_at' => $n->read_at];
        });
    }

    public static function shell(): array
    {
        $empty = ['available' => false, 'unread' => null, 'recent' => collect()];
        if (! Auth::check() || ! Auth::user()->isSeller()) {
            return $empty;
        }
        try {
            $seller = SellerAccount::current();
        } catch (HttpException $exception) {
            return $empty;
        }
        if (! Schema::hasTable('notifications')) {
            return $empty;
        }

        return ['available' => true, 'unread' => self::query($seller)->whereNull('read_at')->count(),
            'recent' => self::present(self::query($seller)->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get(), $seller)];
    }

    public static function page(Seller $seller, Request $request): array
    {
        self::requireStorage();
        $types = self::query($seller)->reorder()->select('type')->distinct()->pluck('type')->mapWithKeys(fn ($type) => [$type => self::typeLabel($type)]);
        $criteria = $request->validate(['read_status' => 'nullable|in:read,unread', 'type' => ['nullable', 'string', Rule::in($types->keys()->all())],
            'sort' => 'nullable|in:newest,oldest', 'page' => 'nullable|integer|min:1']);
        $query = self::query($seller);
        if (($criteria['read_status'] ?? '') === 'unread') {
            $query->whereNull('read_at');
        }
        if (($criteria['read_status'] ?? '') === 'read') {
            $query->whereNotNull('read_at');
        }
        if (! empty($criteria['type'])) {
            $query->where('type', $criteria['type']);
        }
        SellerDateFilter::apply($query, $request, 'created_at');
        $criteria += $request->only(['date_range', 'from', 'to']);
        $direction = ($criteria['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';
        $paginator = $query->reorder()->orderBy('created_at', $direction)->orderBy('id', $direction)->paginate(20)->appends(array_diff_key($criteria, ['page' => true]));
        $notifications = self::present($paginator->getCollection(), $seller);
        $filterChips = SellerFilterChips::make($criteria, ['read_status' => ['read' => 'Read', 'unread' => 'Unread'], 'type' => $types->all(), 'sort' => ['newest' => 'Newest First', 'oldest' => 'Oldest First']],
            route('seller.notifications.index'), ['read_status', 'type', 'sort', 'date_range', 'from', 'to'], 'newest');

        return compact('notifications', 'paginator', 'criteria', 'types', 'filterChips');
    }

    public static function read(Seller $seller, string $id, bool $read): void
    {
        self::requireStorage();
        $notification = self::query($seller)->findOrFail($id);
        self::query($seller)->whereKey($notification->id)->when($read, fn ($q) => $q->whereNull('read_at'), fn ($q) => $q->whereNotNull('read_at'))
            ->update(['read_at' => $read ? now() : null, 'updated_at' => now()]);
    }
}
