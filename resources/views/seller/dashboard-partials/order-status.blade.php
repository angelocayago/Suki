@php
    // Presentation only: retain the dashboard's existing canonical grouping.
    $palette = ['completed'=>'#15803D', 'progress'=>'#D97706', 'delivered'=>'#2563EB', 'attention'=>'#DC2626', 'unknown'=>'#64748B'];
    $attentionStatuses = ['cancelled','delivery_failed','rts_initiated','rts_returning','rts_completed'];
    $attention = array_sum(array_intersect_key($statusCounts, array_flip($attentionStatuses)));
    $slices = [
        'completed'=>['label'=>'Completed','count'=>$completedCount,'status'=>'COMPLETED'],
        'progress'=>['label'=>'In Progress','count'=>$orderCount-$completedCount-($statusCounts['delivered']??0)-$attention-($statusCounts['unknown']??0),'status'=>null],
        'delivered'=>['label'=>'Delivered','count'=>$statusCounts['delivered']??0,'status'=>'DELIVERED'],
        'attention'=>['label'=>'Cancelled / Failed / RTS','count'=>$attention,'status'=>null],
        'unknown'=>['label'=>'Unknown workflow','count'=>$statusCounts['unknown']??0,'status'=>null],
    ];
    $categoryFor = fn($status) => match(true) {
        $status==='completed'=>'completed', $status==='delivered'=>'delivered',
        in_array($status,$attentionStatuses,true)=>'attention', $status==='unknown'=>'unknown', default=>'progress',
    };
    $offset = 0;
    $nonzero = count(array_filter($slices, fn($slice)=>$slice['count']>0));
    $percentage = fn($count) => $orderCount ? $count/$orderCount*100 : 0;
@endphp
<article class="sd-panel sd-status-widget" data-order-status>
    <header class="sd-panel-heading"><div><span class="sd-eyebrow">Fulfillment health</span><h3>Order Status</h3><p>All Seller Orders · canonical Seller workflow</p></div></header>
    <div class="sd-status-body">
        <div class="sd-status-visual">
            <svg class="sd-status-donut" viewBox="0 0 200 200" role="group" aria-labelledby="orderStatusTitle orderStatusDescription">
                <title id="orderStatusTitle">{{ number_format($orderCount) }} Seller Orders</title>
                <desc id="orderStatusDescription">Status distribution. Focus a segment or use the status buttons below to inspect counts and percentages.</desc>
                <circle class="sd-status-track" cx="100" cy="100" r="78" fill="none" stroke-width="22"/>
                @foreach($slices as $key=>$slice)
                    @if($slice['count']>0)
                        @php
                            $share = $percentage($slice['count']);
                            $gap = $nonzero>1 ? min(1, $share/4) : 0;
                        @endphp
                        <circle class="sd-status-segment" data-status-segment="{{ $key }}" data-status-label="{{ $slice['label'] }}" data-status-count="{{ $slice['count'] }}" data-status-percent="{{ number_format($share,1,'.','') }}" style="--status-color:{{ $palette[$key] }}" cx="100" cy="100" r="78" fill="none" stroke-width="22" pathLength="100" stroke-dasharray="{{ $share-$gap }} {{ 100-$share+$gap }}" stroke-dashoffset="{{ -$offset }}" transform="rotate(-90 100 100)" tabindex="0" role="img" aria-label="{{ $slice['label'] }}: {{ number_format($slice['count']) }} orders, {{ number_format($share,1) }}%">
                            <title>{{ $slice['label'] }}: {{ number_format($slice['count']) }} orders ({{ number_format($share,1) }}%)</title>
                        </circle>
                        @php $offset += $share; @endphp
                    @endif
                @endforeach
            </svg>
            <div class="sd-status-center" aria-hidden="true"><strong>{{ number_format($orderCount) }}</strong><span>Seller Orders</span></div>
        </div>
        <p class="sd-status-insight" data-status-detail role="status" aria-live="polite">{{ $orderCount ? 'Hover, focus or tap a status to explore your orders.' : 'No orders yet. Your fulfillment overview will appear here.' }}</p>
        <ul class="sd-status-legend">
            @foreach($slices as $key=>$slice)
                <li style="--status-color:{{ $palette[$key] }}" class="{{ $slice['count']===0?'is-zero':'' }}">
                    <button type="button" class="sd-status-choice" data-status-choice="{{ $key }}" data-status-label="{{ $slice['label'] }}" data-status-count="{{ $slice['count'] }}" data-status-percent="{{ number_format($percentage($slice['count']),1,'.','') }}" aria-pressed="false" aria-label="{{ $slice['label'] }}: {{ number_format($slice['count']) }} orders, {{ number_format($percentage($slice['count']),1) }}%">
                        <span class="sd-status-dot" aria-hidden="true"></span><span>{{ $slice['label'] }}</span><strong>{{ number_format($slice['count']) }}</strong><small>{{ number_format($percentage($slice['count']),1) }}%</small>
                    </button>
                    @if($slice['status'])<a class="sd-status-orders-link" href="{{ route('seller.orders',['status'=>$slice['status']]) }}" aria-label="View {{ $slice['label'] }} orders">View orders ↗</a>@endif
                </li>
            @endforeach
        </ul>
        @if($orderCount)
            <details class="sd-status-details"><summary>Explore individual statuses</summary><p>Counts by Seller Order workflow state</p><div>
                @foreach($statusCounts as $status=>$count)
                    @php $category = $categoryFor($status); @endphp
                    @if(isset($statusLabels[strtoupper($status)]))
                        <a class="sd-status-badge" data-status-category="{{ $category }}" style="--status-color:{{ $palette[$category] }}" href="{{ route('seller.orders',['status'=>strtoupper($status)]) }}"><span class="sd-status-dot" aria-hidden="true"></span>{{ $statusLabels[strtoupper($status)] }} <strong>{{ number_format($count) }}</strong></a>
                    @else
                        <span class="sd-status-badge" data-status-category="unknown" style="--status-color:{{ $palette['unknown'] }}"><span class="sd-status-dot" aria-hidden="true"></span>Unknown workflow <strong>{{ number_format($count) }}</strong></span>
                    @endif
                @endforeach
            </div></details>
        @endif
    </div>
</article>
