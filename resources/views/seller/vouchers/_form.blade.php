@php
    $editing = isset($voucher);
    $selectedProductIds = collect(old('product_ids', $editing ? $voucher->products->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
    $discountType = old('discount_type', $editing ? $voucher->discount_type : 'percentage');
    $productScope = old('product_scope', $editing ? $voucher->product_scope : 'all');
    $startsAt = old('starts_at', $editing ? $voucher->starts_at?->timezone('Asia/Manila')->format('Y-m-d\TH:i') : '');
    $endsAt = old('ends_at', $editing ? $voucher->ends_at?->timezone('Asia/Manila')->format('Y-m-d\TH:i') : '');
@endphp

@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Please correct the following:</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $editing ? route('seller.vouchers.update', $voucher) : route('seller.vouchers.store') }}">
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Voucher details</h2>
            <div class="row g-3">
                <div class="col-md-7">
                    <label for="name" class="form-label">Voucher name <span class="text-danger">*</span></label>
                    <input id="name" name="name" class="form-control" maxlength="255" required value="{{ old('name', $voucher->name ?? '') }}" placeholder="Weekend Sale">
                </div>
                <div class="col-md-5">
                    <label for="code" class="form-label">Voucher code <span class="text-danger">*</span></label>
                    <input id="code" name="code" class="form-control text-uppercase" maxlength="80" pattern="[A-Za-z0-9_-]+" required value="{{ old('code', $voucher->code ?? '') }}" placeholder="SHOP10">
                    <div class="form-text">Letters, numbers, underscores and hyphens only. Must be unique across BiliHub.</div>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description (optional)</label>
                    <textarea id="description" name="description" rows="2" maxlength="2000" class="form-control">{{ old('description', $voucher->description ?? '') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Discount rules</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="discount_type" class="form-label">Discount type <span class="text-danger">*</span></label>
                    <select name="discount_type" id="discount_type" class="form-select" required>
                        <option value="percentage" @selected($discountType === 'percentage')>Percentage (%)</option>
                        <option value="fixed" @selected($discountType === 'fixed')>Fixed amount (₱)</option>
                    </select>
                </div>
                <div class="col-md-4" id="percentage-field">
                    <label for="discount_percent" class="form-label">Discount percentage</label>
                    <div class="input-group"><input type="number" id="discount_percent" name="discount_percent" class="form-control" min="0.01" max="100" step="0.01" value="{{ old('discount_percent', $editing && $voucher->discount_percent_bps !== null ? number_format($voucher->discount_percent_bps / 100, 2, '.', '') : '') }}"><span class="input-group-text">%</span></div>
                </div>
                <div class="col-md-4" id="fixed-field">
                    <label for="discount_amount" class="form-label">Fixed discount</label>
                    <div class="input-group"><span class="input-group-text">₱</span><input type="number" id="discount_amount" name="discount_amount" class="form-control" min="0.01" step="0.01" value="{{ old('discount_amount', $editing && $voucher->discount_value_minor !== null ? number_format($voucher->discount_value_minor / 100, 2, '.', '') : '') }}"></div>
                </div>
                <div class="col-md-4">
                    <label for="minimum_spend" class="form-label">Minimum spend <span class="text-danger">*</span></label>
                    <div class="input-group"><span class="input-group-text">₱</span><input type="number" id="minimum_spend" name="minimum_spend" class="form-control" min="0" step="0.01" required value="{{ old('minimum_spend', $editing ? number_format($voucher->minimum_spend_minor / 100, 2, '.', '') : '0.00') }}"></div>
                </div>
                <div class="col-md-4" id="cap-field">
                    <label for="maximum_discount" class="form-label">Maximum discount (optional)</label>
                    <div class="input-group"><span class="input-group-text">₱</span><input type="number" id="maximum_discount" name="maximum_discount" class="form-control" min="0.01" step="0.01" value="{{ old('maximum_discount', $editing && $voucher->maximum_discount_minor !== null ? number_format($voucher->maximum_discount_minor / 100, 2, '.', '') : '') }}"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Eligible products</h2>
            <div class="mb-3">
                <label for="product_scope" class="form-label">Applies to <span class="text-danger">*</span></label>
                <select name="product_scope" id="product_scope" class="form-select" required>
                    <option value="all" @selected($productScope === 'all')>All approved, published products in my shop</option>
                    <option value="selected" @selected($productScope === 'selected')>Selected products only</option>
                </select>
            </div>
            <div id="product-picker" class="border rounded p-3" style="max-height: 240px; overflow-y: auto;">
                @forelse($products as $product)
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" name="product_ids[]" id="product-{{ $product->id }}" value="{{ $product->id }}" @checked(in_array((int) $product->id, $selectedProductIds, true))>
                        <label for="product-{{ $product->id }}" class="form-check-label">{{ $product->name }} <span class="text-muted small">#{{ $product->id }}</span></label>
                    </div>
                @empty
                    <p class="text-muted mb-0">No approved, published products available.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h2 class="h5 mb-3">Schedule and usage</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="starts_at" class="form-label">Starts at (Philippine time) <span class="text-danger">*</span></label>
                    <input id="starts_at" name="starts_at" type="datetime-local" class="form-control" required value="{{ $startsAt }}">
                </div>
                <div class="col-md-6">
                    <label for="ends_at" class="form-label">Ends at (Philippine time) <span class="text-danger">*</span></label>
                    <input id="ends_at" name="ends_at" type="datetime-local" class="form-control" required value="{{ $endsAt }}">
                </div>
                <div class="col-md-6">
                    <label for="usage_limit" class="form-label">Total usage limit (optional)</label>
                    <input id="usage_limit" name="usage_limit" type="number" min="1" step="1" class="form-control" value="{{ old('usage_limit', $voucher->usage_limit ?? '') }}" placeholder="Unlimited">
                </div>
                <div class="col-md-6">
                    <label for="per_user_limit" class="form-label">Uses per buyer <span class="text-danger">*</span></label>
                    <input id="per_user_limit" name="per_user_limit" type="number" min="1" step="1" class="form-control" required value="{{ old('per_user_limit', $voucher->per_user_limit ?? 1) }}">
                </div>
                <div class="col-12">
                    <input type="hidden" name="is_enabled" value="0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_enabled" name="is_enabled" value="1" @checked((bool) old('is_enabled', $voucher->is_enabled ?? true))>
                        <label class="form-check-label" for="is_enabled">Enable voucher when its scheduled start time arrives</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 pb-4">
        <button type="submit" class="btn btn-bili-hub"><i class="bi bi-check-circle me-1"></i> {{ $editing ? 'Save Changes' : 'Create Voucher' }}</button>
        <a href="{{ route('seller.vouchers.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const discountType = document.getElementById('discount_type');
    const scope = document.getElementById('product_scope');
    const percentField = document.getElementById('percentage-field');
    const fixedField = document.getElementById('fixed-field');
    const capField = document.getElementById('cap-field');
    const picker = document.getElementById('product-picker');
    const percentInput = document.getElementById('discount_percent');
    const fixedInput = document.getElementById('discount_amount');
    const capInput = document.getElementById('maximum_discount');

    function syncDiscount() {
        const percentage = discountType.value === 'percentage';
        percentField.classList.toggle('d-none', !percentage);
        fixedField.classList.toggle('d-none', percentage);
        capField.classList.toggle('d-none', !percentage);
        percentInput.disabled = !percentage;
        fixedInput.disabled = percentage;
        capInput.disabled = !percentage;
        percentInput.required = percentage;
        fixedInput.required = !percentage;
    }
    function syncProducts() {
        const selected = scope.value === 'selected';
        picker.classList.toggle('d-none', !selected);
        picker.querySelectorAll('input[type="checkbox"]').forEach(input => input.disabled = !selected);
    }
    discountType.addEventListener('change', syncDiscount);
    scope.addEventListener('change', syncProducts);
    syncDiscount();
    syncProducts();
});
</script>
