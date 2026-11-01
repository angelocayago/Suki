<div class="reports-filter-toolbar"><button type="button" id="reportsFilterToggle" aria-expanded="false" aria-controls="reportsFilterPanel">Filters @if($period['date'] !== 'any')<span class="reports-badge">1</span>@endif</button>
    @if($period['date'] !== 'any')<a class="reports-chip" href="{{ route('seller.reports') }}" aria-label="Remove date filter: {{ $period['label'] }}">{{ $period['label'] }} <span aria-hidden="true">&times;</span></a>@endif
</div>
@if($errors->any())<div class="reports-errors" role="alert"><strong>Please review your report period.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form id="reportsFilterPanel" class="reports-card reports-filter-panel" method="GET" action="{{ route('seller.reports') }}" hidden>
    <fieldset><legend>Report period</legend><div class="reports-date-options">
        @foreach(\App\Support\SellerReports::dateOptions() as $value=>$label)<label><input type="radio" name="date" value="{{ $value }}" @checked($period['date'] === $value)>{{ $label }}</label>@endforeach
    </div></fieldset>
    <div id="reportsCustomDates" class="reports-custom-dates" @if($period['date'] !== 'custom') hidden @endif>
        <label>From date<input id="reportsFrom" type="date" name="from" value="{{ $period['start']?->format('Y-m-d') }}"></label>
        <label>To date<input id="reportsTo" type="date" name="to" value="{{ $period['end']?->format('Y-m-d') }}"></label>
    </div>
    <p class="reports-note">Inclusive Manila calendar dates. Completed sales use completion dates; other order counts use their last status update dates.</p>
    <footer><a href="{{ route('seller.reports') }}">Clear All</a><button type="submit" class="reports-primary" id="reportsApply">Apply Filters</button></footer>
</form>
<noscript><style>#reportsFilterPanel[hidden],#reportsCustomDates[hidden]{display:block}#reportsFilterToggle{display:none}</style></noscript>
