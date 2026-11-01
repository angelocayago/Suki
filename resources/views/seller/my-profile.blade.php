@extends('layouts.seller')
@section('title','My Profile')
@section('page-title','My Profile')
@section('content')
@include('seller.profile-style')
<div class="seller-my-profile">
    <header><h2>My Profile</h2><p>Your personal account details and security.</p></header>
    @if(session('success'))<p class="sp-success" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="sp-errors" role="alert"><strong>Please review your details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="sp-card" aria-labelledby="spPersonalHeading">
        <div class="sp-identity"><x-profile-avatar :user="$user"/><div><h3 id="spPersonalHeading">Personal Information</h3><p>{{ $user->full_name ?: $user->name }}</p></div></div>
        <dl class="sp-email"><dt>Email address</dt><dd>{{ $user->email ?: 'Not provided' }}</dd></dl>
        <form method="POST" action="{{ route('seller.profile.update') }}">@csrf @method('PATCH')
            <div class="sp-grid">
                <div><label for="spFirstName">First name *</label><input id="spFirstName" name="first_name" required maxlength="100" autocomplete="given-name" value="{{ old('first_name',$user->first_name) }}" aria-invalid="{{ $errors->has('first_name') ? 'true' : 'false' }}">@error('first_name')<p class="sp-field-error">{{ $message }}</p>@enderror</div>
                <div><label for="spLastName">Last name *</label><input id="spLastName" name="last_name" required maxlength="100" autocomplete="family-name" value="{{ old('last_name',$user->last_name) }}" aria-invalid="{{ $errors->has('last_name') ? 'true' : 'false' }}">@error('last_name')<p class="sp-field-error">{{ $message }}</p>@enderror</div>
                <div><label for="spMiddleInitial">Middle initial</label><input id="spMiddleInitial" name="middle_initial" maxlength="4" value="{{ old('middle_initial',$user->middle_initial) }}">@error('middle_initial')<p class="sp-field-error">{{ $message }}</p>@enderror</div>
                <div><label for="spPhone">Phone number</label><input id="spPhone" name="phone" type="tel" maxlength="30" autocomplete="tel" value="{{ old('phone',$user->phone) }}" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}">@error('phone')<p class="sp-field-error">{{ $message }}</p>@enderror</div>
            </div>
            <button type="submit">Save Profile</button>
        </form>
    </section>
    <section class="sp-card" id="security" aria-labelledby="spSecurityHeading">
        <h3 id="spSecurityHeading">Account Security</h3>
        <form method="POST" action="{{ route('seller.store.password.update') }}">@csrf
            <div><label for="spCurrentPassword">Current password *</label><input id="spCurrentPassword" type="password" name="current_password" required autocomplete="current-password">@error('current_password')<p class="sp-field-error">{{ $message }}</p>@enderror</div>
            <div class="sp-grid">
                <div><label for="spNewPassword">New password *</label><input id="spNewPassword" type="password" name="password" minlength="8" required autocomplete="new-password">@error('password')<p class="sp-field-error">{{ $message }}</p>@enderror</div>
                <div><label for="spPasswordConfirmation">Confirm new password *</label><input id="spPasswordConfirmation" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password"></div>
            </div>
            <p class="sp-note">At least 8 characters and different from your current password.</p>
            <button type="submit">Update Password</button>
        </form>
    </section>
</div>
@endsection
