@extends('admin.layout')

@section('content')
@php
$roleName = $user->roles()->value('name');
@endphp

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Review Application</h1>

  <a href="{{ route('admin.registrations.index') }}"
    class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left"></i> Back
  </a>
</div>

<div class="row g-4">
  <div class="col-md-7">

    {{-- Applicant Information --}}
    <div class="table-container mb-4">
      <h5 class="mb-3">Applicant Information</h5>

      <table class="table table-borderless">
        <tr>
          <th class="w-40">Name</th>
          <td>{{ $user->name }}</td>
        </tr>

        <tr>
          <th>Email</th>
          <td>{{ $user->email }}</td>
        </tr>

        <tr>
          <th>Mobile</th>
          <td>{{ $user->mobile_number ?? 'N/A' }}</td>
        </tr>

        <tr>
          <th>Role</th>
          <td>
            <span class="badge bg-info text-capitalize">
              {{ $roleName ?? 'N/A' }}
            </span>
          </td>
        </tr>

        <tr>
          <th>Sex</th>
          <td>{{ $user->sex ?? 'N/A' }}</td>
        </tr>

        <tr>
          <th>Birthday</th>
          <td>{{ $user->birthday ?? 'N/A' }}</td>
        </tr>

        <tr>
          <th>Address</th>
          <td>
            {{
                            collect([
                                trim(
                                    ($user->house_number ?? '') . ' ' .
                                    ($user->street_address ?? '')
                                ),
                                $user->barangay_name,
                                $user->municipality_name,
                                $user->province_name,
                                $user->region_name,
                            ])
                            ->filter(fn ($value) => filled($value))
                            ->implode(', ') ?: 'N/A'
                        }}
          </td>
        </tr>
      </table>
    </div>

    {{-- Seller Details --}}
    @if($roleName === 'seller')
    <div class="table-container mb-4">
      <h5 class="mb-3">Seller Details</h5>

      <table class="table table-borderless">
        <tr>
          <th class="w-40">Business Name</th>
          <td>{{ $user->business_name ?? 'N/A' }}</td>
        </tr>

        <tr>
          <th>Selling Categories</th>
          <td>
            @foreach($user->selling_categories ?? [] as $catId)
            <span class="badge bg-light text-dark">
              {{ \App\Models\Category::find($catId)?->name ?? $catId }}
            </span>
            @endforeach
          </td>
        </tr>
      </table>

      @if($user->business_permit)
      <a href="{{ route('admin.registrations.documents.show', [$user, 'business_permit']) }}"
        target="_blank"
        rel="noopener noreferrer"
        class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-file-earmark-pdf"></i>
        View Business Permit
      </a>
      @endif
    </div>
    @endif


    {{-- Logistics Details --}}
    @if($roleName === 'logistics')
    @php
    $logistic = \App\Models\Logistic::where('owner_user_id', $user->id)->first();
    @endphp

    <div class="table-container mb-4">
      <h5 class="mb-3">Logistics Company Details</h5>

      @if($logistic)
      <table class="table table-borderless">
        <tr>
          <th class="w-40">Company Name</th>
          <td>{{ $logistic->company_name ?? 'N/A' }}</td>
        </tr>
        <tr>
          <th>SEC Registration Number</th>
          <td>{{ $logistic->sec_registration_number ?? 'N/A' }}</td>
        </tr>
        <tr>
          <th>TIN</th>
          <td>{{ $logistic->tin ?? 'N/A' }}</td>
        </tr>
        <tr>
          <th>Region</th>
          <td>{{ $logistic->business_region ?? 'N/A' }}</td>
        </tr>
        <tr>
          <th>Province</th>
          <td>{{ $logistic->business_province ?? 'N/A' }}</td>
        </tr>
        <tr>
          <th>City / Municipality</th>
          <td>{{ $logistic->business_city ?? 'N/A' }}</td>
        </tr>
        <tr>
          <th>Barangay</th>
          <td>{{ $logistic->business_barangay ?? 'N/A' }}</td>
        </tr>
        <tr>
          <th>Street Address</th>
          <td>{{ $logistic->business_street ?? 'N/A' }}</td>
        </tr>
        <tr>
          <th>Postal Code</th>
          <td>{{ $logistic->business_postal_code ?? 'N/A' }}</td>
        </tr>
        <tr>
          <th>Contact Person</th>
          <td>{{ $logistic->contact_person ?? 'N/A' }}</td>
        </tr>
        <tr>
          <th>Position</th>
          <td>{{ $logistic->contact_position ?? 'N/A' }}</td>
        </tr>
        <tr>
          <th>Company Email</th>
          <td>{{ $logistic->email ?? 'N/A' }}</td>
        </tr>
        <tr>
          <th>Mobile Number</th>
          <td>{{ $logistic->phone ?? 'N/A' }}</td>
        </tr>
        <tr>
          <th>Service Areas</th>
          <td style="white-space: pre-line;">{{ $logistic->service_areas ?? 'N/A' }}</td>
        </tr>
        <tr>
          <th>Hub / API Address</th>
          <td>{{ $logistic->api_address ?: 'N/A' }}</td>
        </tr>
        <tr>
          <th>Status</th>
          <td>
            @php
            $statusColor = match ($logistic->status) {
            'active', 'approved' => 'success',
            'pending' => 'warning',
            'suspended', 'rejected' => 'danger',
            default => 'secondary',
            };
            @endphp
            <span class="badge bg-{{ $statusColor }}">
              {{ ucfirst($logistic->status ?? 'Unknown') }}
            </span>
          </td>
        </tr>
      </table>

      <h6 class="mt-4 mb-3">Company Verification Documents</h6>

      <div class="d-flex flex-wrap gap-2">
        @if($logistic->sec_certificate)
        <a href="{{ route('admin.registrations.documents.show', [$user, 'sec_certificate']) }}"
          target="_blank" rel="noopener noreferrer"
          class="btn btn-sm btn-outline-primary">
          <i class="bi bi-file-earmark-check"></i>
          View SEC Certificate
        </a>
        @endif

        @if($logistic->bir_form_2303)
        <a href="{{ route('admin.registrations.documents.show', [$user, 'bir_form_2303']) }}"
          target="_blank" rel="noopener noreferrer"
          class="btn btn-sm btn-outline-primary">
          <i class="bi bi-file-earmark-text"></i>
          View BIR Form 2303
        </a>
        @endif

        @if($logistic->business_permit)
        <a href="{{ route('admin.registrations.documents.show', [$user, 'logistic_permit']) }}"
          target="_blank" rel="noopener noreferrer"
          class="btn btn-sm btn-outline-primary">
          <i class="bi bi-file-earmark-pdf"></i>
          View Mayor's / Business Permit
        </a>
        @endif
      </div>

      <p class="text-muted small mt-3 mb-0">
        Authorized Representative Valid ID is available in the Identity Verification section below.
      </p>

      @if($logistic->logo_url)
      <img src="{{ $logistic->logo_url }}"
        alt="Company Logo"
        class="mt-3"
        style="max-height: 80px;">
      @endif
      @else
      <p class="text-muted">No logistics company record found.</p>
      @endif
    </div>
    @endif


    {{-- Rider Details --}}
    @if($roleName === 'rider')
    <div class="table-container mb-4">
      <h5 class="mb-3">Rider Details</h5>

      <table class="table table-borderless">
        <tr>
          <th class="w-40">Vehicle Type</th>
          <td>{{ $user->vehicle_type ?? 'N/A' }}</td>
        </tr>

        <tr>
          <th>License Number</th>
          <td>{{ $user->license_number ?? 'N/A' }}</td>
        </tr>
      </table>

      @if($user->or_document)
      <a href="{{ route('admin.registrations.documents.show', [$user, 'or_document']) }}"
        target="_blank"
        rel="noopener noreferrer"
        class="btn btn-sm btn-outline-secondary me-2">
        <i class="bi bi-file-earmark"></i>
        View OR
      </a>
      @endif

      @if($user->cr_document)
      <a href="{{ route('admin.registrations.documents.show', [$user, 'cr_document']) }}"
        target="_blank"
        rel="noopener noreferrer"
        class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-file-earmark"></i>
        View CR
      </a>
      @endif
    </div>
    @endif

    {{-- Identity Verification --}}
    <div class="table-container">
      <h5 class="mb-3">Identity Verification</h5>

      @if($user->id_verification)
      <a href="{{ route('admin.registrations.documents.show', [$user, 'id_verification']) }}"
        target="_blank"
        rel="noopener noreferrer"
        class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-file-earmark-person"></i>
        View ID Verification
      </a>
      @else
      <span class="text-muted">
        No document uploaded.
      </span>
      @endif
    </div>

  </div>

  {{-- Admin Decision --}}
  <div class="col-md-5">
    <div class="table-container">
      <h5 class="mb-3">Decision</h5>

      <form method="POST"
        action="{{ route('admin.registrations.approve', $user) }}">
        @csrf

        <button type="submit"
          class="btn btn-success w-100 mb-2"
          onclick="return confirm('Approve this application?')">
          <i class="bi bi-check-circle"></i>
          Approve Registration
        </button>
      </form>

      <form method="POST"
        action="{{ route('admin.registrations.reject', $user) }}"
        onsubmit="return confirm('Reject this application?');">
        @csrf

        <div class="mb-2">
          <label class="form-label">
            Rejection Reason
          </label>

          <textarea
            name="rejection_reason"
            class="form-control @error('rejection_reason') is-invalid @enderror"
            rows="3"
            required>{{ old('rejection_reason') }}</textarea>

          @error('rejection_reason')
          <div class="text-danger small">
            {{ $message }}
          </div>
          @enderror
        </div>

        <button type="submit"
          class="btn btn-danger w-100">
          <i class="bi bi-x-circle"></i>
          Reject Registration
        </button>
      </form>

      <small class="text-muted d-block mt-2">
        The applicant will be notified by email of your decision.
      </small>
    </div>
  </div>
</div>
@endsection