@extends('rider.layout')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
  <h1 class="h2">Cash Report</h1>
  <span class="text-muted">{{ now()->format('F d, Y') }}</span>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-success text-white me-3"><i class="bi bi-cash"></i></div>
        <div>
          <h6 class="text-muted mb-1">Today's Collection</h6>
          <h3 class="mb-0">&#8369;{{ number_format($cashReport->total_collected ?? 0, 2) }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-primary text-white me-3"><i class="bi bi-receipt"></i></div>
        <div>
          <h6 class="text-muted mb-1">Collections Recorded</h6>
          <h3 class="mb-0">{{ $cashReport->total_orders ?? 0 }}</h3>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-warning text-white me-3"><i class="bi bi-currency-dollar"></i></div>
        <div>
          <h6 class="text-muted mb-1">Average per Order</h6>
          <h3 class="mb-0">
            &#8369;{{ number_format(($cashReport->total_orders ?? 0) > 0 ? ($cashReport->total_collected ?? 0) / ($cashReport->total_orders ?? 1) : 0, 2) }}
          </h3>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="table-container">
  <h5 class="mb-3">Recent Collections</h5>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Order #</th>
          <th>Shipment</th>
          <th>Customer</th>
          <th>Amount</th>
          <th>Collected At</th>
        </tr>
      </thead>

      <tbody>
        @forelse($recentCollections as $collection)
        <tr>
          <td>{{ $collection->order_number }}</td>

          <td>
            @if($collection->shipment_number)
            <span class="badge bg-primary">
              {{ $collection->shipment_number }}
            </span>
            @else
            <span class="badge bg-secondary">Legacy COD</span>
            @endif
          </td>

          <td>{{ $collection->customer_name }}</td>

          <td class="text-success fw-bold">
            &#8369;{{ number_format($collection->amount_minor / 100, 2) }}
          </td>

          <td>
            {{ $collection->collected_at?->format('M d, Y H:i') ?? 'N/A' }}
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="5" class="text-center text-muted py-4">
            No cash collections yet.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection