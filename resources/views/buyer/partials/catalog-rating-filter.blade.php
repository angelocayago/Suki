<details class="catalog-filter-section" open>
    <summary>Customer Rating</summary>
    <fieldset class="catalog-rating-options"><legend class="sr-only">Filter by customer rating</legend>
        @foreach(['' => 'Any rating', 5 => '5 stars', 4 => '4 stars & up', 3 => '3 stars & up', 2 => '2 stars & up', 1 => '1 star & up'] as $value => $label)
            <label class="catalog-rating-row">
                <input type="radio" name="catalog_rating" value="{{ $value }}" data-catalog-rating @checked((string) request('rating', '') === (string) $value)>
                @if($value !== '')<span aria-label="{{ $label }}" class="catalog-stars">@for($star = 1; $star <= 5; $star++)<span class="{{ $star <= (int) $value ? 'is-filled' : '' }}" aria-hidden="true">@if($star <= (int) $value)&#9733;@else&#9734;@endif</span>@endfor</span>@endif
                <span>{{ $value === '' ? $label : ((int) $value === 5 ? '5 stars' : '& up') }}</span>
            </label>
        @endforeach
    </fieldset>
</details>
