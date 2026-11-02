@php($item = [])
@include('seller.partials.product-form-style')
<style>
.seller-product-form [hidden]{display:none!important}.spf-heading{display:flex;justify-content:space-between;gap:16px;align-items:start}.spf-heading h3{margin-bottom:6px}.spf-generated{display:grid;gap:12px;margin-top:18px}.spf-generated article{background:#fafcfb;border:1px solid #dde6e1;border-radius:12px;padding:16px;min-width:0}.spf-generated h4{font-weight:600;overflow-wrap:anywhere;margin-bottom:12px}.spf-generated .spf-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.spf-generate{width:100%;border-style:dashed!important;margin-top:6px}.spf-category{border:1px solid #dde6e1;background:#f5f8f6;border-radius:12px;padding:12px;margin-top:12px}.spf-mode-note{padding:10px;background:#f5f8f6;border-radius:10px}.seller-product-form .spf-option-row{grid-template-columns:minmax(0,1fr) minmax(0,2fr) auto}.seller-product-form [data-remove-photo]{margin-top:8px}@media(max-width:600px){.seller-product-form .spf-option-row,.spf-generated .spf-grid{grid-template-columns:1fr}.spf-heading{flex-wrap:wrap}.seller-product-form .spf-option-row button{grid-column:auto}}
</style>
<div class="seller-product-form">
    <header class="spf-intro"><div><nav aria-label="Breadcrumb"><a href="{{ route('seller.products') }}">Products</a> / Add Product</nav><h2>Add Product</h2><p>Create a simple product or give each option combination its own price and stock.</p></div><a href="{{ route('seller.products') }}">Back to Products</a></header>
    @if($errors->any())<div class="spf-errors" role="alert"><strong>Please review the following fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p>Your details are retained. Choose image files again before resubmitting.</p></div>@endif
    <form id="universalProductForm" method="POST" action="{{ route('seller.products.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="creation_token" value="{{ $creationToken }}">
        <input type="hidden" name="product_mode" id="productMode" value="simple">
        <section class="spf-card" aria-labelledby="basicHeading">
            <h3 id="basicHeading">Basic Information</h3>
            @include('seller.partials.product-form-field', ['field'=>'name', 'label'=>'Product Name', 'required'=>true, 'maxLength'=>150])
            <label for="description">Description *</label><textarea id="description" name="description" rows="5" maxlength="3000" required aria-describedby="description-error">{{ old('description') }}</textarea><p id="description-error" class="spf-error">@error('description'){{ $message }}@enderror</p>
            <div class="spf-category"><strong>Business category: {{ $assignedCategory?->name ?? 'Not assigned' }}</strong><p>Only approved product categories are available. Options are independent of category.</p>
                @if($categories->count() === 1)
                    <p>{{ $categories->first()->name }}</p><input type="hidden" name="category_id" value="{{ $categories->first()->id }}">
                @else
                    <label for="category_id">Product Category *</label><select id="category_id" name="category_id" required><option value="">Select a permitted category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id') === (string)$category->id)>{{ $category->display_name ?? $category->name }}</option>@endforeach</select>
                @endif
                <p class="spf-error">@error('category_id'){{ $message }}@enderror</p>
                @if($categories->isEmpty())<p role="alert">No authorized product category is available. Ask the administrator to review your assigned category.</p>@endif
            </div>
            @include('seller.partials.product-form-field', ['field'=>'brand', 'label'=>'Brand (optional)', 'maxLength'=>100])
        </section>
        <section class="spf-card" aria-labelledby="pricingHeading">
            <h3 id="pricingHeading">Pricing &amp; Stock</h3><p id="pricingHelp">Set a single price and stock count, or generate options below to price every variant separately.</p>
            <div id="simplePricing" class="spf-grid">
                @include('seller.partials.product-form-field', ['field'=>'price', 'label'=>'Price (PHP)', 'type'=>'number', 'step'=>'0.01', 'maximum'=>'21474836.47', 'required'=>true])
                @include('seller.partials.product-form-field', ['field'=>'stock', 'label'=>'Stock Quantity', 'type'=>'number', 'step'=>'1', 'maximum'=>'2147483647', 'required'=>true])
                <div><label for="sku">SKU (optional)</label><input id="sku" name="sku" value="{{ old('sku') }}" maxlength="50" placeholder="Generated automatically after saving"></div>
            </div>
            @include('seller.partials.product-form-field', ['field'=>'weight', 'label'=>'Product Weight (kg, optional)', 'type'=>'number', 'step'=>'0.001', 'maximum'=>'2147483.647'])
            <p>Weight per unit. A variant can override it below. Delivery fees continue to use the existing SUKI SHOP policy.</p>
        </section>
        <section class="spf-card" aria-labelledby="optionsHeading">
            <div class="spf-heading"><div><h3 id="optionsHeading">Options &amp; Variants</h3><p>Use your own option names and comma-separated values. Up to 3 groups, 20 values per group and 50 combinations.</p></div><button type="button" id="addOption">+ Add Option</button></div>
            <div id="optionGroups"></div>
            <button type="button" id="generateVariants" class="spf-generate" hidden>Generate Variant Combinations</button>
            <p id="optionFeedback" class="spf-error" role="status" aria-live="polite"></p>
            <p id="variantCount" aria-live="polite">No options needed? Keep the single price and stock above.</p>
            <div id="generatedVariants" class="spf-generated"></div>
            <noscript><p>Simple products can be submitted without JavaScript. Enable JavaScript to generate custom options.</p></noscript>
        </section>
        <section class="spf-card" aria-labelledby="photosHeading">
            <h3 id="photosHeading">Product Photos</h3><p>Up to 8 JPG, PNG or WebP photos, 5 MB each. The first selected photo is the cover. Photos are shared by this product's variants.</p>
            <label for="images" class="spf-upload">Click to upload photos<input id="images" name="images[]" type="file" multiple accept="image/jpeg,image/png,image/webp" aria-describedby="photoHelp imageUploadStatus"></label>
            <p id="photoHelp">Preview or remove pending photos below. Files must be selected again after a server validation error.</p><div id="imagePreview" class="spf-photos"></div><p id="imageUploadStatus" class="spf-error" role="status" aria-live="polite"></p>
        </section>
        <details class="spf-card"><summary>Package Details (optional)</summary><p>Package dimensions use the existing default-variant fields.</p><div class="spf-grid">@foreach(['length'=>'Length (cm)', 'width'=>'Width (cm)', 'height'=>'Height (cm)'] as $field=>$label)@include('seller.partials.product-form-field', ['field'=>$field, 'label'=>$label, 'type'=>'number', 'step'=>'0.01', 'maximum'=>'214748364'])@endforeach</div></details>
        <section class="spf-card spf-actions" aria-label="Product actions">
            <label for="status">Availability after approval</label><select id="status" name="status"><option value="active" @selected(old('status','active') === 'active')>Active</option><option value="inactive" @selected(old('status') === 'inactive')>Inactive</option></select>
            <p>Products are submitted for Admin review before appearing in the marketplace. Inactive products remain unavailable even after approval.</p>
            <button type="submit" class="spf-primary" @disabled($categories->isEmpty())>Submit Product for Review</button><a href="{{ route('seller.products') }}">Cancel</a>
        </section>
    </form>
</div>
@include('seller.partials.product-option-engine')
@include('seller.partials.product-create-script')
