<section class="reports-card reports-sales-chart">
    <header class="reports-section-heading"><h3>Sales Performance</h3><p>Raw Completed Sales &middot; {{ $data['sales_chart']['granularity'] ? ucfirst($data['sales_chart']['granularity']).' totals' : 'Completion dates' }} &middot; PHP</p></header>
    @if($data['sales_chart']['unavailable'])
        <p class="reports-empty">{{ $data['sales_chart']['unavailable'] }}</p>
    @elseif(empty($data['sales_chart']['points']))
        <p class="reports-empty">No sales data available for this period.</p>
    @else
        @php
            $points = $data['sales_chart']['points'];
            $maximum = max(array_column($points, 'raw_minor'));
            $scale = max(1, $maximum);
            $coordinates = [];
            foreach ($points as $index => $point) {
                $coordinates[] = [count($points) === 1 ? 420 : 90 + $index / (count($points) - 1) * 680, 205 - $point['raw_minor'] / $scale * 170];
            }
            $line = implode(' ', array_map(fn ($p) => round($p[0], 2).','.round($p[1], 2), $coordinates));
        @endphp
        <svg class="reports-line-chart" viewBox="0 0 800 250" role="group" aria-labelledby="reportsChartTitle reportsChartDescription">
            <title id="reportsChartTitle">Raw Completed Sales in PHP</title><desc id="reportsChartDescription">Completed Seller merchandise sales by Manila calendar period. Focus or select a point for its value. A full data table follows.</desc>
            @foreach([0, 1, 2] as $tick)
                <line x1="90" x2="770" y1="{{ 205 - $tick * 85 }}" y2="{{ 205 - $tick * 85 }}" class="reports-grid-line"/>
                <text x="80" y="{{ 209 - $tick * 85 }}" text-anchor="end">&#8369;{{ $money(intdiv($maximum * $tick, 2)) }}</text>
            @endforeach
            <polyline points="{{ $line }}" fill="none" stroke="#1F6F5B" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            @foreach($points as $index => $point)
                <circle cx="{{ round($coordinates[$index][0], 2) }}" cy="{{ round($coordinates[$index][1], 2) }}" r="4" fill="#1F6F5B" stroke="white" stroke-width="2" tabindex="0" role="img" aria-label="{{ $point['label'] }}: PHP {{ $money($point['raw_minor']) }}" data-report-point data-tooltip="{{ $point['label'] }} · PHP {{ $money($point['raw_minor']) }}"><title>{{ $point['label'] }}: &#8369;{{ $money($point['raw_minor']) }}</title></circle>
            @endforeach
            <text x="90" y="235">{{ $period['start']?->format('M d, Y') ?? explode(' – ', $points[0]['label'])[0] }}</text>
            @if(count($points) > 1)<text x="770" y="235" text-anchor="end">{{ $period['end']?->format('M d, Y') ?? $points[array_key_last($points)]['label'] }}</text>@endif
        </svg>
        <output id="reportsChartTooltip" class="reports-chart-tooltip" aria-live="polite">Select a point to see its value.</output>
        <details class="reports-chart-data"><summary>View chart data</summary><div class="reports-table-scroll"><table><thead><tr><th scope="col">Manila period</th><th scope="col" class="reports-numeric">Raw Completed Sales (PHP)</th></tr></thead><tbody>@foreach($points as $point)<tr><td>{{ $point['label'] }}</td><td class="reports-numeric">&#8369;{{ $money($point['raw_minor']) }}</td></tr>@endforeach</tbody></table></div></details>
    @endif
</section>
