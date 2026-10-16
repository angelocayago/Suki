@extends('seller.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <div>
    <h1 class="h2 mb-1">Seller Orders</h1>
    <p class="text-muted mb-0">
      Manage orders and shipments belonging to your shop.
    </p>
  </div>
</div>

<div class="table-container">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Order #</th>
          <th>Customer</th>
          <th>My Items</th>
          <th>My Total</th>
          <th>Status</th>
          <th>Shipment</th>
          <th>Actions</th>
        </tr>
      </thead>

      <tbody>
        @forelse($orders as $order)
        @php
        // Controller loads only the authenticated shop's seller order.
        $sellerOrder = $order->sellerOrders->first();

        $sellerItems = $sellerOrder
        ? $sellerOrder->items
        : collect();

        $status = $sellerOrder?->status ?? 'pending';
        $shipment = $sellerOrder?->shipment;

        $statusColors = [
        'pending' => 'secondary',
        'placed' => 'secondary',
        'confirmed' => 'primary',
        'preparing' => 'info',
        'ready_for_pickup' => 'success',
        'cancelled' => 'danger',
        'picked_up' => 'warning',
        'in_transit' => 'primary',
        'delivered' => 'success',
        'completed' => 'success',
        ];

        $statusColor = $statusColors[$status] ?? 'secondary';

        $progressValues = [
        'pending' => 10,
        'placed' => 10,
        'confirmed' => 25,
        'preparing' => 45,
        'ready_for_pickup' => 60,
        'picked_up' => 75,
        'in_transit' => 85,
        'delivered' => 100,
        'completed' => 100,
        ];

        $progress = $progressValues[$status] ?? 0;
        @endphp

        @if($sellerOrder)
        <tr>
          <td>
            <div class="fw-semibold">
              {{ $order->order_number }}
            </div>
            <small class="text-muted">
              Seller Order #{{ $sellerOrder->id }}
            </small>
          </td>

          <td>
            {{ $order->user?->name ?? 'N/A' }}
          </td>

          <td>
            @forelse($sellerItems as $item)
            <div class="mb-1">
              <span class="fw-medium">
                {{ $item->product_name }}
              </span>

              <span class="text-muted">
                ×{{ $item->quantity }}
              </span>

              @if($item->size)
              <small class="text-muted d-block">
                Size: {{ $item->size->name }}
              </small>
              @endif
            </div>
            @empty
            <span class="text-muted">
              No items found
            </span>
            @endforelse
          </td>

          <td>
            <span class="fw-bold">
              &#8369;{{ number_format($sellerOrder->total_minor / 100, 2) }}
            </span>
          </td>

          <td>
            <span class="badge bg-{{ $statusColor }} mb-2">
              {{ ucwords(str_replace('_', ' ', $status)) }}
            </span>

            <div class="progress"
              style="height: 6px; min-width: 100px;">
              <div class="progress-bar"
                role="progressbar"
                style="width: {{ $progress }}%;"
                aria-valuenow="{{ $progress }}"
                aria-valuemin="0"
                aria-valuemax="100">
              </div>
            </div>

            @if(in_array($status, ['pending', 'placed', 'confirmed', 'preparing']))
            <div class="mt-2">

              @if(in_array($status, ['pending', 'placed']))
              <form method="POST"
                action="{{ route('seller.orders.updateStatus', $order) }}">
                @csrf
                <input type="hidden"
                  name="status"
                  value="confirmed">

                <button type="submit"
                  class="btn btn-sm btn-outline-primary">
                  Confirm Order
                </button>
              </form>

              @elseif($status === 'confirmed')
              <form method="POST"
                action="{{ route('seller.orders.updateStatus', $order) }}">
                @csrf
                <input type="hidden"
                  name="status"
                  value="preparing">

                <button type="submit"
                  class="btn btn-sm btn-outline-primary">
                  Start Preparing
                </button>
              </form>

              @elseif($status === 'preparing')
              <form method="POST"
                action="{{ route('seller.orders.ready', $order) }}">
                @csrf

                <button type="submit"
                  class="btn btn-sm btn-outline-success"
                  onclick="return confirm('Mark your seller order as ready for pickup?')">
                  Ready for Pickup
                </button>
              </form>
              @endif
            </div>
            @endif
          </td>

          <td>
            @if($shipment)
            <span class="badge bg-info text-dark">
              {{ ucwords(str_replace('_', ' ', $shipment->status)) }}
            </span>

            <small class="d-block text-muted mt-1">
              {{ $shipment->tracking_number }}
            </small>

            @if($shipment->logistic)
            <small class="d-block text-muted">
              {{ $shipment->logistic->company_name }}
            </small>
            @endif

            @if($shipment->rider_id)
            <small class="d-block text-success">
              Rider assigned
            </small>
            @else
            <small class="d-block text-muted">
              Waiting for rider
            </small>
            @endif
            @else
            <span class="badge bg-light text-dark border">
              No shipment yet
            </span>
            @endif
          </td>

          <td>
            <a href="{{ route('seller.orders.show', $order) }}"
              class="btn btn-sm btn-outline-primary">
              <i class="bi bi-eye"></i>
              View
            </a>
          </td>
        </tr>
        @endif

        @empty
        <tr>
          <td colspan="7"
            class="text-center text-muted py-4">
            No orders found for your shop.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">
    {{ $orders->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection