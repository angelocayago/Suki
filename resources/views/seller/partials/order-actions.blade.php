<div class="orders-actions">
    @if($order['next_action'])
        <form method="POST" action="{{ route('seller.orders.status', $order['id']) }}">
            @csrf
            <input type="hidden" name="status" value="{{ $order['next_action'][0] }}">
            <button class="orders-button orders-button-primary" type="submit">{{ $order['next_action'][1] }}</button>
        </form>
    @endif
    <form method="POST" action="{{ route('seller.messages.context.seller-order.store', $order['id']) }}">
        @csrf
        <button type="submit" class="orders-button">Message</button>
    </form>
    <a class="orders-button" href="{{ route('seller.complaints.create', $order['id']) }}">Complaint</a>
</div>
