@extends('seller.layout')
@section('page-title', 'Create Voucher')

@section('content')
<div class="pt-3 pb-3 mb-4 border-bottom">
    <a href="{{ route('seller.vouchers.index') }}" class="text-decoration-none small"><i class="bi bi-arrow-left me-1"></i> Back to vouchers</a>
    <h1 class="h2 mt-2 mb-1">Create Voucher</h1>
    <p class="text-muted mb-0">Set a discount, choose eligible products, and schedule your promotion.</p>
</div>
@include('seller.vouchers._form')
@endsection
