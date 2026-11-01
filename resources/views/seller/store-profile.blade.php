@extends('layouts.seller')
@section('title', 'Store Profile')
@section('page-title', 'Store Profile')
@section('page-subtitle', 'Manage your shop information and pickup address.')
@section('content')
@include('seller.store-profile-style')
@php
    $owner = $seller->owner;
    $shopName = $seller->name;
    $businessCategory = $seller->category?->name ?? '';
    $sellerName = $owner?->full_name ?: $owner?->name;
    $phone = $owner?->phone;
    $email = $owner?->email;
    $description = $seller->description;
    $pickupAddress = $seller->pickupAddress;
    $region = old('region', $pickupAddress?->region_code ?? '');
    $province = old('province', $pickupAddress?->province_code ?? '');
    $municipality = old('municipality', $pickupAddress?->city_municipality_code ?? '');
    $barangay = old('barangay', $pickupAddress?->barangay_code ?? '');
    $postalCode = $pickupAddress?->postal_code ?? '';
    $streetAddress = $pickupAddress?->line1;
    $profilePhoto = $seller->logo_path;
    $bannerPhoto = $seller->banner_path;
    $status = $seller->status;
    $isVerified = $status === 'approved' && filled($seller->valid_id_path) && filled($seller->business_permit_path);
    $accountStatus = match ($status) {
        'approved' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended',
        'rejected' => 'Rejected', default => 'Not available',
    };
    $memberSince = $seller->created_at?->format('F j, Y');
@endphp

