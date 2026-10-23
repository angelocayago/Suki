@extends('admin.layout')
@section('content')
<div class="container-fluid py-4"><h2>Rider Withdrawals</h2>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@foreach($withdrawals as $w)<div class="card border-0 shadow-sm mb-3"><div class="card-body"><div class="row g-3"><div class="col-lg-5"><h5>#{{ $w->id }} · ₱{{ number_format($w->amount_minor/100,2) }}</h5><div>{{ strtoupper(str_replace('_',' ',$w->payment_method)) }}</div><div>{{ $w->account_name }} · {{ $w->account_number }}</div><div>Status: {{ ucfirst($w->status) }}</div></div><div class="col-lg-7">
@if($w->status==='pending')<form method="POST" action="{{ route('admin.rider-withdrawals.paid',$w) }}">@csrf<div class="mb-2"><input name="transaction_reference" class="form-control" placeholder="Transaction Reference" required></div><div class="mb-2"><textarea name="admin_notes" class="form-control" placeholder="Admin Notes"></textarea></div><label><input type="checkbox" name="confirmed_sent" value="1" required> I actually sent the payout.</label><button class="btn btn-success w-100 mt-2">Mark Paid</button></form><hr><form method="POST" action="{{ route('admin.rider-withdrawals.reject',$w) }}">@csrf<textarea name="admin_notes" class="form-control mb-2" placeholder="Rejection reason" required></textarea><button class="btn btn-outline-danger">Reject & Return Funds</button></form>@else<div>Reference: {{ $w->transaction_reference ?: '—' }}</div>@endif
</div></div></div></div>@endforeach{{ $withdrawals->links() }}</div>
@endsection
