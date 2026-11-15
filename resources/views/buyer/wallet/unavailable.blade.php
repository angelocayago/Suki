@extends('layouts.app')
@section('content')
<x-buyer.account-shell active="wallet"><section class="rounded-2xl border bg-white p-6"><h1 class="text-2xl font-bold text-[#173F35]">Suki Wallet</h1><p class="mt-3" role="status">Suki Wallet is not available yet. No balance has been changed.</p><a href="{{ route('buyer.account') }}" class="mt-4 inline-block font-semibold text-[#1F6F5B]">Back to My Account</a></section></x-buyer.account-shell>
@endsection
