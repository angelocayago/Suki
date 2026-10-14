<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Complaint - SUKI</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#F6F9F7] text-gray-800">
@php
    $action = $contextType === 'buyer'
        ? route('buyer.complaints.store', $order)
        : route($contextType.'.complaints.store', $sellerOrder);
@endphp
<main class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <a href="{{ route('complaints.mine.index') }}" class="text-sm font-semibold text-[#1F6F5B]">&larr; My complaints</a>
    <div class="mt-4 rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-bold text-[#173F35]">Submit a complaint</h1>
        <p class="mt-1 text-sm text-gray-500">Order {{ $order->order_number }}@if($sellerOrder) &middot; Seller order #{{ $sellerOrder->id }}@endif</p>

        @if($errors->any())<div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ $action }}" class="mt-6 space-y-5">
            @csrf
            <div><label class="text-sm font-semibold text-gray-700">Category</label><select name="category" required class="mt-2 w-full rounded-xl border border-[#DCE5E0] px-3 py-2.5 text-sm"><option value="">Select category</option>@foreach(\App\Enums\ComplaintCategory::cases() as $category)<option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ str(strtolower($category->value))->replace('_', ' ')->headline() }}</option>@endforeach</select></div>

            @if($contextType === 'buyer')
                <div><label class="text-sm font-semibold text-gray-700">Affected seller order <span class="font-normal text-gray-500">(required for seller or product-specific complaints)</span></label><select name="seller_order_id" class="mt-2 w-full rounded-xl border border-[#DCE5E0] px-3 py-2.5 text-sm"><option value="">General order complaint</option>@foreach($sellerOrders as $option)<option value="{{ $option->id }}" @selected((string) old('seller_order_id') === (string) $option->id)>#{{ $option->id }} &mdash; {{ $option->seller->name }}</option>@endforeach</select></div>
                <div><label class="text-sm font-semibold text-gray-700">Affected product <span class="font-normal text-gray-500">(optional)</span></label><select name="product_id" class="mt-2 w-full rounded-xl border border-[#DCE5E0] px-3 py-2.5 text-sm"><option value="">No specific product</option>@foreach($sellerOrders as $option)@foreach($option->items->pluck('product')->filter()->unique('id') as $product)<option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>{{ $option->seller->name }} &mdash; {{ $product->name }}</option>@endforeach @endforeach</select></div>
            @elseif($products->isNotEmpty())
                <div><label class="text-sm font-semibold text-gray-700">Affected product <span class="font-normal text-gray-500">(optional)</span></label><select name="product_id" class="mt-2 w-full rounded-xl border border-[#DCE5E0] px-3 py-2.5 text-sm"><option value="">No specific product</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>{{ $product->name }}</option>@endforeach</select></div>
            @endif

            <div><label class="text-sm font-semibold text-gray-700">Subject</label><input name="subject" value="{{ old('subject') }}" required maxlength="200" class="mt-2 w-full rounded-xl border border-[#DCE5E0] px-3 py-2.5 text-sm"></div>
            <div><label class="text-sm font-semibold text-gray-700">Description</label><textarea name="description" required maxlength="10000" rows="6" class="mt-2 w-full rounded-xl border border-[#DCE5E0] p-3 text-sm">{{ old('description') }}</textarea></div>
            <p class="rounded-xl bg-[#F6F9F7] p-4 text-xs text-gray-600">Submitting a complaint records a review case only. It does not change order, payment, shipment, or inventory status.</p>
            <button class="rounded-xl bg-[#173F35] px-5 py-2.5 text-sm font-semibold text-white">Submit complaint</button>
        </form>
    </div>
</main>
</body>
</html>
