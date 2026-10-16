@extends('admin.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Shipment Details</h1>
  <a href="{{ route('admin.shipments.index') }}" class="btn btn-secondary btn-sm">Back to List</a>
</div>

<div class="row g-4">
  <div class="col-md-8">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Shipment Information</h5>
        <span class="badge bg-{{ $shipment->statusBadgeClass() }}">
          {{ ucfirst(str_replace('_', ' ', $shipment->status)) }}
        </span>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label text-muted">Tracking Number</label>
            <p class="fw-bold"><code>{{ $shipment->tracking_number }}</code></p>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted">Logistics Company</label>
            <p class="fw-bold">{{ $shipment->logistic?->company_name ?? 'N/A' }}</p>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted">Current Rider</label>
            <p class="fw-bold">{{ $shipment->rider?->name ?? 'Unassigned' }}</p>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted">Courier</label>
            <p class="fw-bold">{{ $shipment->courier ?? 'N/A' }}</p>
          </div>
          <div class="col-12">
            <label class="form-label text-muted">Pickup Address</label>
            <p class="fw-bold">{{ $shipment->pickup_address }}</p>
          </div>
          <div class="col-12">
            <label class="form-label text-muted">Delivery Address</label>
            <p class="fw-bold">{{ $shipment->delivery_address }}</p>
          </div>
          @if($shipment->notes)
          <div class="col-12">
            <label class="form-label text-muted">Notes</label>
            <p class="fw-bold">{{ $shipment->notes }}</p>
          </div>
          @endif
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <h5 class="mb-0">Shipment Status Monitoring</h5>
      </div>
      <div class="card-body">
        <p class="mb-2">Current status: <strong>{{ ucfirst(str_replace('_', ' ', $shipment->status)) }}</strong></p>
        <p class="text-muted mb-0">Status changes are handled through the logistics and rider workflows. Admin access is read-only on this page.</p>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white">
        <h5 class="mb-0">Rider Assignment Information</h5>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">Rider assignments are managed by the logistics company.</p>
        <div class="mb-3">
          <strong>Logistics Company</strong>
          <div>{{ $shipment->logistic?->company_name ?? 'Not assigned' }}</div>
        </div>
        <div class="mb-3">
          <strong>Pickup Rider</strong>
          <div>{{ $shipment->pickupRider?->name ?? 'Not assigned' }}</div>
        </div>
        <div class="mb-3">
          <strong>Current Rider</strong>
          <div>{{ $shipment->rider?->name ?? 'Not assigned' }}</div>
        </div>
        <div class="alert alert-info mb-0">
          Only the logistics company can assign pickup and delivery riders.
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <h5 class="mb-0">Timeline</h5>
      </div>
      <div class="card-body">
        @php
        $steps = [
        ['label' => 'Created', 'timestamp' => $shipment->created_at],
        ];
        if ($shipment->picked_up_at) {
        $steps[] = ['label' => 'Picked Up', 'timestamp' => $shipment->picked_up_at];
        }
        if ($shipment->received_at) {
        $steps[] = ['label' => 'Received at Sorting Center', 'timestamp' => $shipment->received_at];
        }
        if ($shipment->scanned_at) {
        $steps[] = ['label' => 'Scanned', 'timestamp' => $shipment->scanned_at];
        }
        if ($shipment->sorted_at) {
        $steps[] = ['label' => 'Sorted', 'timestamp' => $shipment->sorted_at];
        }
        if ($shipment->staged_at) {
        $steps[] = ['label' => 'Staged', 'timestamp' => $shipment->staged_at];
        }
        if ($shipment->delivered_at) {
        $steps[] = ['label' => 'Delivered', 'timestamp' => $shipment->delivered_at];
        }
        @endphp
        <div class="timeline">
          @foreach($steps as $step)
          <div class="timeline-item mb-3">
            <div class="timeline-content">
              <h6 class="mb-1">{{ $step['label'] }}</h6>
              <small class="text-muted">
                {{ $step['timestamp']?->format('M d, Y h:i A') ?? 'Pending' }}
              </small>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>
@endsection