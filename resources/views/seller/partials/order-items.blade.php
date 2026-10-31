<ul class="orders-item-list">
    @forelse($order['items'] as $item)
        <li>
            <strong>{{ $item['name'] }}</strong>
            @if($item['variant'])<small>{{ $item['variant'] }}</small>@endif
            @foreach($item['options'] as $option => $value)
                @if(is_scalar($value))<small>{{ $option }}: {{ $value }}</small>@endif
            @endforeach
            <small>{{ $item['quantity'] }} &times; @if($item['unit_price_minor'] !== null)&#8369;{{ number_format($item['unit_price_minor'] / 100, 2) }}@else Price not recorded @endif</small>
            @if($item['recorded_line_total'] !== null)<small>Recorded line total: &#8369;{{ number_format((float) $item['recorded_line_total'], 2) }}</small>@endif
        </li>
    @empty
        <li class="orders-muted">No purchased-item snapshot recorded.</li>
    @endforelse
</ul>
