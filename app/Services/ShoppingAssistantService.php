<?php

namespace App\Services;

use App\Models\{Order, ShoppingAssistantConversation, ShoppingAssistantMessage, User};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Grounded shopping support. Optional AI may only rewrite this safe response. */
class ShoppingAssistantService
{
    public function reply(Request $request, string $message): array
    {
        $conversation = $this->conversation($request);
        $message = trim($message);
        $conversation->messages()->create(['role' => 'user', 'body' => $message]);
        // Bound retained history so a guest/session cannot turn this into a data store.
        $conversation->messages()->orderByDesc('id')->skip(20)->take(PHP_INT_MAX)->delete();
        $answer = $this->deterministic($request->user(), $message);
        $source = 'deterministic';
        if (config('services.shopping_assistant.enabled') && config('services.shopping_assistant.provider') && config('services.shopping_assistant.key')) {
            $rewritten = app(ShoppingAssistantAiAdapter::class)->rewrite($answer['text'], $this->safeContext($answer));
            // The deterministic answer remains the authority.  The adapter may
            // only return a verified equivalent; otherwise it is a fallback.
            if ($rewritten !== null) { $answer['text'] = $rewritten; $source = 'ai'; }
        }
        $conversation->messages()->create(['role' => 'assistant', 'source' => $source, 'body' => $answer['text']]);
        return $answer + ['source' => $source, 'conversation_id' => $conversation->id];
    }

    public function clear(Request $request): void
    {
        $conversation = $this->conversation($request, false);
        if ($conversation) $conversation->messages()->delete();
    }

    public function conversation(Request $request, bool $create = true): ?ShoppingAssistantConversation
    {
        if ($user = $request->user()) return $create
            ? ShoppingAssistantConversation::firstOrCreate(['user_id' => $user->id])
            : ShoppingAssistantConversation::where('user_id', $user->id)->first();
        $token = $request->session()->get('shopping_assistant_guest');
        if (! $token && $create) { $token = Str::random(64); $request->session()->put('shopping_assistant_guest', $token); }
        return $token ? ($create ? ShoppingAssistantConversation::firstOrCreate(['guest_token' => $token]) : ShoppingAssistantConversation::where('guest_token', $token)->first()) : null;
    }

    private function deterministic(?User $user, string $message): array
    {
        $query = Str::lower($message);
        if (preg_match('/(ignore|system prompt|api.?key|secret|other (?:user|buyer)|database password)/i', $message)) {
            return ['text' => 'I can help with SUKI SHOP products, delivery, returns, and your own orders. I cannot provide private account information or system configuration.', 'products' => []];
        }
        if ($this->has($query, ['track', 'order', 'shipment'])) {
            if (! $user || ! $user->isBuyer()) return ['text' => 'Sign in as a Buyer to look up an order. I can still help with products, COD, delivery, and returns.', 'products' => []];
            preg_match('/\b(?:SKI|SUKI)-[A-Z0-9-]+\b/i', $message, $match);
            $reference = $match[0] ?? preg_replace('/[^A-Za-z0-9-]/', '', $message);
            $order = Order::where('buyer_id', $user->id)->where(fn ($q) => $q->where('order_number', 'like', '%'.$reference.'%')->orWhere('reference', 'like', '%'.$reference.'%'))->with('sellerOrders.shipment')->latest()->first();
            if (! $order) return ['text' => 'I could not find one of your orders from that reference. Open My Purchases to choose an order you own.', 'products' => []];
            return ['text' => 'Your order '.$order->order_number.' is currently '.$order->status.'.', 'products' => [], 'order_url' => route('buyer.order-details', $order)];
        }
        if ($this->has($query, ['cod', 'cash on delivery'])) return ['text' => 'SUKI SHOP supports Cash on Delivery where it is offered. Please prepare the exact order total for the assigned delivery rider. Online wallet or bank payment is not used for COD orders.', 'products' => []];
        if ($this->has($query, ['return', 'refund'])) return ['text' => 'For eligible delivered purchases, open My Purchases and choose the item to submit a return or refund request with the reason and evidence. Physical returns follow the approved hub instructions; refund payment is recorded only after review.', 'products' => []];
        if ($this->has($query, ['shipping', 'delivery', 'rider', 'logistics'])) return ['text' => 'Delivery status is updated as the parcel moves through pickup, sorting, destination hub, and last-mile delivery. Use your order tracking page for the current status; I cannot promise a delivery date.', 'products' => []];
        if ($this->has($query, ['support', 'human', 'agent'])) return ['text' => 'I can connect you to existing seller support for a product when you confirm it. For general account help, use Buyer Help or My Purchases so a support request has the right context.', 'products' => [], 'support_url' => $user?->isBuyer() ? route('buyer.help') : route('login')];

        $products = $this->catalogMatches($message);
        if ($products) return ['text' => 'Here are currently available SUKI SHOP products matching your search. Prices and stock are live at the time of this reply.', 'products' => $products];
        return ['text' => 'I could not find an available public product for that search. Try a product type, category, or use Find a product for current catalog suggestions.', 'products' => []];
    }

    private function safeContext(array $answer): array
    {
        return ['text' => $answer['text'], 'products' => collect($answer['products'] ?? [])->map(fn ($p) => ['name' => $p['name'], 'price' => $p['price'], 'stock' => $p['stock']])->all()];
    }

    /**
     * Keep discovery inside the public-catalog boundary.  A natural-language
     * query such as "coconut bowl" should still find "Coconut Serving Bowl"
     * when the full phrase is not an exact database substring.
     */
    private function catalogMatches(string $message): array
    {
        $catalog = app(ProductCatalogService::class);
        $queries = [Str::limit($message, 100, '')];
        foreach (preg_split('/[^[:alnum:]]+/u', Str::lower($message), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if (mb_strlen($word) >= 3) $queries[] = $word;
        }

        return collect($queries)->unique()->flatMap(fn ($query) => $catalog->marketplacePage(['q' => $query], 4)->items())
            ->unique('id')->take(4)->map(fn ($item) => [
                'id' => $item['id'],
                'name' => $item['name'],
                'price' => $item['price'],
                'stock' => $item['stock'],
                'url' => route('buyer.product', $item['slug']),
            ])->values()->all();
    }

    private function has(string $value, array $needles): bool { foreach ($needles as $needle) if (str_contains($value, $needle)) return true; return false; }
}
