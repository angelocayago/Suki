@extends('admin.layout')
@section('title','COD Oversight')
@section('content')
<div class="space-y-5"><h1 class="text-2xl font-bold text-[#173F35]">COD oversight</h1><p class="text-sm text-gray-600">Read-only audit of provider hub remittances. Discrepancies are retained; Admin does not move cash or edit balances.</p>@forelse($rows as $row)<section class="rounded-xl bg-white p-5 shadow"><p class="font-bold">{{ $row->provider->name }} — ₱{{ number_format($row->expected_minor/100,2) }} — {{ $row->status }}</p><p class="text-sm">Hub: {{ $row->hub?->name ?? 'Not recorded' }} · Reference: {{ $row->reference ?? 'None' }}</p><ul class="mt-2 text-sm">@foreach($row->items as $item)<li>{{ $item->riderRemittance->shipment->tracking_code }}: ₱{{ number_format($item->amount_minor/100,2) }}</li>@endforeach</ul></section>@empty<p>No COD remittance batches recorded.</p>@endforelse {{ $rows->links() }}</div>
@endsection
