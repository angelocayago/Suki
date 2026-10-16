@extends('rider.layout')

@section('content')
<div class="container-fluid px-4 mx-auto" style="max-width: 1400px;">
  <div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2 mb-0">Rider Dashboard</h1>
    <div class="mb-3">
      <span class="badge {{ $rider->availability_status === 'available' ? 'bg-success' : 'bg-secondary' }}">
        {{ $rider->availability_status === 'available' ? 'Online - Available' : 'Offline' }}
      </span>
    </div>
    <div class="d-flex gap-2">
      <form method="POST" action="{{ route('rider.availability.update') }}">
        @csrf

        <input
          type="hidden"
          name="availability_status"
          value="{{ $rider->availability_status === 'available' ? 'offline' : 'available' }}">

        <button
          type="submit"
          class="btn btn-sm {{ $rider->availability_status === 'available' ? 'btn-outline-danger' : 'btn-success' }}">
          <i class="bi bi-power"></i>

          {{ $rider->availability_status === 'available' ? 'Go Offline' : 'Go Online' }}
        </button>
      </form>
      <a href="{{ route('rider.deliveries') }}" class="btn btn-bili-hub btn-sm">
        <i class="bi bi-bell"></i> Delivery Notifications
      </a>
      <a href="{{ route('rider.pickups') }}" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-box-seam"></i> View Pickups
      </a>
    </div>
  </div>

  @if($logistic)
  <div class="table-container mb-4">
    <h5 class="mb-3">My Logistics Company</h5>
    <div class="d-flex align-items-center">
      <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;">
        {{ substr($logistic->company_name, 0, 1) }}
      </div>
      <div>
        <h5 class="mb-1">{{ $logistic->company_name }}</h5>
        <p class="mb-0 text-muted small">
          @if($rider->logistic_status === 'approved')
          <span class="badge bg-success">Approved</span>
          Approved on {{ $rider->logistic_approved_at?->format('M d, Y') ?? '—' }}
          @elseif($rider->logistic_status === 'rejected')
          <span class="badge bg-danger">Rejected</span> Application declined
          @else
          <span class="badge bg-warning text-dark">Pending</span> Waiting for logistics approval
          @endif
        </p>
      </div>
    </div>
  </div>
  @endif

  <div class="row g-4 mb-4">
    <div class="col-md-4">
      <div class="card stat-card">
        <div class="card-body d-flex align-items-center">
          <div class="stat-icon bg-primary text-white me-3"><i class="bi bi-box"></i></div>
          <div>
            <h6 class="text-muted mb-1">Assigned Shipments</h6>
            <h3 class="mb-0">{{ $assignedOrders->total() }}</h3>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card">
        <div class="card-body d-flex align-items-center">
          <div class="stat-icon bg-success text-white me-3"><i class="bi bi-check-circle"></i></div>
          <div>
            <h6 class="text-muted mb-1">Completed Today</h6>
            <h3 class="mb-0">{{ $completedToday }}</h3>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card">
        <div class="card-body d-flex align-items-center">
          <div class="stat-icon bg-warning text-white me-3"><i class="bi bi-trophy"></i></div>
          <div>
            <h6 class="text-muted mb-1">Total Delivered</h6>
            <h3 class="mb-0">{{ $totalDelivered }}</h3>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-8">
      <div class="table-container">
        <h5 class="mb-3">Assigned Shipments</h5>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Order # / Tracking</th>
                <th>Customer</th>
                <th>Address</th>
                <th>Zone</th>
                <th>Ready</th>
                <th>Distance</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($assignedOrders as $shipment)
              @php
              $order = $shipment->sellerOrder?->order;
              $distance = null;

              if ($order && $order->customer_latitude !== null && $order->customer_longitude !== null
              && $rider->latitude !== null && $rider->longitude !== null) {
              $distance = $order->calculateDistance(
              $rider->latitude,
              $rider->longitude,
              $order->customer_latitude,
              $order->customer_longitude
              );
              }

              $shippingData = $order?->shipping_address;
              if (is_string($shippingData)) {
              $decoded = json_decode($shippingData, true);
              $shippingData = is_array($decoded) ? $decoded : $shippingData;
              }

              $displayAddress = is_array($shippingData)
              ? ($shippingData['address'] ?? implode(', ', array_filter([
              $shippingData['address_line1'] ?? null,
              $shippingData['address_line2'] ?? null,
              $shippingData['city'] ?? null,
              $shippingData['province'] ?? null,
              $shippingData['postal_code'] ?? null,
              $shippingData['country'] ?? null,
              ])))
              : ($shippingData ?? 'N/A');

              $shipmentStatus = $shipment->status ?? 'pending';
              $statusClass = match ($shipmentStatus) {
              'out_for_delivery', 'in_transit', 'picked_up' => 'primary',
              'at_sorting_center', 'staged', 'sorted' => 'info',
              'delivered' => 'success',
              'delivery_failed', 'cancelled' => 'danger',
              default => 'warning',
              };
              @endphp
              <tr>
                <td>
                  <div>{{ $order?->order_number ?? 'N/A' }}</div>
                  <small class="text-muted">{{ $shipment->tracking_number ?? 'Shipment #'.$shipment->id }}</small>
                </td>
                <td>{{ $order?->user?->name ?? 'N/A' }}</td>
                <td>
                  @if($displayAddress !== 'N/A')
                  <a href="https://www.google.com/maps/dir/?api=1&amp;destination={{ urlencode($displayAddress) }}"
                    target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                    {{ \Illuminate\Support\Str::limit($displayAddress, 30) }}
                  </a>
                  @else
                  N/A
                  @endif
                </td>
                <td><span class="badge bg-info">{{ $shipment->delivery_zone ?? $order?->delivery_zone ?? 'N/A' }}</span></td>
                <td>
                  @if($shipment->received_by_sorting_center && $shipment->sorting_status === 'staged')
                  <span class="badge bg-success">Ready</span>
                  @else
                  <span class="badge bg-secondary">Processing</span>
                  @endif
                </td>
                <td>{{ $distance !== null ? number_format($distance, 1).' km' : 'N/A' }}</td>
                <td>
                  <span class="badge bg-{{ $statusClass }}">
                    {{ ucwords(str_replace('_', ' ', $shipmentStatus)) }}
                  </span>
                </td>
                <td>
                  <a href="{{ route('rider.shipments.show', $shipment) }}" class="btn btn-sm btn-outline-primary">View</a>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="8" class="text-center text-muted py-4">No assigned shipments.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="mt-3">{{ $assignedOrders->links('pagination::bootstrap-5') }}</div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="table-container">
        <h5 class="mb-3">Delivery Route</h5>
        <div id="map"></div>
      </div>
    </div>
  </div>
</div>
@endsection

@php
$dashboardOrders = $assignedOrders->getCollection()->map(function ($shipment) {
$order = $shipment->sellerOrder?->order;
$shippingData = $order?->shipping_address;

if (is_string($shippingData)) {
$decoded = json_decode($shippingData, true);
$shippingData = is_array($decoded) ? $decoded : $shippingData;
}

$displayAddress = is_array($shippingData)
? ($shippingData['address'] ?? implode(', ', array_filter([
$shippingData['address_line1'] ?? null,
$shippingData['address_line2'] ?? null,
$shippingData['city'] ?? null,
$shippingData['province'] ?? null,
$shippingData['postal_code'] ?? null,
$shippingData['country'] ?? null,
])))
: ($shippingData ?? '');

return [
'address' => $displayAddress,
'customerName' => $order?->user?->name ?? 'Customer',
'latitude' => $order?->customer_latitude !== null ? (float) $order->customer_latitude : null,
'longitude' => $order?->customer_longitude !== null ? (float) $order->customer_longitude : null,
];
})->values();
@endphp

@push('scripts')
@vite('resources/js/rider/dashboard.js')
<script type="application/json" id="rider-dashboard-data">
  @json($dashboardOrders)
</script>
@endpush