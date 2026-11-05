@if(isset($formKey))
@php
    $attempted = old('_review_form') === $formKey;
    $selectedRating = $attempted ? old('rating') : ($review?->rating ?? null);
    $fieldId = str_replace(':', '-', $formKey);
@endphp
<input type="hidden" name="_review_form" value="{{ $formKey }}">
<fieldset class="mt-3" aria-describedby="{{ $fieldId }}-rating-error">
    <legend class="text-sm font-semibold">Rating (required): choose 1–5 stars</legend>
    <div class="mt-2 flex flex-wrap gap-2">
        @foreach(range(1, 5) as $rating)
            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus-within:ring-2 focus-within:ring-[#1F6F5B]">
                <input type="radio" name="rating" value="{{ $rating }}" required @checked((string) $selectedRating === (string) $rating) aria-label="{{ $rating }} out of 5 stars" class="accent-[#1F6F5B]">
                <span><span aria-hidden="true">&#9733;</span> {{ $rating }}/5</span>
            </label>
        @endforeach
    </div>
    <p id="{{ $fieldId }}-rating-error" class="mt-1 text-sm text-red-700" @if($attempted && $errors->has('rating')) role="alert" @endif>@if($attempted){{ $errors->first('rating') }}@endif</p>
</fieldset>
<label for="{{ $fieldId }}-body" class="mt-3 block text-sm font-semibold">Comment (optional, maximum 500 characters)</label>
<textarea id="{{ $fieldId }}-body" name="body" maxlength="500" rows="3" class="mt-2 block w-full rounded-lg border border-gray-300 bg-white p-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#1F6F5B]" aria-describedby="{{ $fieldId }}-body-error">{{ $attempted ? old('body') : ($review?->body ?? '') }}</textarea>
<p id="{{ $fieldId }}-body-error" class="mt-1 text-sm text-red-700" @if($attempted && $errors->has('body')) role="alert" @endif>@if($attempted){{ $errors->first('body') }}@endif</p>
<button class="mt-3 rounded-lg bg-[#1F6F5B] px-4 py-2.5 text-sm font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1F6F5B]" type="submit">{{ $review ? 'Save Review Changes' : 'Submit Review' }}</button>
@else
<label>Rating
    <select name="rating" required>
        @foreach (range(1, 5) as $rating)
            <option value="{{ $rating }}" @selected(($review->rating ?? 5) == $rating)>{{ $rating }}</option>
        @endforeach
    </select>
</label>
<label class="block">Comment (optional, maximum 500 characters)
    <textarea name="body" maxlength="500" class="block w-full border rounded p-2">{{ $review->body ?? '' }}</textarea>
</label>
<button class="underline" type="submit">Save</button>
@endif
