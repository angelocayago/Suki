@extends('superadmin.layout')

@section('title', 'Create Compliance Case')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div>
        <a href="{{ route('superadmin.compliance.index') }}" class="text-sm font-semibold text-[#1F6F5B]">&larr; Compliance queue</a>
        <h1 class="mt-2 text-2xl font-bold text-[#173F35]">Create compliance case</h1>
        <p class="mt-1 text-sm text-gray-500">Record a seller violation without changing the seller's account status.</p>
    </div>

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
        <form method="GET" action="{{ route('superadmin.compliance.create') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <label class="flex-1 text-sm font-semibold text-gray-700">Seller
                <select name="seller_id" required class="mt-2 w-full rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
                    <option value="">Select seller</option>
                    @foreach($sellers as $seller)
                        <option value="{{ $seller->id }}" @selected($selectedSeller?->id === $seller->id)>{{ $seller->name }} ({{ $seller->owner->email }})</option>
                    @endforeach
                </select>
            </label>
            <button class="rounded-xl border border-[#173F35] px-5 py-2 text-sm font-semibold text-[#173F35]">Load seller products</button>
        </form>
    </section>

    @if($selectedSeller)
        <form method="POST" action="{{ route('superadmin.compliance.store') }}" class="space-y-5 rounded-2xl border border-[#DCE5E0] bg-white p-6 shadow-sm">
            @csrf
            <input type="hidden" name="seller_id" value="{{ $selectedSeller->id }}">
            <div class="rounded-xl bg-[#F6F9F7] p-4 text-sm"><span class="font-semibold">Seller:</span> {{ $selectedSeller->name }}</div>

            <div class="grid gap-5 md:grid-cols-2">
                <label class="text-sm font-semibold text-gray-700">Category
                    <select name="category" required class="mt-2 w-full rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
                        @foreach(\App\Enums\SellerViolationCategory::cases() as $case)
                            <option value="{{ $case->value }}" @selected(old('category') === $case->value)>{{ str($case->value)->replace('_', ' ')->headline() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold text-gray-700">Severity
                    <select name="severity" required class="mt-2 w-full rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
                        @foreach(\App\Enums\SellerViolationSeverity::cases() as $case)
                            <option value="{{ $case->value }}" @selected(old('severity', 'MEDIUM') === $case->value)>{{ $case->value }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <label class="block text-sm font-semibold text-gray-700">Related product (optional)
                <select name="product_id" class="mt-2 w-full rounded-xl border border-[#DCE5E0] px-3 py-2 text-sm">
                    <option value="">No related product</option>
                    @foreach($selectedSeller->products as $product)
                        <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm font-semibold text-gray-700">Reason
                <textarea name="reason" required maxlength="2000" rows="3" class="mt-2 w-full rounded-xl border border-[#DCE5E0] p-3 text-sm">{{ old('reason') }}</textarea>
            </label>
            <label class="block text-sm font-semibold text-gray-700">Details (optional)
                <textarea name="details" maxlength="10000" rows="5" class="mt-2 w-full rounded-xl border border-[#DCE5E0] p-3 text-sm">{{ old('details') }}</textarea>
            </label>

            <button class="rounded-xl bg-[#173F35] px-5 py-2.5 text-sm font-semibold text-white">Create compliance case</button>
        </form>
    @endif
</div>
@endsection
