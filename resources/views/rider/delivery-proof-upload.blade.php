<fieldset data-delivery-proof class="mb-4 min-w-0 rounded-xl border border-[#DCE5E0] bg-[#F6F9F7] p-4 focus-within:ring-2 focus-within:ring-[#1F6F5B]">
    <legend class="px-1 text-sm font-semibold text-[#173F35]">Delivery proof photo <span aria-hidden="true">*</span></legend>
    <p id="{{ $proofId }}-help" class="mb-3 text-xs text-[#617169]">Add a clear photo of the delivered parcel. JPG, JPEG, PNG or WebP, up to 5 MB. The photo uploads only when you confirm Mark as Delivered.</p>
    <input id="{{ $proofId }}" data-proof-input type="file" name="proof_photo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" required class="sr-only" aria-label="Delivery proof photo" aria-describedby="{{ $proofId }}-help {{ $proofId }}-error" aria-invalid="{{ $errors->has('proof_photo') ? 'true' : 'false' }}">
    <button data-proof-add type="button" class="flex min-h-28 w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-[#1F6F5B] bg-white px-4 py-5 text-sm font-semibold text-[#1F6F5B] hover:bg-[#EAF4EF] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1F6F5B]">
        <svg aria-hidden="true" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7a2 2 0 0 1 2-2h3l2-2h4l2 2h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/><circle cx="12" cy="12" r="4" stroke-width="1.5"/></svg>
        <span>Add Delivery Photo</span>
    </button>
    <div data-proof-selected hidden>
        <img data-proof-preview alt="Selected delivery proof photo preview" class="mx-auto max-h-56 w-full rounded-lg bg-white object-contain">
        <p data-proof-filename class="mt-2 break-all text-xs font-medium text-[#34483F]" role="status" aria-live="polite"></p>
        <div class="mt-3 flex flex-wrap gap-2">
            <button data-proof-replace type="button" class="rounded-lg border border-[#1F6F5B] bg-white px-3 py-2 text-sm font-semibold text-[#1F6F5B] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1F6F5B]">Replace Photo</button>
            <button data-proof-remove type="button" class="rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-semibold text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Remove Photo</button>
        </div>
    </div>
    <p id="{{ $proofId }}-error" data-proof-error class="mt-3 text-sm font-medium text-red-700" role="alert" @if(!$errors->has('proof_photo')) hidden @endif>{{ $errors->first('proof_photo') }}</p>
</fieldset>
