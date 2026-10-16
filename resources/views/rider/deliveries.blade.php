@extends('rider.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Delivery Notifications</h1>

  <div class="d-flex gap-2">
    <a href="{{ route('rider.pickups') }}" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-box-seam"></i> View Pickups
    </a>

    <a href="{{ route('rider.deliveries') }}" class="btn btn-sm btn-outline-secondary">
      Refresh
    </a>
  </div>
</div>

<div class="table-container">
  <h5 class="mb-3">My Assigned Shipments</h5>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Tracking #</th>
          <th>Order #</th>
          <th>Customer</th>
          <th>Address</th>
          <th>Contact</th>
          <th>Zone</th>
          <th>Ready</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
        @forelse($deliveries as $shipment)
        @php
        $order = $shipment->sellerOrder->order;

        $shippingData = is_array($order->shipping_address)
        ? $order->shipping_address
        : json_decode($order->shipping_address ?? '', true);

        $displayAddress = is_array($shippingData)
        ? ($shippingData['address']
        ?? implode(', ', array_filter([
        $shippingData['address_line1'] ?? null,
        $shippingData['address_line2'] ?? null,
        $shippingData['city'] ?? null,
        $shippingData['province'] ?? null,
        ])))
        : $order->shipping_address;

        $contactNumber = is_array($shippingData)
        ? ($shippingData['phone'] ?? $order->user?->phone)
        : $order->user?->phone;

        $ready = $shipment->received_by_sorting_center
        && $shipment->sorting_status === 'staged';
        @endphp

        <tr>
          <td>{{ $shipment->tracking_number ?? 'N/A' }}</td>
          <td>{{ $order->order_number }}</td>
          <td>{{ $order->user?->name ?? 'N/A' }}</td>

          <td>
            @if($displayAddress)
            <a
              href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($displayAddress) }}"
              target="_blank"
              rel="noopener noreferrer"
              class="text-decoration-none">
              {{ Str::limit($displayAddress, 40) }}
            </a>
            @else
            N/A
            @endif
          </td>

          <td>{{ $contactNumber ?: 'N/A' }}</td>

          <td>
            <span class="badge bg-info">
              {{ $shipment->delivery_zone ?? $order->delivery_zone ?? 'N/A' }}
            </span>
          </td>

          <td>
            @if($ready)
            <span class="badge bg-success">Staged</span>
            @else
            <span class="badge bg-secondary">Processing</span>
            @endif
          </td>

          <td>
            <span class="badge bg-{{
                                $shipment->status === 'out_for_delivery'
                                    ? 'primary'
                                    : ($shipment->status === 'assigned' ? 'info' : 'warning')
                            }}">
              {{ ucwords(str_replace('_', ' ', $shipment->status)) }}
            </span>
          </td>

          <td>
            <a
              href="{{ route('rider.shipments.show', $shipment) }}"
              class="btn btn-sm btn-primary">
              <i class="bi bi-eye"></i> View
            </a>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="9" class="text-center text-muted py-4">
            No assigned shipments at the moment.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">
    {{ $deliveries->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection