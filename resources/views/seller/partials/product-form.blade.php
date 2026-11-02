@php
    $item = $editing ? $product : [];
    $title = $editing ? 'Edit Product' : 'Add Product';
    $variantRows = old('variants', $item['variants'] ?? []);
    $optionNames = old('variation_name', $item['variation_names'] ?? ['']);
    $optionValues = old('variation_value', $item['variation_values'] ?? ['']);
@endphp
@include('seller.partials.product-form-style')
<div class="seller-product-form">
    <header class="spf-intro">
        <div><nav aria-label="Breadcrumb"><a href="{{ route('seller.products') }}">Products</a> / {{ $title }}</nav>
        <h2>{{ $title }}</h2><p>{{ $editing ? 'Update your product information and stock.' : 'List a new item in your store.' }}</p></div>
        <a href="{{ route('seller.products') }}">Back to Products</a>
    </header>
    @if($errors->any())
        <div class="spf-errors" role="alert"><strong>Please review the following fields.</strong><ul>
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul><p>Choose image files again before resubmitting.</p></div>
    @endif
    @if($legacyProduct ?? false)
        <p class="spf-errors" role="alert">This legacy session product must be recreated as a database product before it can be updated.</p>
    @endif
    <form id="productForm" method="POST" action="{{ $editing ? route('seller.products.update', request()->route('product')) : route('seller.products.store') }}" enctype="multipart/form-data">
        @csrf
        @if($editing) @method('PUT') @endif
        <section class="spf-card" aria-labelledby="basicHeading">
            <h3 id="basicHeading">Basic Information</h3>
            @include('seller.partials.product-form-field', ['field'=>'name', 'label'=>'Product Name', 'required'=>true, 'maxLength'=>150])
            <label for="description">Description <span aria-hidden="true">*</span></label>
            <textarea id="description" name="description" rows="4" required maxlength="3000" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" aria-describedby="description-error">{{ old('description', $item['description'] ?? '') }}</textarea>
            <p class="spf-error" id="description-error">@error('description'){{ $message }}@enderror</p>
            <div class="spf-grid">
                <div><label for="category_id">Product Category *</label>
                    <select id="category_id" name="category_id" required aria-describedby="category-help category_id-error" aria-invalid="{{ $errors->has('category_id') ? 'true' : 'false' }}">
                        <option value="">Select a category</option>
                        @foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id', $item['category_id'] ?? '') === (string) $category->id)>{{ $category->display_name ?? $category->name }}</option>@endforeach
                    </select>
                    <p id="category-help">Business category: {{ $assignedCategory?->name ?? 'Not assigned' }}. Select a permitted product subcategory.</p>
                    <p class="spf-error" id="category_id-error">@error('category_id'){{ $message }}@enderror</p>
                </div>
                @include('seller.partials.product-form-field', ['field'=>'brand', 'label'=>'Brand (optional)', 'maxLength'=>100])
            </div>
        </section>
        <section class="spf-card" aria-labelledby="pricingHeading">
            <h3 id="pricingHeading">Pricing &amp; Stock</h3>
            <p>Set the default variant's price and quantity. Additional combinations have their own stock below.</p>
            <div class="spf-stock-row" data-stock-row>
                <div class="spf-grid">
                    @include('seller.partials.product-form-field', ['field'=>'price', 'label'=>'Price (PHP)', 'type'=>'number', 'step'=>'0.01', 'maximum'=>'99999999.99', 'required'=>true])
                    @include('seller.partials.product-form-field', ['field'=>'stock', 'label'=>'Stock Quantity', 'type'=>'number', 'step'=>'1', 'maximum'=>'4294967295', 'required'=>true])
                    <div><label for="sku">SKU</label><input id="sku" name="sku" value="{{ $item['sku'] ?? '' }}" readonly placeholder="Generated automatically after saving"><p>Assigned by SUKI SHOP. Existing SKUs are preserved.</p></div>
                    <div><label for="status">Product Status</label><select id="status" name="status" data-variant-status>
                        <option value="active" @selected(old('status', $item['status'] ?? 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $item['status'] ?? 'active') === 'inactive')>Inactive</option>
                    </select><p class="spf-error">@error('status'){{ $message }}@enderror</p></div>
                </div>
                <output data-stock-indicator aria-live="polite"></output>
            </div>
            <p>Active variants: 0 units = Out of Stock; 1–10 = Low Stock; 11+ = In Stock.</p>
        </section>
        <section class="spf-card" aria-labelledby="variantsHeading">
            <h3 id="variantsHeading">Product Variations</h3>
            <p>Describe the default variant's options (for example, Color / Blue and Size / Large).</p>
            <div id="primaryOptions">
                @foreach($optionNames ?: [''] as $index => $name)
                    <div class="spf-option-row"><label>Option name<input name="variation_name[]" maxlength="100" value="{{ $name }}" placeholder="Color"></label><label>Option value<input name="variation_value[]" maxlength="255" value="{{ $optionValues[$index] ?? '' }}" placeholder="Blue"></label><button type="button" data-remove-option aria-label="Remove option">Remove</button></div>
                @endforeach
            </div>
            <button type="button" id="addPrimaryOption">Add option</button>
            @include('seller.partials.additional-variants')
        </section>
        <section class="spf-card" aria-labelledby="photosHeading">
            <h3 id="photosHeading">Product Photos</h3>
            <p>Upload up to 20 JPG, PNG or WebP images per save, up to 4 MB each. The first saved image is the cover.</p>
            @if($editing && !empty($item['images']))
                <div class="spf-photos" aria-label="Saved photos">
                    @foreach($item['images'] as $index => $image)
                        <figure><img src="{{ $image }}" alt="Saved product photo {{ $index + 1 }}" loading="lazy"><figcaption>{{ ($imageColors[$index] ?? null) ?: 'All colors' }}</figcaption></figure>
                    @endforeach
                </div>
                <p>Saved photos and their color associations are retained. New photos are appended.</p>
            @endif
            <label for="images" class="spf-upload">Add Images<input id="images" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple aria-describedby="photo-help imageUploadStatus"></label>
            <p id="photo-help">Selecting files replaces the pending selection. Choose files again after a validation error.</p>
            <div id="imagePreview" class="spf-photos"></div>
            <p id="imageUploadStatus" class="spf-error" role="status" aria-live="polite"></p>

            @include('seller.partials.product-video-fields')
        </section>
        <details class="spf-card">
            <summary>Package Details (optional)</summary>
            <p>Dimensions and weight apply to the default variant.</p>
            <div class="spf-grid">
                @foreach(['weight'=>'Package Weight (kg)', 'length'=>'Length (cm)', 'width'=>'Width (cm)', 'height'=>'Height (cm)'] as $field => $label)
                    @include('seller.partials.product-form-field', ['field'=>$field, 'label'=>$label, 'type'=>'number', 'step'=>'0.01', 'maximum'=>$field === 'weight' ? '4294967' : '429496729'])
                @endforeach
            </div>
            <p>Delivery is managed through SUKI SHOP's existing fulfillment process.</p>
        </details>
        <section class="spf-card spf-actions" aria-label="Product actions">
            <p>{{ $editing ? 'Material changes are submitted for review. Stock-only updates keep the current review status.' : 'New products are submitted for review before appearing in the marketplace.' }}</p>
            @if($editing)<p>Current review status: {{ $item['moderation_status'] ?? 'PENDING' }} @if(!empty($item['moderation_reason']))— {{ $item['moderation_reason'] }}@endif</p>@endif
            <button class="spf-primary" type="submit" @disabled($legacyProduct ?? false)>{{ $editing ? 'Save Changes' : 'Add Product' }}</button>
            <a href="{{ route('seller.products') }}">Cancel</a>
        </section>
    </form>
</div>
@include('seller.partials.product-form-script')

@include('seller.partials.product-video-script')