<div class="store-profile-page">
    @if(session('success') && !session('store_profile_feedback'))<div class="stp-feedback stp-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="stp-feedback stp-errors" role="alert"><strong>Please check the form.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <nav class="stp-nav" aria-label="Store profile sections"><a href="#shop-profile">Shop Profile</a><a href="#pickup-address">Pickup Address</a><a href="#business-verification">Business &amp; Verification</a><a class="stp-security" href="{{ route('seller.profile') }}#security">Account security &rarr;</a></nav>
    <section id="shop-profile" class="stp-card" aria-labelledby="stp-shop-heading">
        <header class="stp-section-heading"><div><h2 id="stp-shop-heading">Shop Profile</h2><p>Your shop information and storefront images.</p></div><span class="stp-badge">{{ ucfirst($status) }}</span></header>
        <dl class="stp-readonly"><div><dt>Shop ID</dt><dd>#{{ $seller->id }}</dd></div><div><dt>Assigned main category / Line of business</dt><dd>{{ $businessCategory ?: 'Not provided' }}</dd></div></dl>
        <form id="storeProfileForm" action="{{ route('seller.store.profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="stp-field"><label for="store_name">Store name</label><input id="store_name" name="store_name" maxlength="150" value="{{ old('store_name', $shopName) }}" autocomplete="organization" aria-invalid="{{ $errors->has('store_name') ? 'true' : 'false' }}" aria-describedby="store-name-error">@error('store_name')<p id="store-name-error" class="stp-field-error">{{ $message }}</p>@enderror</div>
            <div class="stp-logo-row">
                <div class="stp-logo-preview"><img id="shopPhotoPreview" @if($profilePhoto) src="{{ asset('storage/'.$profilePhoto) }}" @else hidden @endif data-original-src="{{ $profilePhoto ? asset('storage/'.$profilePhoto) : '' }}" alt="{{ $shopName ?: 'Store logo' }}"><span id="shopPhotoPlaceholder" @if($profilePhoto) hidden @endif aria-label="No store logo"><i data-lucide="store" aria-hidden="true"></i></span></div>
                <div class="stp-image-controls"><label for="profile_photo">Store logo</label><p class="stp-hint">JPG, PNG or WEBP &middot; up to 2 MB.</p><input id="profile_photo" name="profile_photo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="photoFileName profile-photo-error">
                    <div class="stp-image-actions"><button type="button" data-clear-image="profile_photo" hidden>Cancel selection</button>@if($profilePhoto)<button type="submit" form="removeStoreLogo" class="stp-danger">Remove logo</button>@endif</div>
                    <p id="photoFileName" class="stp-hint" role="status" aria-live="polite"></p>@error('profile_photo')<p id="profile-photo-error" class="stp-field-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="stp-banner-editor"><label for="banner">Store banner</label><div class="stp-banner-preview"><img id="shopBannerPreview" @if($bannerPhoto) src="{{ asset('storage/'.$bannerPhoto) }}" @else hidden @endif data-original-src="{{ $bannerPhoto ? asset('storage/'.$bannerPhoto) : '' }}" alt="{{ $shopName ?: 'Store' }} banner"><span id="shopBannerPlaceholder" @if($bannerPhoto) hidden @endif>No store banner yet</span></div><div class="stp-banner-controls"><input id="banner" name="banner" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="bannerFileName banner-error"><button type="button" data-clear-image="banner" hidden>Cancel selection</button>@if($bannerPhoto)<button type="submit" form="removeStoreBanner" class="stp-danger">Remove banner</button>@endif</div><p class="stp-hint">JPG, PNG or WEBP &middot; up to 4 MB. Saved with your store profile.</p><p id="bannerFileName" class="stp-hint" role="status" aria-live="polite"></p>@error('banner')<p id="banner-error" class="stp-field-error">{{ $message }}</p>@enderror</div>
            <div class="stp-field"><label for="phone">Contact number <span class="stp-required" aria-hidden="true">*</span></label><input id="phone" type="tel" name="phone" value="{{ old('phone', $phone) }}" required maxlength="30" autocomplete="tel" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" aria-describedby="phone-hint phone-error"><p id="phone-hint" class="stp-hint">Also used as your personal account phone number.</p>@error('phone')<p id="phone-error" class="stp-field-error">{{ $message }}</p>@enderror</div>
            <div class="stp-field"><div class="stp-label-row"><label for="description">Shop description <span class="stp-hint">(optional)</span></label><span id="descriptionCount" class="stp-hint">{{ mb_strlen(old('description', $description) ?? '') }}/500</span></div><textarea id="description" name="description" rows="3" maxlength="500" placeholder="Tell buyers what your shop is about..." aria-describedby="descriptionCount description-error" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}">{{ old('description', $description) }}</textarea>@error('description')<p id="description-error" class="stp-field-error">{{ $message }}</p>@enderror</div>
            @if(session('success') && session('store_profile_feedback'))<div id="store-profile-feedback" class="stp-feedback stp-success" role="status" tabindex="-1">{{ session('success') }}</div>@endif
            <footer class="stp-form-footer"><p class="stp-hint" id="storeProfileSaveStatus" role="status" aria-live="polite">Profile changes are saved separately from your address.</p><button class="stp-primary" type="submit" id="storeProfileSave">Save Store Profile</button></footer>
        </form>
        @if($profilePhoto)<form id="removeStoreLogo" action="{{ route('seller.store.profile.photo.remove') }}" method="POST">@csrf</form>@endif
        @if($bannerPhoto)<form id="removeStoreBanner" action="{{ route('seller.store.profile.banner.remove') }}" method="POST">@csrf</form>@endif
    </section>
    <section id="pickup-address" class="stp-card" aria-labelledby="stp-pickup-heading">
        <header class="stp-section-heading"><div><h2 id="stp-pickup-heading">Pickup Address</h2><p>The saved location used for store pickups.</p></div></header>
        <div class="stp-address-summary"><span>Saved pickup address</span><p>{{ collect([$pickupAddress?->line1, $pickupAddress?->barangay, $pickupAddress?->city, $pickupAddress?->province, $pickupAddress?->region, $pickupAddress?->postal_code])->filter()->implode(', ') ?: 'No pickup address saved yet.' }}</p></div>
        <div id="pickupLocationError" class="stp-feedback stp-errors" role="alert" hidden><p id="pickupLocationErrorText"></p><button id="pickupLocationRetry" type="button" class="stp-primary">Retry locations</button></div>
        <p id="pickupLocationStatus" class="stp-hint" role="status" aria-live="polite"></p>
        <form id="storeAddressForm" data-initial-region="{{ $region }}" data-initial-province="{{ $province }}" data-initial-city="{{ $municipality }}" data-initial-barangay="{{ $barangay }}" action="{{ route('seller.store.address.update') }}" method="POST">@csrf
            <div class="stp-grid">
                <div class="stp-field"><label for="profileRegion">Region <span class="stp-required" aria-hidden="true">*</span></label><select id="profileRegion" name="region" required aria-describedby="region-error"><option value="{{ $region }}">{{ $region === ($pickupAddress?->region_code ?? '') ? ($pickupAddress?->region ?: 'Select region') : ($region ?: 'Select region') }}</option></select>@error('region')<p id="region-error" class="stp-field-error">{{ $message }}</p>@enderror</div>
                <div class="stp-field"><label for="profileProvince">Province</label><select id="profileProvince" name="province" aria-describedby="province-error"><option value="{{ $province }}">{{ $province === ($pickupAddress?->province_code ?? '') ? ($pickupAddress?->province ?: 'Select region first') : ($province ?: 'Select region first') }}</option></select>@error('province')<p id="province-error" class="stp-field-error">{{ $message }}</p>@enderror</div>
                <div class="stp-field"><label for="profileMunicipality">Municipality / City <span class="stp-required" aria-hidden="true">*</span></label><select id="profileMunicipality" name="municipality" required aria-describedby="municipality-error"><option value="{{ $municipality }}">{{ $municipality === ($pickupAddress?->city_municipality_code ?? '') ? ($pickupAddress?->city ?: 'Select province or region first') : ($municipality ?: 'Select province or region first') }}</option></select>@error('municipality')<p id="municipality-error" class="stp-field-error">{{ $message }}</p>@enderror</div>
                <div class="stp-field"><label for="profileBarangay">Barangay <span class="stp-required" aria-hidden="true">*</span></label><select id="profileBarangay" name="barangay" required aria-describedby="barangay-error"><option value="{{ $barangay }}">{{ $barangay === ($pickupAddress?->barangay_code ?? '') ? ($pickupAddress?->barangay ?: 'Select municipality/city first') : ($barangay ?: 'Select municipality/city first') }}</option></select>@error('barangay')<p id="barangay-error" class="stp-field-error">{{ $message }}</p>@enderror</div>
                <div class="stp-field"><label for="profilePostalCode">Postal code</label><input id="profilePostalCode" type="text" value="{{ $postalCode }}" readonly aria-describedby="postal-hint" placeholder="Auto-filled from city / municipality"><p id="postal-hint" class="stp-hint">Automatically determined from your city or municipality.</p></div>
                <div class="stp-field"><label for="pickupContactDisplay">Saved pickup contact</label><input id="pickupContactDisplay" value="{{ $pickupAddress?->phone ?: ($phone ?? '') }}" readonly><p class="stp-hint">Uses your account contact when the address is saved.</p></div>
            </div>
            <div class="stp-field"><label for="profileAddress">Street / House No. / Building <span class="stp-required" aria-hidden="true">*</span></label><input id="profileAddress" name="address" value="{{ old('address', $streetAddress) }}" required maxlength="255" autocomplete="street-address" aria-describedby="address-error">@error('address')<p id="address-error" class="stp-field-error">{{ $message }}</p>@enderror</div>
            <footer class="stp-form-footer"><p class="stp-hint">Choose locations from the live PSGC lists.</p><button id="storeAddressSave" class="stp-primary" type="submit">Save Address</button></footer>
        </form>
    </section>
    <section id="business-verification" class="stp-card" aria-labelledby="stp-business-heading">
        <header class="stp-section-heading"><div><h2 id="stp-business-heading">Business &amp; Verification</h2><p>Your saved business and account information.</p></div><span class="stp-readonly-label">Read only</span></header>
        <dl class="stp-business-grid"><div><dt>Business name</dt><dd>{{ $shopName ?: 'Not provided' }}</dd></div><div><dt>Line of business</dt><dd>{{ $businessCategory ?: 'Not provided' }}</dd></div><div><dt>Account holder</dt><dd>{{ $sellerName ?: 'Not provided' }}</dd></div><div><dt>Email</dt><dd>{{ $email ?: 'Not provided' }}</dd></div><div><dt>Verification</dt><dd><span class="stp-badge {{ $isVerified ? '' : 'stp-pending' }}">{{ $isVerified ? 'Verified' : 'Pending' }}</span></dd></div><div><dt>Store approval status</dt><dd><span class="stp-badge">{{ ucfirst($status) }}</span></dd></div><div><dt>Member since</dt><dd>{{ $memberSince ?: 'Not provided' }}</dd></div></dl>
        <p class="stp-business-note">Assigned category and registration details are read-only. Manage personal details in <a href="{{ route('seller.profile') }}">My Profile</a>.</p>
    </section>
</div>
@include('seller.store-profile-script')
@endsection
@include('seller.store-profile-address-script')
