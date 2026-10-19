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
