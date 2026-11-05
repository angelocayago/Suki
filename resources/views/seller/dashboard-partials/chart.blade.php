@php
    $initial = $salesSeries['7d']['points'];
    $max = max(1, ...array_column($initial,'raw_minor'));
    $points = [];
    foreach($initial as $index=>$point) $points[] = ['x'=>80+$index*620/6, 'y'=>210-$point['raw_minor']/$max*180];
    $path = implode(' ', array_map(fn($point,$index)=>($index?'L':'M').$point['x'].','.$point['y'], $points,array_keys($points)));
    $payload = collect($salesSeries)->map(fn($series)=>array_replace($series, ['total_minor'=>(string)$series['total_minor'], 'points'=>array_map(fn($point)=>array_replace($point,['raw_minor'=>(string)$point['raw_minor']]),$series['points'])]));
@endphp
<div class="sd-chart-wrap">
    <svg id="sellerSalesChart" class="sd-sales-chart" viewBox="0 0 720 260" role="group" aria-labelledby="salesChartTitle salesChartDescription">
        <title id="salesChartTitle">Completed raw merchandise sales</title>
        <desc id="salesChartDescription">Daily sales for the last seven days in Asia/Manila. Focus a point for its exact period and amount, or open the data table below.</desc>
        <defs><linearGradient id="sellerSalesFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#1f6f5b" stop-opacity=".20"/><stop offset="100%" stop-color="#1f6f5b" stop-opacity=".01"/></linearGradient></defs>
        <g class="sd-chart-grid"><path d="M80 30H700 M80 120H700 M80 210H700"/></g>
        <g class="sd-chart-axis"><text x="4" y="34" data-sales-axis="max">{{ $money($max) }}</text><text x="4" y="124" data-sales-axis="mid">{{ $money(intdiv($max,2)) }}</text><text x="4" y="214">₱0</text></g>
        <path data-sales-area d="{{ $path }} L700,210 L80,210 Z" fill="url(#sellerSalesFill)"/>
        <path data-sales-line d="{{ $path }}" class="sd-chart-line"/>
        <g data-sales-points>@foreach($points as $index=>$point)<circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" tabindex="0" role="img" aria-label="{{ $initial[$index]['period'] }}: {{ $money($initial[$index]['raw_minor']) }}"><title>{{ $initial[$index]['period'] }}: {{ $money($initial[$index]['raw_minor']) }}</title></circle>@endforeach</g>
        <g data-sales-labels class="sd-chart-axis">@foreach($points as $index=>$point)@if($index%2===0)<text x="{{ $point['x'] }}" y="244" text-anchor="middle">{{ $initial[$index]['label'] }}</text>@endif @endforeach</g>
    </svg>
</div>
<p data-sales-empty class="sd-chart-empty" @if($salesSeries['7d']['total_minor']!==0) hidden @endif>No completed merchandise sales in this range.</p>
<p data-sales-detail class="sd-chart-detail" role="status" aria-live="polite">Hover or focus a point to see the exact amount.</p>
<details class="sd-data-table"><summary>View exact sales data</summary><table><caption>Raw completed sales · Asia/Manila</caption><thead><tr><th scope="col">Period</th><th scope="col">Merchandise gross</th></tr></thead><tbody data-sales-table>@foreach($initial as $point)<tr><td>{{ $point['period'] }}</td><td>{{ $money($point['raw_minor']) }}</td></tr>@endforeach</tbody></table></details>
<script type="application/json" id="sellerSalesData">@json($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
