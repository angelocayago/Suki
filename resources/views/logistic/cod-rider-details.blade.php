@extends('layouts.logistic')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('logistic.cod-reconciliation.index') }}"
                class="text-decoration-none small">
                &larr; Back to Rider Cash Balances
            </a>

            <h2 class="fw-bold mt-2 mb-1">
                {{ $riderInfo->name }}
            </h2>

            <p class="text-muted mb-0">
                Individual COD Payment Details
            </p>
        </div>
    </div>

    @php
    $cards = [
    ['label' => 'Total Collected', 'key' => 'collected_minor', 'color' => 'primary'],
    ['label' => 'Unsubmitted', 'key' => 'unsubmitted_minor', 'color' => 'warning'],
    ['label' => 'Pending Verification', 'key' => 'pending_minor', 'color' => 'info'],
    ['label' => 'Confirmed Received', 'key' => 'confirmed_minor', 'color' => 'success'],
    ];
    @endphp

    <div class="row g-3 mb-4">
        @foreach ($cards as $card)
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-2">
                        {{ $card['label'] }}
                    </div>
                    <h4 class="fw-bold text-{{ $card['color'] }} mb-0">
                        ₱{{ number_format($totals->{$card['key']} / 100, 2) }}
                    </h4>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="fw-bold mb-1">COD Payment History</h5>
                    <div class="small text-muted">
                        {{ $totals->payment_count }} eligible payments
                    </div>
                </div>

                <form method="GET">
                    <select name="status"
                        class="form-select"
                        onchange="this.form.submit()">
                        <option value="all" @selected($filter==='all' )>
                            All Payments
                        </option>
                        <option value="unsubmitted" @selected($filter==='unsubmitted' )>
                            Unsubmitted
                        </option>
                        <option value="pending" @selected($filter==='pending' )>
                            Pending Verification
                        </option>
                        <option value="confirmed" @selected($filter==='confirmed' )>
                            Confirmed
                        </option>
                    </select>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Payment ID</th>
                        <th>Shipment</th>
                        <th class="text-end">Collected Amount</th>
                        <th>Remittance Status</th>
                        <th>Date Collected</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($payments as $payment)
                    @php
                    $status = match ((int) $payment->status_rank) {
                    1 => 'Pending Verification',
                    2 => 'Confirmed',
                    default => 'Unsubmitted',
                    };

                    $badge = match ((int) $payment->status_rank) {
                    1 => 'info',
                    2 => 'success',
                    default => 'warning',
                    };
                    @endphp

                    <tr>
                        <td class="fw-semibold">
                            #{{ $payment->payment_id }}
                        </td>

                        <td>
                            #{{ $payment->shipment_id }}
                        </td>

                        <td class="text-end fw-semibold">
                            ₱{{ number_format($payment->amount_minor / 100, 2) }}
                        </td>

                        <td>
                            <span class="badge bg-{{ $badge }}">
                                {{ $status }}
                            </span>
                        </td>

                        <td>
                            {{ \Illuminate\Support\Carbon::parse($payment->paid_at)->format('M d, Y h:i A') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5"
                            class="text-center text-muted py-5">
                            No COD payments found for this filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($payments->hasPages())
        <div class="card-footer bg-white py-3">
            {{ $payments->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>

    <p class="small text-muted mt-3">
        Only eligible delivered COD collections are displayed.
        Rejected remittances are treated as unsubmitted unless
        another active remittance exists.
    </p>
</div>
@endsection