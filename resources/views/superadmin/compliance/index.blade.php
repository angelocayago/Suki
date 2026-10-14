@extends('superadmin.layout')

@section('title', 'Seller Compliance')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#173F35]">Seller Compliance</h1>
            <p class="mt-1 text-sm text-gray-500">Review violations, warnings, evidence, and case history.</p>
        </div>
        <a href="{{ route('superadmin.compliance.create') }}" class="rounded-xl bg-[#173F35] px-5 py-2.5 text-center text-sm font-semibold text-white">
            Create violation
        </a>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach(\App\Enums\SellerViolationStatus::cases() as $case)
            <a href="{{ route('superadmin.compliance.index', ['status' => $case->value]) }}" class="rounded-2xl border border-[#DCE5E0] bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $case->value }}</div>
                <div class="mt-2 text-2xl font-bold text-[#173F35]">{{ $stats[$case->value] ?? 0 }}</div>
            </a>
        @endforeach
    </div>

    <form method="GET" class="grid gap-3 rounded-2xl border border-[#DCE5E0] bg-white p-5 shadow-sm md:grid-cols-2 xl:grid-cols-4">
        <input name="search" value="{{ request('search') }}" placeholder="Reference, seller, product, reason"
               class="rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm xl:col-span-2">
        <select name="seller_id" class="rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
            <option value="">All sellers</option>
            @foreach($sellers as $seller)
                <option value="{{ $seller->id }}" @selected((string) request('seller_id') === (string) $seller->id)>{{ $seller->name }}</option>
            @endforeach
        </select>
        <select name="severity" class="rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
            <option value="ALL">All severities</option>
            @foreach(\App\Enums\SellerViolationSeverity::cases() as $case)
                <option value="{{ $case->value }}" @selected(request('severity', 'ALL') === $case->value)>{{ $case->value }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
            <option value="ALL">All statuses</option>
            @foreach(\App\Enums\SellerViolationStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected(request('status', 'ALL') === $case->value)>{{ $case->value }}</option>
            @endforeach
        </select>
        <select name="category" class="rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
            <option value="ALL">All categories</option>
            @foreach(\App\Enums\SellerViolationCategory::cases() as $case)
                <option value="{{ $case->value }}" @selected(request('category', 'ALL') === $case->value)>{{ str($case->value)->replace('_', ' ')->headline() }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Created from" class="rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
        <input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Created to" class="rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
        <div class="flex gap-2 xl:col-span-4">
            <button class="rounded-xl bg-[#173F35] px-5 py-2 text-sm font-semibold text-white">Apply filters</button>
            <a href="{{ route('superadmin.compliance.index') }}" class="rounded-xl border border-[#DCE5E0] px-5 py-2 text-sm font-semibold text-gray-700">Clear</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-[#DCE5E0] bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#E7EDE9] text-sm">
                <thead class="bg-[#F6F9F7] text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr><th class="px-5 py-4">Case</th><th class="px-5 py-4">Seller</th><th class="px-5 py-4">Category</th><th class="px-5 py-4">Severity</th><th class="px-5 py-4">Status</th><th class="px-5 py-4">Created</th><th class="px-5 py-4"></th></tr>
                </thead>
                <tbody class="divide-y divide-[#EDF1EF]">
                    @forelse($violations as $violation)
                        <tr>
                            <td class="px-5 py-4"><div class="font-semibold text-[#173F35]">{{ $violation->reference }}</div><div class="mt-1 max-w-xs truncate text-xs text-gray-500">{{ $violation->reason }}</div></td>
                            <td class="px-5 py-4">{{ $violation->seller->name }}</td>
                            <td class="px-5 py-4">{{ str($violation->category->value)->replace('_', ' ')->headline() }}</td>
                            <td class="px-5 py-4"><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">{{ $violation->severity->value }}</span></td>
                            <td class="px-5 py-4"><span class="rounded-full bg-[#EEF5F1] px-3 py-1 text-xs font-semibold text-[#173F35]">{{ $violation->status->value }}</span></td>
                            <td class="px-5 py-4 text-gray-500">{{ $violation->created_at->format('M d, Y') }}</td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('superadmin.compliance.show', $violation) }}" class="font-semibold text-[#1F6F5B] hover:underline">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-12 text-center text-gray-500">No compliance cases match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-[#E7EDE9] px-5 py-4">{{ $violations->links() }}</div>
    </div>
</div>
@endsection
