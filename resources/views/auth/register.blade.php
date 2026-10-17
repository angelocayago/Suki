@extends('layouts.app')

@section('content')
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-5">
          <div class="text-center mb-4">
            <i class="bi bi-person-plus text-sky-500 fs-1"></i>
            <h2 class="fw-bold mt-2">Create Account</h2>
            <p class="text-muted">Join Bili Hub today</p>
          </div>

          @if($errors->any())
          <div class="alert alert-danger alert-dismissible fade show rounded-2 border-0" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i>
            <strong>Please correct the highlighted fields below.</strong>

            <button
              type="button"
              class="btn-close"
              data-bs-dismiss="alert"
              aria-label="Close">
            </button>
          </div>
          @endif

          <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" id="registerForm" novalidate>
            @csrf

            <h5 class="fw-semibold text-slate-800 mb-3">Personal Information</h5>
            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <label for="last_name" class="form-label fw-medium">Last Name <span class="text-danger">*</span></label>
                <input type="text" name="last_name" id="last_name" class="form-control rounded-xl" value="{{ old('last_name') }}" required autofocus>
                @error('last_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4">
                <label for="first_name" class="form-label fw-medium">First Name <span class="text-danger">*</span></label>
                <input type="text" name="first_name" id="first_name" class="form-control rounded-xl" value="{{ old('first_name') }}" required>
                @error('first_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4">
                <label for="middle_name" class="form-label fw-medium">Middle Initial</label>
                <input
                  type="text"
                  name="middle_name"
                  id="middle_name"
                  class="form-control rounded-xl"
                  value="{{ old('middle_name') }}"
                  maxlength="1"
                  pattern="[A-Za-zÃ‘Ã±]"
                  title="Middle initial must be one letter only."
                  autocomplete="additional-name">
                @error('middle_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="role" class="form-label fw-medium">Register As <span class="text-danger">*</span></label>
                <select name="role" id="role" class="form-select rounded-xl" required>
                  <option value="">Select Role</option>
                  <option value="buyer" {{ old('role') == 'buyer' ? 'selected' : '' }}>Buyer</option>
                  <option value="seller" {{ old('role') == 'seller' ? 'selected' : '' }}>Seller</option>
                  <option value="logistic" {{ old('role') == 'logistic' ? 'selected' : '' }}>Logistics Company</option>
                </select>
                @error('role') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="sex" class="form-label fw-medium">Sex <span class="text-danger">*</span></label>
                <select name="sex" id="sex" class="form-select rounded-xl" required>
                  <option value="">Select Sex</option>
                  <option value="Male" {{ old('sex') == 'Male' ? 'selected' : '' }}>Male</option>
                  <option value="Female" {{ old('sex') == 'Female' ? 'selected' : '' }}>Female</option>
                </select>
                @error('sex') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="email" class="form-label fw-medium">
                  E-mail <span class="text-danger">*</span>
                </label>

                <input
                  type="email"
                  name="email"
                  id="email"
                  class="form-control rounded-xl @error('email') is-invalid @enderror"
                  value="{{ old('email') }}"
                  required
                  aria-describedby="email-error">

                <div
                  id="email-error"
                  class="text-danger small mt-1"
                  role="alert"
                  aria-live="polite">@error('email'){{ $message }}@enderror</div>
              </div>
              <div class="col-md-3">
                <label for="mobile_number" class="form-label fw-medium">Contact No. <span class="text-danger">*</span></label>
                <input
                  type="text"
                  name="mobile_number"
                  id="mobile_number"
                  class="form-control rounded-xl"
                  value="{{ old('mobile_number') }}"
                  required
                  inputmode="tel"
                  maxlength="13"
                  pattern="09[0-9]{9}"
                  title="Please enter a valid Philippine mobile number (09XXXXXXXXX)">
                @error('mobile_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="birthday" class="form-label fw-medium">Birthday <span class="text-danger">*</span></label>
                <input
                  type="date"
                  name="birthday"
                  id="birthday"
                  class="form-control rounded-xl"
                  value="{{ old('birthday') }}"
                  max="{{ now()->subYears(18)->toDateString() }}"
                  required>
                @error('birthday') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-3">
                <label for="age" class="form-label fw-medium">Age <span class="text-danger">*</span></label>
                <input type="number" name="age" id="age" class="form-control rounded-xl" value="{{ old('age') }}" readonly required>
                @error('age') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
            </div>

            <h5 class="fw-semibold text-slate-800 mb-3">Account Security</h5>

            <div class="row g-3 mb-4">

              <!-- Password -->
              <div class="col-md-6">
                <label for="password" class="form-label fw-medium">
                  Password <span class="text-danger">*</span>
                </label>

                <div class="position-relative">
                  <input
                    type="password"
                    name="password"
                    id="password"
                    class="form-control rounded-xl pe-5 @error('password') is-invalid @enderror"
                    required
                    minlength="8"
                    aria-describedby="password-help password-error">

                  <button
                    type="button"
                    class="register-password-toggle"
                    data-password-target="password"
                    aria-label="Show password"
                    aria-pressed="false">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                  </button>
                </div>

                <div id="password-help" class="small mt-2">
                  <div id="password-rule-length" class="text-muted">&#9675; At least 8 characters</div>
                  <div id="password-rule-upper" class="text-muted">&#9675; At least 1 uppercase letter</div>
                  <div id="password-rule-lower" class="text-muted">&#9675; At least 1 lowercase letter</div>
                  <div id="password-rule-number" class="text-muted">&#9675; At least 1 number</div>
                  <div id="password-rule-symbol" class="text-muted">&#9675; At least 1 special character</div>
                </div>

                <div
                  id="password-error"
                  class="text-danger small mt-1"
                  role="alert"
                  aria-live="polite">@error('password'){{ $message }}@enderror</div>
              </div>

              <!-- Confirm Password -->
              <div class="col-md-6">
                <label for="password_confirmation" class="form-label fw-medium">
                  Confirm Password <span class="text-danger">*</span>
                </label>

                <div class="position-relative">
                  <input
                    type="password"
                    name="password_confirmation"
                    id="password_confirmation"
                    class="form-control rounded-xl pe-5 @error('password_confirmation') is-invalid @enderror"
                    required
                    aria-describedby="password-confirmation-error">

                  <button
                    type="button"
                    class="register-password-toggle"
                    data-password-target="password_confirmation"
                    aria-label="Show confirm password"
                    aria-pressed="false">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                  </button>
                </div>

                <div
                  id="password-confirmation-error"
                  class="text-danger small mt-1"
                  role="alert"
                  aria-live="polite">@error('password_confirmation'){{ $message }}@enderror</div>
              </div>

            </div>

            <h5 class="fw-semibold text-slate-800 mb-3">Address</h5>
            <div class="row g-3 mb-4">
              <input type="hidden" name="region_name" id="region_name">
              <input type="hidden" name="province_name" id="province_name">
              <input type="hidden" name="municipality_name" id="municipality_name">
              <input type="hidden" name="barangay_name" id="barangay_name">
              <div class="col-md-4">
                <label for="region" class="form-label fw-medium">Region <span class="text-danger">*</span></label>
                <select name="region" id="region" class="form-select rounded-xl" required>
                  <option value="">Select Region</option>
                </select>
                @error('region') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4">
                <label for="province" class="form-label fw-medium">Province/City <span class="text-danger">*</span></label>
                <select name="province" id="province" class="form-select rounded-xl" required disabled>
                  <option value="">Select Province/City</option>
                </select>
                @error('province') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4" id="municipality-field">
                <label for="municipality" class="form-label fw-medium">Municipality <span class="text-danger">*</span></label>
                <select name="municipality" id="municipality" class="form-select rounded-xl" required disabled>
                  <option value="">Select Municipality</option>
                </select>
                @error('municipality') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-4">
                <label for="barangay" class="form-label fw-medium">Barangay <span class="text-danger">*</span></label>
                <select name="barangay" id="barangay" class="form-select rounded-xl" required disabled>
                  <option value="">Select Barangay</option>
                </select>
                @error('barangay') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6">
                <label for="house_number" class="form-label fw-medium">House Number</label>
                <input type="text" name="house_number" id="house_number" class="form-control rounded-xl" value="{{ old('house_number') }}" placeholder="e.g. 123">
                @error('house_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6">
                <label for="street_address" class="form-label fw-medium">Street</label>
                <input type="text" name="street_address" id="street_address" class="form-control rounded-xl" value="{{ old('street_address') }}" placeholder="e.g. Rizal Street">
                @error('street_address') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
            </div>

            <h5 class="fw-semibold text-slate-800 mb-3 d-none" id="seller-heading">Seller Information</h5>
            <div class="row g-3 mb-4 d-none" id="seller-section">
                <div class="col-md-6">
                  <label for="seller_type" class="form-label fw-medium">
                    Seller Type <span class="text-danger">*</span>
                  </label>
                  <select name="seller_type" id="seller_type"
                          class="form-select rounded-xl" required>
                    <option value="">Select Seller Type</option>
                    <option value="individual" {{ old('seller_type') === 'individual' ? 'selected' : '' }}>Individual</option>
                    <option value="sole_proprietorship" {{ old('seller_type') === 'sole_proprietorship' ? 'selected' : '' }}>Sole Proprietorship</option>
                    <option value="partnership" {{ old('seller_type') === 'partnership' ? 'selected' : '' }}>Partnership</option>
                    <option value="corporation" {{ old('seller_type') === 'corporation' ? 'selected' : '' }}>Corporation</option>
                  </select>
                  @error('seller_type')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                  @enderror
                </div>
                              <div class="col-md-6">
                  <label for="seller_tin" class="form-label fw-medium">
                    Tax Identification Number (TIN) <span class="text-danger">*</span>
                  </label>
                  <input
                    type="text"
                    name="seller_tin"
                    id="seller_tin"
                    class="form-control rounded-xl"
                    value="{{ old('seller_tin') }}"
                    placeholder="123-456-789-000"
                    pattern="([0-9]{9}|[0-9]{12}|[0-9]{3}-[0-9]{3}-[0-9]{3}(-[0-9]{3})?)"
                    maxlength="15"
                    required
                  >
                  @error('seller_tin')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                  @enderror
                </div>
                                <div class="col-md-6 d-none" id="seller-dti-number-field">
                  <label for="dti_registration_number" class="form-label">
                    DTI Registration Number *
                  </label>
                  <input type="text"
                         name="dti_registration_number"
                         id="dti_registration_number"
                         class="form-control rounded-xl"
                         value="{{ old('dti_registration_number') }}"
                         minlength="5"
                         maxlength="100">
                  @error('dti_registration_number')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-md-6 d-none" id="seller-dti-certificate-field">
                  <label for="dti_certificate" class="form-label">
                    DTI Certificate *
                  </label>
                  <input type="file"
                         name="dti_certificate"
                         id="dti_certificate"
                         class="form-control rounded-xl"
                         accept=".pdf,.jpg,.jpeg,.png">
                  @error('dti_certificate')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-md-6 d-none" id="seller-sec-number-field">
                  <label for="seller_sec_registration_number" class="form-label">
                    SEC Registration Number *
                  </label>
                  <input type="text"
                         name="seller_sec_registration_number"
                         id="seller_sec_registration_number"
                         class="form-control rounded-xl"
                         value="{{ old('seller_sec_registration_number') }}"
                         minlength="5"
                         maxlength="100">
                  @error('seller_sec_registration_number')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-md-6 d-none" id="seller-sec-certificate-field">
                  <label for="seller_sec_certificate" class="form-label">
                    SEC Certificate *
                  </label>
                  <input type="file"
                         name="seller_sec_certificate"
                         id="seller_sec_certificate"
                         class="form-control rounded-xl"
                         accept=".pdf,.jpg,.jpeg,.png">
                  @error('seller_sec_certificate')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                  @enderror
                </div>
                                <div class="col-md-6 d-none" id="seller-bir-field">
                  <label for="seller_bir_form_2303" class="form-label">
                    BIR Form 2303 *
                  </label>
                  <input
                    type="file"
                    name="seller_bir_form_2303"
                    id="seller_bir_form_2303"
                    class="form-control rounded-xl"
                    accept=".pdf,.jpg,.jpeg,.png"
                  >
                  @error('seller_bir_form_2303')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                  @enderror
                </div>
                                <div class="col-12 mt-3">
                  <h6 class="fw-semibold">Business Pickup Address</h6>
                  <small class="text-muted">
                    This address will be used for seller order pickups.
                  </small>
                </div>

                <div class="col-md-6">
                  <label for="pickup_region_select" class="form-label">Region *</label>
                  <select id="pickup_region_select" class="form-select rounded-xl" required>
                    <option value="">Select Region</option>
                  </select>
                  <input type="hidden" name="pickup_region" id="pickup_region"
                         value="{{ old('pickup_region') }}">
                  @error('pickup_region')
                    <div class="text-danger small">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label for="pickup_province_select" class="form-label">Province *</label>
                  <select id="pickup_province_select" class="form-select rounded-xl" disabled required>
                    <option value="">Select Province / City</option>
                  </select>
                  <input type="hidden" name="pickup_province" id="pickup_province"
                         value="{{ old('pickup_province') }}">
                  @error('pickup_province')
                    <div class="text-danger small">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-md-6" id="pickup_city_field">
                  <label for="pickup_city_select" class="form-label">City / Municipality *</label>
                  <select id="pickup_city_select" class="form-select rounded-xl" disabled required>
                    <option value="">Select City / Municipality</option>
                  </select>
                  <input type="hidden" name="pickup_city" id="pickup_city"
                         value="{{ old('pickup_city') }}">
                  @error('pickup_city')
                    <div class="text-danger small">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-md-6">
                  <label for="pickup_barangay_select" class="form-label">Barangay *</label>
                  <select id="pickup_barangay_select" class="form-select rounded-xl" disabled required>
                    <option value="">Select Barangay</option>
                  </select>
                  <input type="hidden" name="pickup_barangay" id="pickup_barangay"
                         value="{{ old('pickup_barangay') }}">
                  @error('pickup_barangay')
                    <div class="text-danger small">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-md-8">
                  <label for="pickup_street" class="form-label">Street Address *</label>
                  <input type="text" name="pickup_street" id="pickup_street"
                         class="form-control rounded-xl"
                         value="{{ old('pickup_street') }}"
                         maxlength="255"
                         placeholder="House / Building No., Street"
                         required>
                  @error('pickup_street')
                    <div class="text-danger small">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-md-4">
                  <label for="pickup_postal_code" class="form-label">Postal Code *</label>
                  <input type="text" name="pickup_postal_code" id="pickup_postal_code"
                         class="form-control rounded-xl"
                         value="{{ old('pickup_postal_code') }}"
                         maxlength="4"
                         pattern="[0-9]{4}"
                         inputmode="numeric"
                         placeholder="e.g. 4009"
                         required>
                  @error('pickup_postal_code')
                    <div class="text-danger small">{{ $message }}</div>
                  @enderror
                </div>
                <div class="col-md-6">
                <label for="business_name" class="form-label fw-medium">Business Name <span class="text-danger">*</span></label>
                <input type="text" name="business_name" id="business_name" class="form-control rounded-xl" value="{{ old('business_name') }}" required>
                @error('business_name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6">
                <label for="selling_categories" class="form-label fw-medium">Line of Business <span class="text-danger">*</span></label>
                <select name="selling_categories[]" id="selling_categories" class="form-select rounded-xl" multiple size="5" required>
                  @foreach($categories ?? [] as $category)
                  <option value="{{ $category->id }}" {{ in_array($category->id, old('selling_categories', [])) ? 'selected' : '' }}>
                    {{ $category->name }}
                  </option>
                  @endforeach
                </select>
                <small class="text-muted">Hold Ctrl to select multiple categories</small>
                @error('selling_categories') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
            </div>


            <h5 class="fw-semibold text-slate-800 mb-3 d-none" id="logistic-heading">
              Logistics Company Information
            </h5>

            <div class="row g-3 mb-4 d-none" id="logistic-section">

              <div class="col-12">
                <h6 class="fw-semibold">Company Registration</h6>
              </div>

              <div class="col-md-6">
                <label for="company_name" class="form-label">
                  Company Name *
                </label>

                <input
                  type="text"
                  name="company_name"
                  id="company_name"
                  class="form-control rounded-xl"
                  value="{{ old('company_name') }}"
                  minlength="2"
                  maxlength="255"
                  placeholder="e.g. Speedy Express Logistics Inc."
                  required>

                @error('company_name')
                <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6">
                <label for="sec_registration_number" class="form-label">
                  SEC Registration Number *
                </label>

                <input
                  type="text"
                  name="sec_registration_number"
                  id="sec_registration_number"
                  class="form-control rounded-xl"
                  value="{{ old('sec_registration_number') }}"
                  minlength="5"
                  maxlength="100"
                  pattern="[A-Za-z0-9]+([\x2D ][A-Za-z0-9]+)*"
                  title="Enter 5–100 characters using letters, numbers, hyphens, or spaces."
                  placeholder="e.g. CS202012345"
                  required>

                @error('sec_registration_number')
                <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-6">
                <label for="tin" class="form-label">TIN *</label>

                <input
                  type="text"
                  name="tin"
                  id="tin"
                  class="form-control rounded-xl"
                  value="{{ old('tin') }}"
                  maxlength="15"
                  pattern="([0-9]{9}|[0-9]{12}|[0-9]{3}-[0-9]{3}-[0-9]{3}(-[0-9]{3})?)"
                  title="Enter a valid 9 or 12-digit TIN, with or without hyphens."
                  inputmode="numeric"
                  placeholder="123-456-789-000"
                  required>

                @error('tin')
                <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-12 mt-4">
                <h6 class="fw-semibold">Business Address</h6>
              </div>

              <div class="col-md-6">
                <label for="business_region_select" class="form-label">Region *</label>
                <select id="business_region_select" class="form-select rounded-xl" required>
                  <option value="">Select Region</option>
                </select>
                <input type="hidden" name="business_region" id="business_region"
                  value="{{ old('business_region') }}">
                @error('business_region') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label for="business_province_select" class="form-label">Province *</label>
                <select id="business_province_select" class="form-select rounded-xl" disabled required>
                  <option value="">Select Province / City</option>
                </select>
                <input type="hidden" name="business_province" id="business_province"
                  value="{{ old('business_province') }}">
                @error('business_province') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6" id="business_city_field">
                <label for="business_city_select" class="form-label">City / Municipality *</label>
                <select id="business_city_select" class="form-select rounded-xl" disabled required>
                  <option value="">Select City / Municipality</option>
                </select>
                <input type="hidden" name="business_city" id="business_city"
                  value="{{ old('business_city') }}">
                @error('business_city') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label for="business_barangay_select" class="form-label">Barangay *</label>
                <select id="business_barangay_select" class="form-select rounded-xl" disabled required>
                  <option value="">Select Barangay</option>
                </select>
                <input type="hidden" name="business_barangay" id="business_barangay"
                  value="{{ old('business_barangay') }}">
                @error('business_barangay') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-8">
                <label for="business_street" class="form-label">Street Address *</label>
                <input
                  type="text"
                  name="business_street"
                  id="business_street"
                  class="form-control rounded-xl"
                  value="{{ old('business_street') }}"
                  maxlength="255"
                  required>
                @error('business_street')
                <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-md-4">
                <label for="business_postal_code" class="form-label">Postal Code *</label>
                <input
                  type="text"
                  name="business_postal_code"
                  id="business_postal_code"
                  class="form-control rounded-xl"
                  value="{{ old('business_postal_code') }}"
                  maxlength="4"
                  pattern="[0-9]{4}"
                  inputmode="numeric"
                  title="Enter a valid 4-digit Philippine postal code."
                  placeholder="e.g. 4009"
                  required>
                @error('business_postal_code')
                <div class="text-danger small">{{ $message }}</div>
                @enderror
              </div>

              <div class="col-12 mt-4">
                <h6 class="fw-semibold">Authorized Contact Person</h6>
              </div>

              <div class="col-md-4">
                <label for="contact_first_name" class="form-label">First Name *</label>
                <input type="text" name="contact_first_name" id="contact_first_name"
                  class="form-control rounded-xl" value="{{ old('contact_first_name') }}" required minlength="2" maxlength="100" pattern="[\p{L}]+(?:[ '\-][\p{L}]+)*">
                @error('contact_first_name') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-4">
                <label for="contact_last_name" class="form-label">Last Name *</label>
                <input type="text" name="contact_last_name" id="contact_last_name"
                  class="form-control rounded-xl" value="{{ old('contact_last_name') }}" required minlength="2" maxlength="100" pattern="[\p{L}]+(?:[ '\-][\p{L}]+)*">
                @error('contact_last_name') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-4">
                <label for="contact_position" class="form-label">Position *</label>
                <input type="text" name="contact_position" id="contact_position"
                  class="form-control rounded-xl"
                  value="{{ old('contact_position') }}"
                  placeholder="e.g. Operations Manager" required minlength="2" maxlength="100">
                @error('contact_position') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label for="company_email" class="form-label">Company Email *</label>
                <input type="email" name="company_email" id="company_email"
                  class="form-control rounded-xl" value="{{ old('company_email') }}" required maxlength="255">
                @error('company_email') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label for="company_phone" class="form-label">Company Mobile Number *</label>
                <input type="tel" name="company_phone" id="company_phone"
                  class="form-control rounded-xl" value="{{ old('company_phone') }}"
                  placeholder="09XXXXXXXXX" required minlength="11" maxlength="11" pattern="09[0-9]{9}" inputmode="numeric">
                @error('company_phone') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-12 mt-4">
                <h6 class="fw-semibold">Operations</h6>
              </div>

              <div class="col-12">
                <label for="service_areas" class="form-label">Service Areas *</label>
                <textarea name="service_areas" id="service_areas"
                  class="form-control rounded-xl" rows="2"
                  placeholder="e.g. Santa Cruz, Pagsanjan, Calamba, Laguna" required>{{ old('service_areas') }}</textarea>
                @error('service_areas') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-12">
                <label for="api_address" class="form-label">Hub API Address / Location</label>
                <input type="text" name="api_address" id="api_address"
                  class="form-control rounded-xl"
                  value="{{ old('api_address') }}"
                  placeholder="Full hub location for geocoding">
                <small class="text-muted">
                  Used to locate your logistics hub for delivery assignment.
                </small>
                @error('api_address') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-12 mt-4">
                <h6 class="fw-semibold">Additional Logistics Documents</h6>
                <small class="text-muted">Accepted: PDF, JPG, JPEG, PNG. Maximum 5 MB per file.</small>
              </div>

              <div class="col-md-6">
                <label for="sec_certificate" class="form-label">SEC Certificate *</label>
                <input type="file" name="sec_certificate" id="sec_certificate"
                  class="form-control rounded-xl" accept=".pdf,.jpg,.jpeg,.png" required>
                @error('sec_certificate') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

              <div class="col-md-6">
                <label for="bir_form_2303" class="form-label">BIR Form 2303 *</label>
                <input type="file" name="bir_form_2303" id="bir_form_2303"
                  class="form-control rounded-xl" accept=".pdf,.jpg,.jpeg,.png" required>
                @error('bir_form_2303') <div class="text-danger small">{{ $message }}</div> @enderror
              </div>

            </div>


            <h5 class="fw-semibold text-slate-800 mb-3">Documents</h5>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label for="id_verification" class="form-label fw-medium">Upload ID <span class="text-danger">*</span></label>
                <input type="file" name="id_verification" id="id_verification" class="form-control rounded-xl" accept=".pdf,.jpg,.jpeg,.png" required>
                @error('id_verification') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6 d-none" id="business-permit-field">
                <label for="business_permit" class="form-label fw-medium">Upload Business Permit <span class="text-danger">*</span></label>
                <input type="file" name="business_permit" id="business_permit" class="form-control rounded-xl" accept=".pdf,.jpg,.jpeg,.png">
                @error('business_permit') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
              </div>
            </div>

            <div id="buyerTermsSection" class="mb-3" style="{{ old('role') === 'buyer' ? '' : 'display: none;' }}">
              <div class="form-check">
                <input
                  type="checkbox"
                  name="terms"
                  id="terms"
                  value="1"
                  class="form-check-input @error('terms') is-invalid @enderror"
                  {{ old('terms') ? 'checked' : '' }}
                  required
                  oninvalid="this.setCustomValidity('You must accept the Terms and Privacy Policy to register.')"
                  onchange="this.setCustomValidity('')">

                <label class="form-check-label" for="terms">
                  I agree to the
                  <strong>Terms and Conditions</strong>
                  and
                  <strong>Privacy Policy</strong>.
                  <span class="text-danger">*</span>
                </label>
              </div>

              @error('terms')
              <div class="text-danger small mt-1">
                You must accept the Terms and Privacy Policy to register.
              </div>
              @enderror
            </div>

            <div class="alert alert-info rounded-2 border-0">
              <i class="bi bi-info-circle me-1"></i> After submitting your registration, please wait for the administrator's approval, which will be sent to your email.
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-3 rounded-xl py-2.5">Register</button>
          </form>

          <p class="text-center mt-3 mb-0">
            Already have an account? <a href="{{ route('login') }}" class="text-sky-500 fw-medium">Login</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<script type="application/json" id="auth-page-data">
  @php $authPageData = [
    'region' => old('region'),
    'province' => old('province'),
    'municipality' => old('municipality'),
    'barangay' => old('barangay'),
    'geocodeKey' => config('services.googlemaps.key'),
  ];
  @endphp @json($authPageData)
</script>
@endsection

@push('styles')
@vite('resources/css/auth/register.css')
@endpush

@push('scripts')
@vite('resources/js/auth/register.js')
@endpush