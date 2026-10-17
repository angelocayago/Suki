@extends('layouts.seller')

@section('title', 'My Profile')
@section('page-title', 'My Profile')
@section('page-subtitle', 'Your personal information, photo, address and security')

@section('content')
@php
    $initialAddressValues = [
        'region' => old('region', $personalAddress?->region),
        'province' => old('province', $personalAddress?->province),
        'municipality' => old('municipality', $personalAddress?->city),
        'barangay' => old('barangay', $personalAddress?->barangay),
    ];
@endphp
<div class="mx-auto max-w-5xl space-y-6">
    @if(session('success'))
        <div role="status" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">Please check the form.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <nav aria-label="My Profile sections" class="flex flex-wrap gap-2 text-xs font-medium text-[#1F6F5B]">
        <a href="#personal-information" class="rounded-full border border-gray-200 bg-white px-4 py-2 hover:bg-gray-50">Personal Information</a>
        <a href="#profile-photo" class="rounded-full border border-gray-200 bg-white px-4 py-2 hover:bg-gray-50">Profile Photo</a>
        <a href="#personal-address" class="rounded-full border border-gray-200 bg-white px-4 py-2 hover:bg-gray-50">Personal Address</a>
        <a href="#security" class="rounded-full border border-gray-200 bg-white px-4 py-2 hover:bg-gray-50">Security</a>
    </nav>

    <section id="personal-information" class="scroll-mt-28 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-7">
        <h2 class="text-xs font-bold uppercase tracking-[0.08em] text-[#1F6F5B]">Personal Information</h2>
        <p class="mt-2 break-words text-lg font-semibold text-[#24312C]">{{ $owner->name }}</p>
        <form action="{{ route('seller.profile.update') }}" method="POST" class="mt-6 space-y-5">
            @csrf
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="sellerId" class="text-sm font-medium text-gray-700">Seller ID</label>
                    <input id="sellerId" readonly value="{{ $owner->seller_code }}" class="mt-2 w-full min-w-0 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10">
                </div>
                <div class="min-w-0">
                    <label for="personalEmail" class="text-sm font-medium text-gray-700">Email Address</label>
                    <input id="personalEmail" type="email" readonly value="{{ $owner->email }}" class="mt-2 w-full min-w-0 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10">
                    <p class="mt-1 text-xs leading-5 text-gray-400">Your login email is read-only until a verified seller email-change workflow is available.</p>
                    @error('email')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="personal_first_name" class="text-sm font-medium text-gray-700">First Name</label>
                    <input id="personal_first_name" name="first_name" type="text" required autocomplete="given-name" value="{{ old('first_name', $owner->first_name) }}" class="mt-2 w-full min-w-0 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10">
                    @error('first_name')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="personal_middle_initial" class="text-sm font-medium text-gray-700">Middle Initial (optional)</label>
                    <input id="personal_middle_initial" name="middle_initial" type="text" maxlength="2" autocomplete="additional-name" value="{{ old('middle_initial', $owner->middle_initial) }}" class="mt-2 w-full min-w-0 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10">
                    @error('middle_initial')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="personal_last_name" class="text-sm font-medium text-gray-700">Last Name</label>
                    <input id="personal_last_name" name="last_name" type="text" required autocomplete="family-name" value="{{ old('last_name', $owner->last_name) }}" class="mt-2 w-full min-w-0 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10">
                    @error('last_name')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="personal_phone" class="text-sm font-medium text-gray-700">Personal Contact Number</label>
                    <input id="personal_phone" name="phone" type="tel" required autocomplete="tel" value="{{ old('phone', $owner->phone) }}" class="mt-2 w-full min-w-0 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10">
                    @error('phone')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="text-xs text-gray-400">Personal contact accepts 09XXXXXXXXX or +639XXXXXXXXX. Store contact details are managed separately in Store Profile.</p>
            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-xl bg-[#1F6F5B] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#155244] focus:outline-none focus:ring-2 focus:ring-[#1F6F5B]/40">Save personal information</button>
                <a href="{{ route('seller.profile') }}" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</a>
            </div>
        </form>
    </section>

    <section id="profile-photo" class="scroll-mt-28 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-7">
        <h2 class="text-xs font-bold uppercase tracking-[0.08em] text-[#1F6F5B]">Personal Profile Photo</h2>
        <form action="{{ route('seller.profile.photo.update') }}" method="POST" enctype="multipart/form-data" class="mt-6 flex flex-col gap-5 sm:flex-row sm:items-start">
            @csrf
            <div class="shrink-0">
                <img id="personalPhotoPreview" @if($owner->profile_photo_path) src="{{ asset('storage/' . $owner->profile_photo_path) }}" @endif alt="Your personal profile photo" class="{{ $owner->profile_photo_path ? '' : 'hidden' }} h-36 w-36 rounded-[28px] border border-gray-200 object-cover sm:h-40 sm:w-40">
                <div id="personalPhotoPlaceholder" class="{{ $owner->profile_photo_path ? 'hidden' : '' }} flex h-36 w-36 items-center justify-center rounded-[28px] border border-gray-200 bg-[#EEF7F3] text-[#1F6F5B] sm:h-40 sm:w-40"><i data-lucide="user-round" class="h-10 w-10" aria-hidden="true"></i></div>
            </div>
            <div class="min-w-0 flex-1 space-y-3">
                <p class="text-xs leading-5 text-gray-400">Your personal photo is separate from your store logo and banner. JPG, PNG or WEBP, up to 2 MB.</p>
                <label for="personalPhoto" class="sr-only">Personal profile photo file</label>
                <input id="personalPhoto" name="photo" type="file" required tabindex="-1" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="personalPhotoStatus" class="sr-only">
                <div class="flex flex-wrap items-center gap-3">
                    <button id="personalPhotoButton" type="button" aria-controls="personalPhoto" class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-[#1F6F5B] hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#1F6F5B]/40">{{ $owner->profile_photo_path ? 'Replace image' : 'Upload image' }}</button>
                    @if($owner->profile_photo_path)
                        <button type="submit" formnovalidate formaction="{{ route('seller.profile.photo.remove') }}" class="rounded-xl px-3 py-2 text-xs font-medium text-red-500 hover:bg-red-50">Remove image</button>
                    @endif
                </div>
                <p id="personalPhotoStatus" role="status" class="text-xs text-[#52635B]"></p>
                @error('photo')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror
                <button type="submit" class="rounded-xl bg-[#1F6F5B] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#155244] focus:outline-none focus:ring-2 focus:ring-[#1F6F5B]/40">Save photo</button>
            </div>
        </form>
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-7">
        <h2 class="text-xs font-bold uppercase tracking-[0.08em] text-[#1F6F5B]">Account Status</h2>
        <dl class="mt-4 grid gap-5 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-400">Personal account status</dt><dd class="mt-1"><x-seller-status-badge :status="$owner->status" /></dd></div>
            <div><dt class="text-gray-400">Store approval status</dt><dd class="mt-1"><x-seller-status-badge :status="$store->status" /></dd></div>
        </dl>
        <p class="mt-3 text-xs text-gray-400">Account status and store approval are read-only. Local development access does not change approval status.</p>
    </section>

    <section id="personal-address" class="scroll-mt-28 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-7">
        <h2 class="text-xs font-bold uppercase tracking-[0.08em] text-[#1F6F5B]">Personal Address</h2>
        <p class="mt-2 break-words text-xs leading-5 text-gray-500">{{ $personalAddress ? collect([$personalAddress->line1, $personalAddress->barangay, $personalAddress->city, $personalAddress->province, $personalAddress->region, $personalAddress->postal_code])->filter()->implode(', ') : 'No personal Home address saved. Your store pickup address is managed separately.' }}</p>
        <form action="{{ route('seller.profile.address.update') }}" method="POST" class="mt-5 space-y-5">
            @csrf
            <p id="profileAddressStatus" role="status" class="text-xs text-amber-700"></p>
            <div class="grid gap-5 sm:grid-cols-2">
                <div><label for="profileRegion" class="text-sm font-medium text-gray-700">Region</label><select id="profileRegion" name="region" required class="mt-2 w-full min-w-0 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"><option value="">Loading locations...</option></select>@error('region')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror</div>
                <div><label for="profileProvince" class="text-sm font-medium text-gray-700">Province</label><select id="profileProvince" name="province"  class="mt-2 w-full min-w-0 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"><option value="">Loading locations...</option></select>@error('province')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror</div>
                <div><label for="profileMunicipality" class="text-sm font-medium text-gray-700">Municipality / City</label><select id="profileMunicipality" name="municipality" required class="mt-2 w-full min-w-0 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"><option value="">Loading locations...</option></select>@error('municipality')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror</div>
                <div><label for="profileBarangay" class="text-sm font-medium text-gray-700">Barangay</label><select id="profileBarangay" name="barangay" required class="mt-2 w-full min-w-0 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"><option value="">Loading locations...</option></select>@error('barangay')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror</div>
                <div><label for="personalStreet" class="text-sm font-medium text-gray-700">Street / House No. / Building</label><input id="personalStreet" name="address" required maxlength="255" value="{{ old('address', $personalAddress?->line1) }}" class="mt-2 w-full min-w-0 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10">@error('address')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror</div>
                <div><label for="personalPostalCode" class="text-sm font-medium text-gray-700">Postal code</label><input id="personalPostalCode" name="postal_code" required inputmode="numeric" pattern="[0-9]{4}" maxlength="4" value="{{ old('postal_code', $personalAddress?->postal_code) }}" class="mt-2 w-full min-w-0 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10">@error('postal_code')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror</div>
            </div>
            <button type="submit" class="rounded-xl bg-[#1F6F5B] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#155244] focus:outline-none focus:ring-2 focus:ring-[#1F6F5B]/40">Save personal address</button>
        </form>
    </section>
    @include('seller.partials.security')
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    @include('seller.partials.image-upload-script')
    bindImageUpload('personalPhoto', 'personalPhotoPreview', 'personalPhotoPlaceholder', 'personalPhotoButton', 'personalPhotoStatus', 2, 'Image selected. Save photo to upload.');
    @include('seller.partials.address-script')
    document.querySelectorAll('main form').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.submitting) { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            if (event.submitter) event.submitter.setAttribute('aria-disabled', 'true');
        });
    });
});
</script>
@endpush
