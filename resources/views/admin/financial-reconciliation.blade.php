@extends('admin.layout')

@section('title', 'Financial Reconciliation')

@section('content')
<div class="container-fluid py-3">

    {{-- PAGE HEADER --}}
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">Financial Reconciliation</h2>
            <p class="text-muted mb-0">
                Monitor payment collections, COD remittances, and platform commissions.
            </p>
        </div>

        <span class="badge bg-primary fs-6 px-3 py-2">
            <i class="bi bi-shield-check me-1"></i>
            Read-Only Financial Overview
        </span>
    </div>

    {{-- SUMMARY CARDS --}}
    <div class="row g-3 mb-4">

        <div class="col-md-6 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Total Collected Payments</p>
                    <h3 class="fw-bold mb-1">
                        &#8369;{{ number_format($totalCollectedMinor / 100, 2) }}
                    </h3>
                    <small class="text-muted">Paid payment records</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Confirmed COD Remittances</p>
                    <h3 class="fw-bold text-success mb-1">
                        &#8369;{{ number_format($confirmedCodMinor / 100, 2) }}
                    </h3>
                    <small class="text-muted">Paid payments linked to confirmed remittances</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Recorded Platform Commissions</p>
                    <h3 class="fw-bold mb-1">
                        &#8369;{{ number_format($totalCommissionMinor / 100, 2) }}
                    </h3>
                    <small class="text-muted">Commission records, not cash collected</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Pending Commissions</p>
                    <h3 class="fw-bold text-warning mb-1">
                        &#8369;{{ number_format($pendingCommissionMinor / 100, 2) }}
                    </h3>
                    <small class="text-muted">Commission records marked pending</small>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Paid-Status Commissions</p>
                    <h3 class="fw-bold text-success mb-1">
                        &#8369;{{ number_format($paidCommissionMinor / 100, 2) }}
                    </h3>
                    <small class="text-muted">Marked paid; not proof of a bank transfer</small>
                </div>
            </div>
        </div>

    </div>


    {{-- ORDER-LEVEL FINANCIAL BREAKDOWN --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-1">
                <i class="bi bi-receipt-cutoff me-2"></i>
                Order-Level Financial Breakdown
            </h5>
            <small class="text-muted">
                Historical amounts are displayed exactly as recorded.
                Seller net is before other adjustments and is not a settled payout.
            </small>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order</th>
                            <th>Buyer</th>
                            <th>Subtotal</th>
                            <th>Buyer Fee</th>
                            <th>Shipping</th>
                            <th>Buyer Total</th>
                            <th>Seller Commission</th>
                            <th>Seller Net*</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($recentOrders as $order)
                        @php
                        $subtotalMinor = (int) $order->subtotal_minor;
                        $feeMinor = (int) $order->tax_minor;
                        $shippingMinor = (int) $order->shipping_minor;
                        $totalMinor = (int) $order->total_minor;

                        $commissionMinor = (int) (
                        $commissionsByOrder[$order->id] ?? 0
                        );

                        $sellerNetMinor = $subtotalMinor - $commissionMinor;
                        @endphp

                        <tr>
                            <td class="fw-semibold">
                                #{{ $order->id }}
                            </td>

                            <td>
                                {{ $order->user?->name ?? 'N/A' }}
                            </td>

                            <td>
                                &#8369;{{ number_format($subtotalMinor / 100, 2) }}
                            </td>

                            <td>
                                &#8369;{{ number_format($feeMinor / 100, 2) }}
                            </td>

                            <td>
                                &#8369;{{ number_format($shippingMinor / 100, 2) }}
                            </td>

                            <td class="fw-bold">
                                &#8369;{{ number_format($totalMinor / 100, 2) }}
                            </td>

                            <td>
                                &#8369;{{ number_format($commissionMinor / 100, 2) }}
                            </td>

                            <td class="fw-semibold">
                                @if(in_array(strtolower($order->status), ['delivered', 'completed'])
                                && $commissionMinor > 0)
                                <span class="text-success">
                                    &#8369;{{ number_format($sellerNetMinor / 100, 2) }}
                                </span>
                                @else
                                <span class="text-muted">Not Available</span>
                                @endif
                            </td>

                            <td>
                                <span class="badge bg-secondary">
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No orders found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-white text-muted small">
            * Seller Net = Product Subtotal - Recorded Commission.
            This is an estimate before refunds, adjustments, and settlements.
            Orders without generated commissions may show an incomplete net estimate.
        </div>
    </div>


    {{-- RECENT COD REMITTANCES --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0">
                <i class="bi bi-cash-coin me-2"></i>
                Recent COD Remittances
            </h5>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Rider</th>
                            <th>Logistics</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentRemittances as $remittance)
                        <tr>
                            <td class="fw-semibold">
                                {{ $remittance->reference ?? '#'.$remittance->id }}
                            </td>
                            <td>{{ $remittance->rider?->name ?? 'N/A' }}</td>
                            <td>{{ $remittance->logistic?->company_name ?? 'N/A' }}</td>
                            <td>
                                &#8369;{{ number_format($remittance->total_minor / 100, 2) }}
                            </td>
                            <td>
                                @php
                                $status = strtolower($remittance->status ?? 'unknown');
                                $color = match($status) {
                                'confirmed' => 'success',
                                'rejected' => 'danger',
                                'pending', 'submitted' => 'warning',
                                default => 'secondary'
                                };
                                @endphp

                                <span class="badge bg-{{ $color }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>
                            <td>
                                {{ $remittance->submitted_at?->format('M d, Y h:i A') ?? '—' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No COD remittances found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- RECENT PAYMENTS --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0">
                <i class="bi bi-credit-card me-2"></i>
                Recent Payments
            </h5>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Payment ID</th>
                            <th>Order</th>
                            <th>Shipment</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentPayments as $payment)
                        <tr>
                            <td>#{{ $payment->id }}</td>
                            <td>
                                {{ $payment->order_id ? '#'.$payment->order_id : '—' }}
                            </td>
                            <td>
                                {{ $payment->shipment_id ? '#'.$payment->shipment_id : '—' }}
                            </td>
                            <td>{{ strtoupper($payment->method ?? 'N/A') }}</td>
                            <td>
                                &#8369;{{ number_format($payment->amount_minor / 100, 2) }}
                            </td>
                            <td>
                                @php
                                $paymentStatus = strtolower($payment->status ?? 'unknown');
                                $paymentColor = match($paymentStatus) {
                                'paid' => 'success',
                                'pending' => 'warning',
                                'failed', 'cancelled' => 'danger',
                                default => 'secondary'
                                };
                                @endphp

                                <span class="badge bg-{{ $paymentColor }}">
                                    {{ ucfirst($paymentStatus) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No payment records found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        This dashboard is read-only. Confirmed COD remittances do not
        automatically mean seller or logistics payouts have been settled.
    </div>

</div>
@endsection