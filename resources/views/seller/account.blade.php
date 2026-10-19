@extends('seller.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <div>
    <h1 class="h2 mb-1">Account Management</h1>
    <p class="text-muted mb-0">Update your seller profile, business details, and security settings.</p>
  </div>

  <a href="{{ route('seller.storefront', $user) }}" target="_blank" rel="noopener" class="btn btn-outline-primary">
    <i class="bi bi-shop me-1"></i> View My Shop
  </a>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

@if($errors->updateAccount->any())
  <div class="alert alert-danger">
    <strong>Please check the account form.</strong>
    <ul class="mb-0 mt-2">
      @foreach($errors->updateAccount->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

<div class="card shadow mb-4">
  <div class="card-body p-4">
    <form method="POST" action="{{ route('seller.account.update') }}" enctype="multipart/form-data" id="accountForm">
      @csrf
      @method('PUT')

      <h5 class="mb-3">Personal Information</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
          <input type="text" name="last_name" id="last_name" class="form-control @error('last_name', 'updateAccount') is-invalid @enderror" value="{{ old('last_name', $user->last_name) }}" required>
          @error('last_name', 'updateAccount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
          <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
          <input type="text" name="first_name" id="first_name" class="form-control @error('first_name', 'updateAccount') is-invalid @enderror" value="{{ old('first_name', $user->first_name) }}" required>
          @error('first_name', 'updateAccount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
          <label for="middle_name" class="form-label">Middle Initial / Middle Name</label>
          <input type="text" name="middle_name" id="middle_name" class="form-control @error('middle_name', 'updateAccount') is-invalid @enderror" value="{{ old('middle_name', $user->middle_name) }}">
          @error('middle_name', 'updateAccount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3">
          <label for="sex" class="form-label">Sex <span class="text-danger">*</span></label>
          <select name="sex" id="sex" class="form-select @error('sex', 'updateAccount') is-invalid @enderror" required>
            <option value="">Select Sex</option>
            <option value="Male" {{ old('sex', $user->sex) === 'Male' ? 'selected' : '' }}>Male</option>
            <option value="Female" {{ old('sex', $user->sex) === 'Female' ? 'selected' : '' }}>Female</option>
          </select>
          @error('sex', 'updateAccount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3">
          <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
          <input type="email" name="email" id="email" class="form-control @error('email', 'updateAccount') is-invalid @enderror" value="{{ old('email', $user->email) }}" autocomplete="email" required>
          @error('email', 'updateAccount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3">
          <label for="mobile_number" class="form-label">Contact No. <span class="text-danger">*</span></label>
          <input type="text" name="mobile_number" id="mobile_number" class="form-control @error('mobile_number', 'updateAccount') is-invalid @enderror" value="{{ old('mobile_number', $user->mobile_number) }}" required>
          @error('mobile_number', 'updateAccount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3">
          <label for="birthday" class="form-label">Birthday <span class="text-danger">*</span></label>
          <input type="date" name="birthday" id="birthday" class="form-control @error('birthday', 'updateAccount') is-invalid @enderror" value="{{ old('birthday', $user->birthday ? \Carbon\Carbon::parse($user->birthday)->format('Y-m-d') : '') }}" max="{{ now()->format('Y-m-d') }}" required>
          @error('birthday', 'updateAccount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-3">
          <label for="age" class="form-label">Age</label>
          <input type="number" id="age" class="form-control" value="{{ $user->birthday ? \Carbon\Carbon::parse($user->birthday)->age : $user->age }}" readonly>
          <small class="text-muted">Calculated automatically from birthday.</small>
        </div>
      </div>

      <h5 class="mb-3">Address</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label for="region" class="form-label">Region <span class="text-danger">*</span></label>
          <input type="hidden" name="region" id="region-hidden" value="{{ old('region', $user->region) }}">
          <select id="region" class="form-select" required>
            <option value="">Select Region</option>
          </select>
          @error('region', 'updateAccount') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
          <label for="province" class="form-label">Province/City <span class="text-danger">*</span></label>
          <input type="hidden" name="province" id="province-hidden" value="{{ old('province', $user->province) }}">
          <select id="province" class="form-select" required disabled>
            <option value="">Select Province/City</option>
          </select>
          @error('province', 'updateAccount') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4" id="municipality-field">
          <label for="municipality" class="form-label">Municipality <span class="text-danger">*</span></label>
          <input type="hidden" name="municipality" id="municipality-hidden" value="{{ old('municipality', $user->municipality) }}">
          <select id="municipality" class="form-select" required disabled>
            <option value="">Select Municipality</option>
          </select>
          @error('municipality', 'updateAccount') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
          <label for="barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
          <input type="hidden" name="barangay" id="barangay-hidden" value="{{ old('barangay', $user->barangay) }}">
          <select id="barangay" class="form-select" required disabled>
            <option value="">Select Barangay</option>
          </select>
          @error('barangay', 'updateAccount') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
          <label for="house_number" class="form-label">House Number</label>
          <input type="text" name="house_number" id="house_number" class="form-control @error('house_number', 'updateAccount') is-invalid @enderror" value="{{ old('house_number', $user->house_number) }}" placeholder="e.g. 123">
          @error('house_number', 'updateAccount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
          <label for="street_address" class="form-label">Street</label>
          <input type="text" name="street_address" id="street_address" class="form-control @error('street_address', 'updateAccount') is-invalid @enderror" value="{{ old('street_address', $user->street_address) }}" placeholder="e.g. Rizal Street">
          @error('street_address', 'updateAccount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
      </div>

      <h5 class="mb-3">Business Information</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label for="business_name" class="form-label">Business Name <span class="text-danger">*</span></label>
          <input type="text" name="business_name" id="business_name" class="form-control @error('business_name', 'updateAccount') is-invalid @enderror" value="{{ old('business_name', $user->business_name ?? $user->store_name) }}" required>
          @error('business_name', 'updateAccount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-6">
          <label for="logo" class="form-label">Shop Logo</label>
          <input type="file" name="logo" id="logo" class="form-control @error('logo', 'updateAccount') is-invalid @enderror" accept="image/*">
          <small class="text-muted">Recommended: 200x200px, max 2MB.</small>
          @error('logo', 'updateAccount') <div class="invalid-feedback">{{ $message }}</div> @enderror

          @if($user->logo_url)
            <div class="mt-2 d-flex align-items-center gap-3">
              <img src="{{ $user->logo_url }}" alt="{{ $user->business_name ?? 'Shop logo' }}" style="width:80px;height:80px;object-fit:cover;border-radius:8px;">
              <div>
                <small class="d-block text-muted mb-1">Current logo</small>
                <a href="{{ route('seller.storefront', $user) }}" target="_blank" rel="noopener" class="small text-decoration-none">Preview shop</a>
              </div>
            </div>
          @endif
        </div>

        <div class="col-md-12">
          <label for="preferred_logistic_id" class="form-label">Preferred Logistics Company</label>
          <select name="preferred_logistic_id" id="preferred_logistic_id" class="form-select @error('preferred_logistic_id', 'updateAccount') is-invalid @enderror">
            <option value="">Select Logistics Company</option>
            @foreach(\App\Models\Logistic::where('status', 'active')->orderBy('company_name')->get() as $logistic)
              <option value="{{ $logistic->id }}" {{ (string) old('preferred_logistic_id', $user->preferred_logistic_id) === (string) $logistic->id ? 'selected' : '' }}>
                {{ $logistic->company_name }}
              </option>
            @endforeach
          </select>
          <small class="text-muted">This logistics company will be used when you mark orders as ready for pickup.</small>
          @error('preferred_logistic_id', 'updateAccount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
      </div>

      <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-bili-hub">
          <i class="bi bi-save me-1"></i> Update Account
        </button>
        <a href="{{ route('seller.storefront', $user) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
          <i class="bi bi-shop me-1"></i> View My Shop
        </a>
      </div>
    </form>
  </div>
</div>

<div class="card shadow mb-4">
  <div class="card-body p-4">
    <h5 class="mb-1">Change Password</h5>
    <p class="text-muted small mb-3">Use your current password to create a new password.</p>

    @if(session('password_success'))
      <div class="alert alert-success alert-dismissible fade show">
        {{ session('password_success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    @if($errors->passwordUpdate->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach($errors->passwordUpdate->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('seller.account.password.update') }}" class="row g-3">
      @csrf
      @method('PUT')

      <div class="col-md-4">
        <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
        <input type="password" name="current_password" id="current_password" class="form-control @error('current_password', 'passwordUpdate') is-invalid @enderror" autocomplete="current-password" required>
        @error('current_password', 'passwordUpdate') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>

      <div class="col-md-4">
        <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
        <input type="password" name="password" id="new_password" class="form-control @error('password', 'passwordUpdate') is-invalid @enderror" minlength="8" autocomplete="new-password" required>
        @error('password', 'passwordUpdate') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>

      <div class="col-md-4">
        <label for="password_confirmation" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" minlength="8" autocomplete="new-password" required>
      </div>

      <div class="col-12">
        <button type="submit" class="btn btn-outline-primary">
          <i class="bi bi-shield-lock me-1"></i> Change Password
        </button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script type="application/json" id="seller-account-data">@php $sellerAccountData = [
  'saved' => [
    'region' => old('region', $user->region),
    'province' => old('province', $user->province),
    'municipality' => old('municipality', $user->municipality),
    'barangay' => old('barangay', $user->barangay),
  ],
]; @endphp @json($sellerAccountData)</script>
@vite('resources/js/seller/account.js')
@endpush
@endsection
