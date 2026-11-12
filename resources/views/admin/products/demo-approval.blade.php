@extends('admin.layout')
@section('title', 'Lahat Meron Demo Approval')
@section('content')
<div class="space-y-6">
    <div><a href="{{ route('admin.products.index') }}" class="text-sm font-semibold text-[#1F6F5B]">Back to product moderation</a>
        <h1 class="mt-2 text-2xl font-bold text-[#173F35]">Review Lahat Meron demo batch</h1>
        <p class="mt-1 text-sm text-gray-500">Seller #3 · Others / Miscellaneous · 60 products · 102 variants. This action does not import products.</p>
    </div>
    @if(session('success'))<div role="status" class="rounded-xl border border-green-200 bg-green-50 p-4">{{ session('success') }}</div>@endif
    @if($errors->any())<div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    @if($issue)<div role="alert" class="rounded-xl border border-amber-200 bg-amber-50 p-4">{{ $issue }}</div>@endif
    @if($plan)
        <div class="rounded-2xl border border-[#DCE5E0] bg-white p-5">
            <p class="font-semibold">{{ $plan['pending'] }} pending approval · {{ $plan['approved'] }} already approved</p>
            <p class="mt-2 text-sm text-gray-600">Each pending product uses the existing moderation checks, history and notifications. Already approved entries are skipped. Rejected, hidden, archived or conflicting entries block this batch.</p>
        </div>
        <div class="overflow-x-auto rounded-2xl border border-[#DCE5E0] bg-white"><table class="w-full text-left text-sm">
            <thead class="bg-[#F6F9F7]"><tr><th class="p-3">Reference</th><th class="p-3">Product</th><th class="p-3">Variants</th><th class="p-3">Status</th></tr></thead>
            <tbody>@foreach($plan['products'] as $product)<tr class="border-t"><td class="p-3">{{ $product->legacy_reference }}</td><td class="p-3">{{ $product->name }}</td><td class="p-3">{{ $product->variants->count() }}</td><td class="p-3">{{ $product->moderation_status->value }}</td></tr>@endforeach</tbody>
        </table></div>
        @if($plan['pending'])
        <form method="POST" action="{{ route('admin.products.demo.approve') }}" class="rounded-2xl border border-[#DCE5E0] bg-white p-5" onsubmit="this.querySelector('button[type=submit]').disabled=true">
            @csrf
            <input type="hidden" name="plan_hash" value="{{ $plan['hash'] }}">
            <label for="demo-confirmation" class="block text-sm font-semibold">Type APPROVE LAHAT MERON DEMO to confirm this exact batch</label>
            <input id="demo-confirmation" name="confirmation" required autocomplete="off" value="{{ old('confirmation') }}" class="mt-2 w-full max-w-lg rounded-xl border p-3">
            <button id="demo-approval-submit" type="submit" class="mt-4 block rounded-xl bg-[#173F35] px-5 py-3 font-semibold text-white">Approve {{ $plan['pending'] }} pending demo products</button>
        </form>
        @endif
    @endif
</div>
<script>
window.addEventListener('pageshow', () => {
    const button = document.getElementById('demo-approval-submit');
    if (button) button.disabled = false;
});
</script>
@endsection
