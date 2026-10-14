@extends('admin.layout')

@section('title', 'Review Product')

@section('content')
@php
    $state = $product->moderation_status;
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <a href="{{ route('admin.products.index') }}" class="text-sm font-semibold text-[#1F6F5B]">← Review queue</a>
            <h1 class="mt-2 text-2xl font-bold text-[#173F35]">{{ $product->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $product->seller->name }} · {{ $product->category->name }}</p>
        </div>
        <span class="rounded-full bg-[#EEF5F1] px-4 py-2 text-xs font-bold text-[#173F35]">{{ $state->value }}</span>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-[#173F35]">Product details</h2>
                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs uppercase text-gray-500">Seller</dt><dd class="mt-1 font-semibold">{{ $product->seller->name }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Category</dt><dd class="mt-1 font-semibold">{{ $product->category->name }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Brand</dt><dd class="mt-1 font-semibold">{{ $product->brand ?: 'Not provided' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Seller visibility</dt><dd class="mt-1 font-semibold">{{ $product->is_active ? 'Active' : 'Inactive' }}</dd></div>
                </dl>
                <div class="mt-5">
                    <div class="text-xs uppercase text-gray-500">Description</div>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700">{{ $product->description }}</p>
                </div>
            </section>

            <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-[#173F35]">Variants</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-500"><tr><th class="py-2">Name</th><th>SKU</th><th>Price</th><th>Stock</th><th>Active</th></tr></thead>
                        <tbody class="divide-y divide-[#EDF1EF]">
                            @foreach($product->variants as $variant)
                                <tr><td class="py-3 font-semibold">{{ $variant->name }}</td><td>{{ $variant->sku }}</td><td>₱{{ number_format($variant->price_minor / 100, 2) }}</td><td>{{ $variant->stock }}</td><td>{{ $variant->is_active ? 'Yes' : 'No' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-[#173F35]">Images</h2>
                <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                    @forelse($product->images as $image)
                        <img src="{{ asset('storage/'.$image->path) }}" alt="{{ $product->name }}" class="aspect-square w-full rounded-xl border object-cover">
                    @empty
                        <p class="col-span-full text-sm text-gray-500">No product images uploaded.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="space-y-6">
            <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-[#173F35]">Moderation action</h2>
                <div class="mt-4 rounded-xl {{ $categoryAllowed ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800' }} p-3 text-sm">
                    {{ $categoryAllowed ? 'Category is within the seller assigned category tree.' : 'Assign a compatible root category on the seller profile before approval.' }}
                </div>

                @if($state === \App\Enums\ProductModerationStatus::Pending)
                    <form method="POST" action="{{ route('admin.products.approve', $product) }}" class="mt-4">@csrf
                        <button class="w-full rounded-xl bg-[#173F35] px-4 py-3 text-sm font-semibold text-white">Approve product</button>
                    </form>
                    <form method="POST" action="{{ route('admin.products.reject', $product) }}" class="mt-4 space-y-2">@csrf
                        <textarea name="reason" required maxlength="1000" placeholder="Required rejection reason" class="w-full rounded-xl border border-[#DCE5E0] p-3 text-sm"></textarea>
                        <button class="w-full rounded-xl bg-red-600 px-4 py-3 text-sm font-semibold text-white">Reject product</button>
                    </form>
                @elseif($state === \App\Enums\ProductModerationStatus::Approved)
                    <form method="POST" action="{{ route('admin.products.hide', $product) }}" class="mt-4 space-y-2">@csrf
                        <textarea name="reason" required maxlength="1000" placeholder="Required hiding reason" class="w-full rounded-xl border border-[#DCE5E0] p-3 text-sm"></textarea>
                        <button class="w-full rounded-xl bg-amber-600 px-4 py-3 text-sm font-semibold text-white">Hide product</button>
                    </form>
                @elseif($state === \App\Enums\ProductModerationStatus::Hidden)
                    <form method="POST" action="{{ route('admin.products.restore', $product) }}" class="mt-4">@csrf
                        <button class="w-full rounded-xl bg-[#173F35] px-4 py-3 text-sm font-semibold text-white">Restore product</button>
                    </form>
                @else
                    <p class="mt-4 text-sm text-gray-500">The seller must materially update this product before it returns to pending review.</p>
                @endif

                @if($product->moderation_reason)
                    <div class="mt-4 border-t pt-4 text-sm"><span class="font-semibold">Current reason:</span> {{ $product->moderation_reason }}</div>
                @endif
            </section>

            <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-[#173F35]">Moderation history</h2>
                <div class="mt-4 space-y-4">
                    @forelse($product->moderationEvents as $event)
                        <div class="border-l-2 border-[#BFD2C9] pl-3 text-sm">
                            <div class="font-semibold text-[#173F35]">{{ $event->previous_status?->value ?? 'NEW' }} → {{ $event->new_status->value }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $event->created_at->format('M d, Y h:i A') }} · {{ $event->actor->name ?? 'System' }}</div>
                            @if($event->reason)<p class="mt-2 text-gray-700">{{ $event->reason }}</p>@endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No moderation history.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
