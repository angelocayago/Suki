@extends('layouts.seller')
@section('title','My Profile')
@section('page-title','My Profile')
@section('page-subtitle','Manage your personal information and account security.')
@section('content')
@include('seller.profile-style')
@php
    $securityFields = ['current_password', 'password', 'password_confirmation'];
    $personalFields = ['first_name', 'last_name', 'middle_initial', 'phone'];
    $securitySuccess = session('success') === 'Password updated successfully.';
@endphp
<div class="seller-my-profile">
    <section class="sp-card" id="personal-information" aria-labelledby="spPersonalHeading">
        <header class="sp-section-heading"><div><h2 id="spPersonalHeading">Personal Information</h2><p>Your account details and contact information.</p></div></header>
        <div class="sp-identity"><x-profile-avatar :user="$user"/><div><strong>{{ $user->full_name ?: $user->name }}</strong><span>Seller account</span></div></div>
        <dl class="sp-email"><div><dt>Email address</dt><dd>{{ $user->email ?: 'Not provided' }}</dd></div><span class="sp-readonly">Read only</span></dl>
        @if($errors->hasAny($personalFields))<div class="sp-errors" role="alert"><strong>Please review your details.</strong><ul>@foreach($personalFields as $field)@foreach($errors->get($field) as $message)<li>{{ $message }}</li>@endforeach @endforeach</ul></div>@endif
        <form method="POST" action="{{ route('seller.profile.update') }}" aria-labelledby="spPersonalHeading">
            @csrf @method('PATCH')
            <div class="sp-grid">
                <div class="sp-field"><label for="spFirstName">First name <span aria-hidden="true">*</span></label><input id="spFirstName" name="first_name" required maxlength="100" autocomplete="given-name" value="{{ old('first_name',$user->first_name) }}" aria-invalid="{{ $errors->has('first_name') ? 'true' : 'false' }}" @if($errors->has('first_name')) aria-describedby="spFirstNameError" @endif>@error('first_name')<p id="spFirstNameError" class="sp-field-error">{{ $message }}</p>@enderror</div>
                <div class="sp-field"><label for="spLastName">Last name <span aria-hidden="true">*</span></label><input id="spLastName" name="last_name" required maxlength="100" autocomplete="family-name" value="{{ old('last_name',$user->last_name) }}" aria-invalid="{{ $errors->has('last_name') ? 'true' : 'false' }}" @if($errors->has('last_name')) aria-describedby="spLastNameError" @endif>@error('last_name')<p id="spLastNameError" class="sp-field-error">{{ $message }}</p>@enderror</div>
                <div class="sp-field"><label for="spMiddleInitial">Middle initial <span class="sp-optional">(optional)</span></label><input id="spMiddleInitial" name="middle_initial" maxlength="4" value="{{ old('middle_initial',$user->middle_initial) }}" aria-invalid="{{ $errors->has('middle_initial') ? 'true' : 'false' }}" @if($errors->has('middle_initial')) aria-describedby="spMiddleInitialError" @endif>@error('middle_initial')<p id="spMiddleInitialError" class="sp-field-error">{{ $message }}</p>@enderror</div>
                <div class="sp-field"><label for="spPhone">Phone number <span class="sp-optional">(optional)</span></label><input id="spPhone" name="phone" type="tel" maxlength="30" autocomplete="tel" value="{{ old('phone',$user->phone) }}" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" @if($errors->has('phone')) aria-describedby="spPhoneError" @endif>@error('phone')<p id="spPhoneError" class="sp-field-error">{{ $message }}</p>@enderror</div>
            </div>
            <footer class="sp-form-footer">@if(session('success') && !$securitySuccess)<p class="sp-success" role="status">{{ session('success') }}</p>@endif<button type="submit">Save Profile</button></footer>
        </form>
    </section>
    <section class="sp-card sp-status-card" aria-labelledby="spStatusHeading">
        <header class="sp-section-heading"><div><h2 id="spStatusHeading">Account Status</h2></div><span class="sp-readonly">Read only</span></header>
        <dl class="sp-status-grid"><div><dt>Personal account status</dt><dd><span class="sp-status" data-status="{{ $user->status }}">{{ str($user->status)->replace('_',' ')->headline() }}</span></dd></div><div><dt>Store approval status</dt><dd>@if($store)<span class="sp-status" data-status="{{ $store->status }}">{{ str($store->status)->replace('_',' ')->headline() }}</span>@else<span class="sp-missing">No store record</span>@endif</dd></div></dl>
        <a class="sp-store-link" href="{{ route('seller.store.profile') }}">View Store Profile &rarr;</a>
    </section>
    <section class="sp-card" id="security" aria-labelledby="spSecurityHeading">
        <header class="sp-section-heading"><div><h2 id="spSecurityHeading">Account Security</h2><p>Update your password to keep your account secure.</p></div></header>
        @if($errors->hasAny($securityFields))<div class="sp-errors" role="alert"><strong>Please review your password.</strong><ul>@foreach($securityFields as $field)@foreach($errors->get($field) as $message)<li>{{ $message }}</li>@endforeach @endforeach</ul></div>@endif
        <form method="POST" action="{{ route('seller.store.password.update') }}" aria-labelledby="spSecurityHeading">
            @csrf
            <div class="sp-field"><label for="spCurrentPassword">Current password <span aria-hidden="true">*</span></label><input id="spCurrentPassword" type="password" name="current_password" required autocomplete="current-password" aria-invalid="{{ $errors->has('current_password') ? 'true' : 'false' }}" @if($errors->has('current_password')) aria-describedby="spCurrentPasswordError" @endif>@error('current_password')<p id="spCurrentPasswordError" class="sp-field-error">{{ $message }}</p>@enderror</div>
            <div class="sp-grid">
                <div class="sp-field"><label for="spNewPassword">New password <span aria-hidden="true">*</span></label><input id="spNewPassword" type="password" name="password" minlength="8" required autocomplete="new-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" aria-describedby="spPasswordHint{{ $errors->has('password') ? ' spNewPasswordError' : '' }}">@error('password')<p id="spNewPasswordError" class="sp-field-error">{{ $message }}</p>@enderror</div>
                <div class="sp-field"><label for="spPasswordConfirmation">Confirm new password <span aria-hidden="true">*</span></label><input id="spPasswordConfirmation" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password" aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}">@error('password_confirmation')<p class="sp-field-error">{{ $message }}</p>@enderror</div>
            </div>
            <p id="spPasswordHint" class="sp-note">At least 8 characters and different from your current password.</p>
            <footer class="sp-form-footer">@if(session('success') && $securitySuccess)<p class="sp-success" role="status">{{ session('success') }}</p>@endif<button type="submit">Update Password</button></footer>
        </form>
    </section>
</div>
@endsection
