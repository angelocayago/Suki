@extends('layouts.logistic', ['title' => 'COD Cash Remittances', 'logistic' => $logistic])

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <div class="text-uppercase text-muted small fw-semibold mb-1">SPEEDY EXPRESS / FINANCE</div>
            <h2 class="fw-bold mb-1">COD Cash Remittances</h2>
            <p class="text-muted mb-0">Review rider cash submissions and verify actual cash received.</p>
        </div>
        <span class="badge bg-light text-dark border px-3 py-2">
            <i class="bi bi-shield-check me-1"></i> Logistics Owner Verification
        </span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="text-muted small mb-2">Pending Cash</div>
                    <h3 class="fw-bold text-warning">₱{{ number_format($stats['pending_total_minor'] / 100, 2) }}</h3>
                    <div class="text-muted small">{{ $stats['pending_count'] }} pending in total</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="text-muted small mb-2">Confirmed Cash</div>
                    <h3 class="fw-bold text-success">₱{{ number_format($stats['confirmed_total_minor'] / 100, 2) }}</h3>
                    <div class="text-muted small">{{ $stats['confirmed_count'] }} confirmed in total</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="text-muted small mb-2">Rejected Remittances</div>
                    <h3 class="fw-bold text-danger">{{ $stats['rejected_count'] }}</h3>
                    <div class="text-muted small">Across all remittances</div>
                </div>
            </div>
        </div>
    </div>

    <div id="codFeedback" class="alert d-none" role="alert"></div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white p-4">
            <h5 class="fw-bold mb-1">Rider Cash Submissions</h5>
            <p class="text-muted small mb-0">Confirm only after receiving and counting the actual cash.</p>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Reference</th>
                        <th>Rider</th>
                        <th>Payments</th>
                        <th>Amount</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($remittances as $remittance)
                    <tr>
                        <td class="ps-4">
                            <div class="fw-semibold text-break" title="{{ $remittance->reference }}">
                                {{ $remittance->reference }}
                            </div>
                            @if($remittance->notes)
                            <div class="text-muted small">{{ $remittance->notes }}</div>
                            @endif
                        </td>
                        <td>{{ $remittance->rider?->name ?? 'Unknown Rider' }}</td>
                        <td>{{ $remittance->payments->count() }}</td>
                        <td class="fw-bold">₱{{ number_format($remittance->total_minor / 100, 2) }}</td>
                        <td class="text-muted small">
                            {{ $remittance->submitted_at?->format('M d, Y h:i A') ?? '—' }}
                        </td>
                        <td>
                            @php
                            $color = match ($remittance->status) {
                            'confirmed' => 'success',
                            'rejected' => 'danger',
                            default => 'warning',
                            };
                            @endphp
                            <span class="badge bg-{{ $color }}">{{ ucfirst($remittance->status) }}</span>
                            @if($remittance->status === 'rejected' && $remittance->rejection_reason)
                            <div class="text-danger small mt-2">{{ $remittance->rejection_reason }}</div>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-flex gap-2 justify-content-end flex-wrap">
                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#remittanceDetails{{ $remittance->id }}">
                                    <i class="bi bi-eye me-1"></i> View Details
                                </button>

                                @if($remittance->status === 'pending')
                                <button
                                    type="button"
                                    class="btn btn-success btn-sm cod-confirm"
                                    data-url="{{ route('logistic.cod-remittances.confirm', $remittance) }}"
                                    data-reference="{{ $remittance->reference }}"
                                    data-amount="{{ number_format($remittance->total_minor / 100, 2) }}">
                                    <i class="bi bi-check-circle me-1"></i> Confirm Received
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-outline-danger btn-sm cod-reject"
                                    data-url="{{ route('logistic.cod-remittances.reject', $remittance) }}"
                                    data-reference="{{ $remittance->reference }}">
                                    Reject
                                </button>
                                @else
                                <span class="text-muted small align-self-center">Processed</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            No COD remittances received yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($remittances->hasPages())
        <div class="card-footer bg-white">
            {{ $remittances->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>

{{-- Keep Bootstrap modals outside the table and its responsive scrolling container. --}}
@foreach($remittances as $remittance)
<div
    class="modal fade"
    id="remittanceDetails{{ $remittance->id }}"
    tabindex="-1"
    aria-labelledby="remittanceTitle{{ $remittance->id }}"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold" id="remittanceTitle{{ $remittance->id }}">
                        COD Remittance Details
                    </h5>
                    <small class="text-muted">Verify the payment breakdown before confirming cash.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="text-muted small">Reference Number</div>
                        <div class="fw-semibold text-break">{{ $remittance->reference }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Rider</div>
                        <div class="fw-semibold">{{ $remittance->rider?->name ?? 'Unknown Rider' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Submitted At</div>
                        <div class="fw-semibold">
                            {{ $remittance->submitted_at?->format('M d, Y h:i A') ?? '—' }}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Status</div>
                        <div class="fw-semibold">{{ ucfirst($remittance->status) }}</div>
                    </div>
                </div>

                <h6 class="fw-bold mb-3">Payment Breakdown</h6>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Payment ID</th>
                                <th>Shipment ID</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($remittance->payments as $item)
                            <tr>
                                <td>#{{ $item->payment_id }}</td>
                                <td>
                                    {{ $item->payment?->shipment_id ? '#'.$item->payment->shipment_id : '—' }}
                                </td>
                                <td class="text-end">
                                    ₱{{ number_format($item->amount_minor / 100, 2) }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted">No payment entries found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2">Total Cash Expected</th>
                                <th class="text-end text-success">
                                    ₱{{ number_format($remittance->total_minor / 100, 2) }}
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @if($remittance->notes)
                <div class="mt-3">
                    <div class="text-muted small mb-1">Rider Notes</div>
                    <div class="p-3 bg-light rounded">{{ $remittance->notes }}</div>
                </div>
                @endif
                @if($remittance->rejection_reason)
                <div class="alert alert-danger mt-3">
                    <strong>Rejection Reason:</strong> {{ $remittance->rejection_reason }}
                </div>
                @endif
                <div class="alert alert-warning mt-4 mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Confirm only after physically receiving and counting the cash from the rider.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endforeach
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const feedback = document.getElementById('codFeedback');
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        let processing = false;

        async function sendAction(url, body = {}) {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify(body)
            });

            const data = await response.json();
            if (!response.ok) {
                const message = data.errors ?
                    Object.values(data.errors).flat().join(' ') :
                    data.message;
                throw new Error(message || 'Unable to process remittance.');
            }
            return data;
        }

        function showError(message) {
            feedback.className = 'alert alert-danger';
            feedback.textContent = message;
            feedback.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }

        document.querySelectorAll('.cod-confirm').forEach(button => {
            button.addEventListener('click', async () => {
                if (processing) return;
                const accepted = window.confirm(
                    `Have you physically received and counted ₱${button.dataset.amount} for ${button.dataset.reference}?\n\nConfirm only if the actual cash matches.`
                );
                if (!accepted) return;

                processing = true;
                button.disabled = true;
                try {
                    await sendAction(button.dataset.url);
                    window.location.reload();
                } catch (error) {
                    showError(error.message);
                    button.disabled = false;
                } finally {
                    processing = false;
                }
            });
        });

        document.querySelectorAll('.cod-reject').forEach(button => {
            button.addEventListener('click', async () => {
                if (processing) return;
                const reason = window.prompt(
                    `Why are you rejecting ${button.dataset.reference}?`
                );
                if (reason === null) return;
                if (!reason.trim() || reason.trim().length > 1000) {
                    showError('Enter a rejection reason of 1–1000 characters.');
                    return;
                }

                processing = true;
                button.disabled = true;
                try {
                    await sendAction(button.dataset.url, {
                        rejection_reason: reason.trim()
                    });
                    window.location.reload();
                } catch (error) {
                    showError(error.message);
                    button.disabled = false;
                } finally {
                    processing = false;
                }
            });
        });
    });
</script>
@endpush