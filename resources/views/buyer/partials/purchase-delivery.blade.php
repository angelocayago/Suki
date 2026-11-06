@foreach($order['seller_orders'] as $parcel)
    <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-gray-600">
        <span>{{ $parcel['seller_name'] }}: {{ ucwords(str_replace('_', ' ', $parcel['status'])) }}</span>
        @if($parcel['delivered_at'])
            <span>Delivered {{ \App\Support\ManilaTime::format($parcel['delivered_at']) }} PHT</span>
        @endif
        @if($parcel['status'] === 'delivered')
            <span>{{ $parcel['auto_complete_at'] ? 'Automatic completion from '.\App\Support\ManilaTime::format($parcel['auto_complete_at']).' PHT' : 'Automatic completion unavailable: delivery timestamp missing. Please contact support.' }}</span>
        @endif
        @if(in_array($parcel['status'], ['picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivered', 'completed', 'delivery_failed', 'rts_initiated', 'rts_returning', 'rts_completed'], true))
            <a href="{{ route('buyer.seller-order.track', [$order['order_id'], $parcel['seller_order_id']]) }}" class="rounded-lg border border-[#1F6F5B] px-3 py-2 font-semibold text-[#1F6F5B]">Track Order · {{ $parcel['seller_name'] }}</a>
        @endif
    </div>
@endforeach
@if(empty($order['seller_orders']) && $order['status'] === 'delivered')
    <p class="mb-3 text-xs text-gray-600">{{ $order['delivered_at'] ? 'Delivered '.\App\Support\ManilaTime::format($order['delivered_at']).' PHT. Automatic completion after three full days.' : 'Automatic completion unavailable: delivery timestamp missing. Please contact support.' }}</p>
@endif
