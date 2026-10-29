@extends('seller.layout')
@section('page-title', 'Edit Voucher')

@section('content')
<div class="pt-3 pb-3 mb-4 border-bottom">
    <a href="{{ route('seller.vouchers.index') }}" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i> Back to vouchers</a>
    <h1 class="h2 mt-2 mb-1">Edit Voucher</h1>
    <p class="text-muted mb-0">{{ $voucher->code }} · Used vouchers cannot be edited; pause them instead.</p>
</div>
@include('seller.vouchers._form')
@endsection
