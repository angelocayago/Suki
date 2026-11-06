@if(($order['status'] ?? '') === 'delivered')
    <div class="flex flex-col items-start gap-2">
        <p class="text-sm text-gray-600">Received your order? Tap "Order Received" to complete your purchase.</p>
        <p class="text-xs text-gray-500">Orders are automatically completed 3 days after delivery.</p>
        <form action="{{ route('order.status.update', $order['id']) }}" method="POST" data-receipt-form>
            @csrf
            <input type="hidden" name="status" value="completed">
            <button type="submit" disabled aria-haspopup="dialog" aria-controls="buyerReceiptDialog" class="inline-flex items-center justify-center rounded-xl bg-[#1F6F5B] px-5 py-3 text-sm font-semibold text-white hover:bg-[#155244] disabled:opacity-60">
                Order Received
            </button>
        </form>
        <noscript><p class="text-xs text-gray-500">Enable JavaScript to confirm receipt.</p></noscript>
    </div>
@endif
