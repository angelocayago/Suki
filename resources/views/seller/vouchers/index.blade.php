@extends('seller.layout')
@section('page-title', 'Vouchers')
@push('styles')
<style>
.voucher-stat{background:#fff;border:1px solid #eae5f4;border-radius:15px;padding:18px 20px;height:100%;box-shadow:0 3px 12px rgba(42,22,82,.035)}
.voucher-stat .metric{font-size:1.65rem;font-weight:750;line-height:1.3;color:#272039}
.voucher-stat .caption{font-size:.82rem;color:#777087;font-weight:600}
.voucher-stat .bi{color:#8751e8;font-size:1.25rem}
.voucher-table-card{background:#fff;border:1px solid #e9e5f2;border-radius:16px;padding:16px;box-shadow:0 4px 16px rgba(42,22,82,.04)}
.voucher-table-card .table{margin-bottom:0}
.voucher-table-card thead th{background:#f8f7fc;color:#55516b;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:15px 12px;border-bottom:1px solid #e9e5f2;white-space:nowrap}
.voucher-table-card tbody td{padding:18px 12px;vertical-align:middle;border-color:#f0edf5}
.voucher-table-card tbody tr:hover{background:#fcfaff}
.voucher-table-card code{color:#7946e8;background:#f3ecff;padding:4px 8px;border-radius:6px;font-size:12px}
.voucher-table-card .badge{padding:7px 11px;border-radius:20px;font-weight:600}
.voucher-table-card .btn-sm{border-radius:8px;padding:7px 11px;font-weight:500}
.voucher-filters{background:#fff;border:1px solid #eae5f4;border-radius:14px;padding:16px}
@media(max-width:768px){.voucher-table-card{padding:10px}.voucher-stat{padding:14px}}
</style>
@endpush
@section('content')
@php
    // The controller currently paginates, so counts below use scoped queries for accurate totals.
    $baseQuery = \App\Models\Voucher::query()->where('owner_type', 'seller')->where('seller_id', $seller->id);
    $totalVouchers = (clone $baseQuery)->count();
    $activeVouchers = (clone $baseQuery)->currentlyActive()->where(function ($q) {
        $q->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit');
    })->count();
    $scheduledVouchers = (clone $baseQuery)->where('is_enabled', true)->where('starts_at', '>', now())->count();
    $totalRedemptions = \App\Models\VoucherRedemption::query()->whereHas('voucher', function ($q) use ($seller) {
        $q->where('owner_type', 'seller')->where('seller_id', $seller->id);
    })->where('status', 'redeemed')->count();
    $search = trim((string) request('search', ''));
    $statusFilter = (string) request('status', 'all');
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pt-3 pb-3 mb-4 border-bottom">
  <div><h1 class="h2 mb-1">Vouchers</h1><p class="text-muted mb-0">Create and manage discounts for your shop.</p></div>
  <a href="{{ route('seller.vouchers.create') }}" class="btn btn-bili-hub"><i class="bi bi-plus-circle me-1"></i> Create Voucher</a>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3"><div class="voucher-stat"><div class="d-flex justify-content-between"><span class="caption">Total Vouchers</span><i class="bi bi-ticket-perforated"></i></div><div class="metric">{{ number_format($totalVouchers) }}</div></div></div>
  <div class="col-6 col-xl-3"><div class="voucher-stat"><div class="d-flex justify-content-between"><span class="caption">Active</span><i class="bi bi-check-circle"></i></div><div class="metric">{{ number_format($activeVouchers) }}</div></div></div>
  <div class="col-6 col-xl-3"><div class="voucher-stat"><div class="d-flex justify-content-between"><span class="caption">Scheduled</span><i class="bi bi-calendar-event"></i></div><div class="metric">{{ number_format($scheduledVouchers) }}</div></div></div>
  <div class="col-6 col-xl-3"><div class="voucher-stat"><div class="d-flex justify-content-between"><span class="caption">Redemptions</span><i class="bi bi-people"></i></div><div class="metric">{{ number_format($totalRedemptions) }}</div></div></div>
</div>
<div class="alert alert-info d-flex gap-2 align-items-start" role="alert"><i class="bi bi-info-circle"></i><div>Vouchers can be configured here, but buyers cannot redeem them at checkout until checkout integration is enabled.</div></div>
<form class="voucher-filters row g-3 align-items-end mb-3" method="GET" action="{{ route('seller.vouchers.index') }}">
  <div class="col-md-6"><label for="voucher-search" class="form-label small fw-semibold">Search vouchers</label><input id="voucher-search" name="search" class="form-control" placeholder="Voucher name or code" value="{{ $search }}"></div>
  <div class="col-md-3"><label for="voucher-status" class="form-label small fw-semibold">Status</label><select id="voucher-status" name="status" class="form-select">
    @foreach(['all'=>'All statuses','active'=>'Active','scheduled'=>'Scheduled','paused'=>'Paused','expired'=>'Expired','exhausted'=>'Exhausted'] as $value => $label)
      <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
    @endforeach
  </select></div>
  <div class="col-md-3 d-flex gap-2"><button class="btn btn-bili-hub flex-grow-1" type="submit"><i class="bi bi-funnel me-1"></i> Filter</button><a class="btn btn-outline-secondary" href="{{ route('seller.vouchers.index') }}">Reset</a></div>
</form>
<div class="voucher-table-card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Voucher</th><th>Discount</th><th>Minimum Spend</th><th>Validity</th><th>Usage</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
@forelse($vouchers as $voucher)
@php
  $status = $voucher->display_status;
  $statusColors = ['active'=>'success','scheduled'=>'info','expired'=>'secondary','paused'=>'warning','exhausted'=>'dark'];
  $discount = $voucher->discount_type === 'fixed' ? '₱'.number_format(($voucher->discount_value_minor ?? 0)/100,2) : number_format(($voucher->discount_percent_bps ?? 0)/100,2).'%';
@endphp
<tr><td><div class="fw-semibold">{{ $voucher->name }}</div><code>{{ $voucher->code }}</code><div class="small text-muted mt-1">{{ $voucher->product_scope === 'all' ? 'All eligible products' : 'Selected products' }}</div></td>
<td><strong>{{ $discount }}</strong>@if($voucher->discount_type === 'percentage' && $voucher->maximum_discount_minor !== null)<div class="small text-muted">Cap: ₱{{ number_format($voucher->maximum_discount_minor/100,2) }}</div>@endif</td>
<td>₱{{ number_format(($voucher->minimum_spend_minor ?? 0)/100,2) }}</td>
<td class="small"><div>{{ $voucher->starts_at?->timezone('Asia/Manila')->format('M d, Y g:i A') }}</div><div class="text-muted">to {{ $voucher->ends_at?->timezone('Asia/Manila')->format('M d, Y g:i A') }}</div></td>
<td>{{ $voucher->usage_count }} / {{ $voucher->usage_limit ?? 'Unlimited' }}</td>
<td><span class="badge text-bg-{{ $statusColors[$status] ?? 'secondary' }}">{{ ucfirst($status) }}</span></td>
<td class="text-end"><div class="d-inline-flex gap-2 flex-wrap justify-content-end">@if((int)$voucher->usage_count === 0)<a href="{{ route('seller.vouchers.edit', $voucher) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>@endif<form method="POST" action="{{ route('seller.vouchers.toggle', $voucher) }}">@csrf @method('PATCH')<button type="submit" class="btn btn-sm {{ $voucher->is_enabled ? 'btn-outline-warning' : 'btn-outline-success' }}"><i class="bi {{ $voucher->is_enabled ? 'bi-pause-circle' : 'bi-play-circle' }}"></i> {{ $voucher->is_enabled ? 'Pause' : 'Resume' }}</button></form></div></td></tr>
@empty
<tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-ticket-perforated fs-2 d-block mb-2"></i>No matching vouchers found.</td></tr>
@endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $vouchers->appends(request()->only(['search','status']))->links() }}</div>
@endsection
