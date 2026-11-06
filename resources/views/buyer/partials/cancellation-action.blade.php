@if(array_key_exists('cancel_rejection', $order) && $order['cancel_rejection'] === null)
    <button type="button" data-cancel-order="{{ $order['id'] }}" data-cancel-action="{{ route('buyer.order.cancel', $order['id']) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-white px-4 py-2.5 text-xs font-semibold text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700" aria-haspopup="dialog" aria-controls="buyerCancellationDialog">
        <i data-lucide="x" class="h-4 w-4" aria-hidden="true"></i>Cancel Order
    </button>
@elseif(in_array(strtolower($order['status']), ['placed','order_placed'], true))
    <p class="mt-3 text-xs leading-5 text-gray-500" data-cancel-unavailable>{{ $order['cancel_rejection'] ?? 'Please contact support to review cancellation eligibility.' }}</p>
@endif
