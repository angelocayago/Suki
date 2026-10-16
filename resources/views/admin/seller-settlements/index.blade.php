@extends('admin.layout')

@section('title', 'Seller Settlements')

@section('content')
<div class="container-fluid py-4">
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Action could not be completed:</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1">Seller Settlements</h2>
            <p class="text-muted mb-0">Review seller earnings, commissions, and payout status.</p>
        </div>
        <span class="badge bg-secondary">Admin Financial Management</span>
    </div>

    @php
    $cards = [
    [
    'title' => 'For Review',
    'amount' => $stats['eligible_minor'],
    'color' => 'warning',
    'subtitle' => $stats['eligible_count'] . ' settlement(s)',
    ],
    [
    'title' => 'Approved',
    'amount' => $stats['approved_minor'],
    'color' => 'primary',
    'subtitle' => 'Awaiting verified payout',
    ],
    [
    'title' => 'Paid',
    'amount' => $stats['paid_minor'],
    'color' => 'success',
    'subtitle' => 'Recorded payouts',
    ],
    ];
    @endphp

    <div class="row g-3 mb-4">
        @foreach($cards as $card)
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">{{ $card['title'] }}</p>
                    <h3 class="fw-bold mb-2">₱{{ number_format($card['amount'] / 100, 2) }}</h3>
                    <span class="badge bg-{{ $card['color'] }}">{{ $card['subtitle'] }}</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <h5 class="fw-bold mb-0">Settlement Records</h5>
                <div class="d-flex gap-2 flex-wrap">
                    @foreach([
                    'all' => 'All',
                    'eligible' => 'For Review',
                    'approved' => 'Approved',
                    'paid' => 'Paid',
                    ] as $value => $label)
                    <a href="{{ route('admin.seller-settlements.index', ['status' => $value]) }}"
                        class="btn btn-sm {{ $status === $value ? 'btn-dark' : 'btn-outline-secondary' }}">
                        {{ $label }}
                    </a>
                    @endforeach
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Settlement</th>
                            <th>Seller</th>
                            <th>Seller Order</th>
                            <th>Subtotal</th>
                            <th>Commission</th>
                            <th>Net Payout</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($settlements as $settlement)
                        @php
                        $badge = match ($settlement->status) {
                        'eligible' => 'warning',
                        'approved' => 'primary',
                        'paid' => 'success',
                        default => 'secondary',
                        };
                        $payout = ($payouts ?? collect())->get($settlement->id);
                        @endphp
                        <tr>
                            <td><strong>#{{ $settlement->id }}</strong></td>
                            <td>{{ $settlement->seller?->name ?? 'Unknown Seller' }}</td>
                            <td>#{{ $settlement->seller_order_id }}</td>
                            <td>₱{{ number_format($settlement->subtotal_minor / 100, 2) }}</td>
                            <td>₱{{ number_format($settlement->commission_minor / 100, 2) }}</td>
                            <td class="fw-bold">₱{{ number_format($settlement->net_minor / 100, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $badge }}">
                                    {{ $settlement->status === 'eligible' ? 'For Review' : ucfirst($settlement->status) }}
                                </span>
                            </td>
                            <td>{{ $settlement->created_at?->format('M d, Y') ?? '—' }}</td>
                            <td style="min-width: 270px;">
                                @if($settlement->status === 'eligible')
                                <form method="POST"
                                    action="{{ route('admin.seller-settlements.approve', $settlement) }}"
                                    onsubmit="return confirm('Approve this settlement after completing financial verification?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        Approve Settlement
                                    </button>
                                </form>
                                @elseif($settlement->status === 'approved')
                                <details>
                                    <summary class="btn btn-sm btn-outline-success">Record Actual Payout</summary>
                                    <form method="POST"
                                        action="{{ route('admin.seller-settlements.record-payout', $settlement) }}"
                                        class="border rounded p-3 mt-2"
                                        onsubmit="return confirm('Confirm that this payment was actually sent and independently verified?');">
                                        @csrf
                                        <div class="fw-semibold mb-2">Verified Payout Details</div>
                                        <p class="small text-muted mb-3">
                                            Expected amount: <strong>₱{{ number_format($settlement->net_minor / 100, 2) }}</strong>
                                        </p>
                                        <div class="mb-2">
                                            <label class="form-label small">Payment Method</label>
                                            <select name="payment_method" class="form-select form-select-sm" required>
                                                <option value="">Select method</option>
                                                <option value="bank_transfer">Bank Transfer</option>
                                                <option value="gcash">GCash</option>
                                                <option value="maya">Maya</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Transaction Reference</label>
                                            <input type="text" name="transaction_reference"
                                                class="form-control form-control-sm" maxlength="150" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Actual Payment Date/Time</label>
                                            <input type="datetime-local" name="paid_at"
                                                class="form-control form-control-sm" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Verification Notes</label>
                                            <textarea name="notes" class="form-control form-control-sm"
                                                rows="2" maxlength="2000"
                                                placeholder="Payment evidence or verification details"></textarea>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox"
                                                name="payout_verified" value="1"
                                                id="verified-{{ $settlement->id }}" required>
                                            <label class="form-check-label small" for="verified-{{ $settlement->id }}">
                                                I checked the actual payment record and confirmed the
                                                amount and transaction reference match.
                                            </label>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-success w-100">
                                            Record Verified Payout
                                        </button>
                                    </form>
                                </details>
                                @elseif($settlement->status === 'paid')
                                <span class="badge bg-success mb-2">Paid</span>
                                @if($payout)
                                <div class="small">
                                    <div><strong>Method:</strong> {{ $payout->payment_method }}</div>
                                    <div><strong>Reference:</strong> {{ $payout->transaction_reference }}</div>
                                    <div><strong>Amount:</strong> ₱{{ number_format($payout->amount_minor / 100, 2) }}</div>
                                    <div><strong>Verified:</strong> {{ $payout->verified_at }}</div>
                                </div>
                                @else
                                <div class="small text-danger">Payout audit record unavailable.</div>
                                @endif
                                @else
                                <span class="text-muted small">No action available</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                No seller settlements found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $settlements->links() }}</div>
        </div>
    </div>

    <div class="alert alert-info mt-4 mb-0">
        <strong>Important:</strong> Eligible settlements are not automatically approved or paid.
        Admin financial verification and actual payout recording are required before a
        settlement can be marked as paid. Recording a payout does not transfer funds.
    </div>
</div>
@endsection