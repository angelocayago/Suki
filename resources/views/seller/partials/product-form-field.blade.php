<div>
    <label for="{{ $field }}">{{ $label }}{{ ($required ?? false) ? ' *' : '' }}</label>
    <input id="{{ $field }}" name="{{ $field }}" type="{{ $type ?? 'text' }}" value="{{ old($field, $item[$field] ?? '') }}"
        @required($required ?? false)
        @if(isset($maxLength)) maxlength="{{ $maxLength }}" @endif
        @if(($type ?? '') === 'number') min="0" step="{{ $step ?? '1' }}" max="{{ $maximum }}" @endif
        aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" aria-describedby="{{ $field }}-error">
    <p class="spf-error" id="{{ $field }}-error">@error($field){{ $message }}@enderror</p>
</div>
