@extends('admin.layout')

@section('title', 'Product Moderation')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#173F35]">Product Moderation</h1>
            <p class="mt-1 text-sm text-gray-500">Review database-backed seller products before marketplace publication.</p>
        </div>
        <form method="GET" class="flex flex-wrap gap-2">
            <input name="search" value="{{ request('search') }}" placeholder="Product or seller"
                   class="rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
            <select name="status" class="rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
                <option value="ALL" @selected($status === 'ALL')>All statuses</option>
                @foreach(\App\Enums\ProductModerationStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case->value)>
                        {{ ucfirst(strtolower($case->value)) }}
                    </option>
                @endforeach
            </select>
            <button class="rounded-xl bg-[#173F35] px-4 py-2 text-sm font-semibold text-white">Filter</button>
        </form>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach(\App\Enums\ProductModerationStatus::cases() as $case)
            <a href="{{ route('admin.products.index', ['status' => $case->value]) }}"
               class="rounded-2xl border border-[#DCE5E0] bg-white p-4 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $case->value }}</div>
                <div class="mt-2 text-2xl font-bold text-[#173F35]">{{ $stats[$case->value] ?? 0 }}</div>
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-2xl border border-[#DCE5E0] bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#E7EDE9] text-sm">
                <thead class="bg-[#F6F9F7] text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-5 py-4">Product</th>
                        <th class="px-5 py-4">Seller</th>
                        <th class="px-5 py-4">Category</th>
                        <th class="px-5 py-4">Status</th>
                        <th class="px-5 py-4">Submitted</th>
                        <th class="px-5 py-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#EDF1EF]">
                    @forelse($products as $product)
                        <tr>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-[#173F35]">{{ $product->name }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ $product->variants->count() }} variant(s)</div>
                            </td>
                            <td class="px-5 py-4 text-gray-700">{{ $product->seller->name }}</td>
                            <td class="px-5 py-4 text-gray-700">{{ $product->category->name }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-[#EEF5F1] px-3 py-1 text-xs font-semibold text-[#173F35]">
                                    {{ $product->moderation_status->value }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-gray-500">{{ $product->created_at->format('M d, Y') }}</td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('admin.products.show', $product) }}"
                                   class="font-semibold text-[#1F6F5B] hover:underline">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No products match this queue.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-[#E7EDE9] px-5 py-4">{{ $products->links() }}</div>
    </div>
</div>
@endsection
