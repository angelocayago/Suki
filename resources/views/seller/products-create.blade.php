@extends('layouts.seller')

@section('title', 'Add Product')
@section('page-title', 'Add Product')
@section('page-subtitle', 'List a new item in your store')

@section('content')
@php
    $fieldClass = 'w-full rounded-xl border border-[#DDE6E1] bg-white px-4 py-3 text-sm text-[#34483F] outline-none transition focus:border-[#1F6F5B] focus:ring-4 focus:ring-[#DDF3EC]/70';
    $labelClass = 'mb-2 block text-sm font-semibold text-[#34483F]';
    $cardClass = 'space-y-5 rounded-2xl border border-[#DDE6E1] bg-white p-5 shadow-sm sm:p-6';
    $headingClass = 'text-xs font-bold uppercase tracking-wider text-[#1F6F5B]';
@endphp

<div class="mx-auto max-w-3xl" data-add-product-page="reference-v2">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold tracking-tight text-[#173F35]">Add Product</h2>
            <p class="mt-1 text-sm text-[#728078]">List a new item in your store</p>
        </div>
        <a href="{{ route('seller.products') }}" class="text-sm font-semibold text-[#1F6F5B] hover:underline">Back to Products</a>
    </div>

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <p class="font-semibold">Please review the fields below.</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form id="productForm" data-product-create method="POST" action="{{ route('seller.products.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        <section class="{{ $cardClass }}" aria-labelledby="basicHeading">
            <h3 id="basicHeading" class="{{ $headingClass }}">Basic Information</h3>
            <div>
                <label for="name" class="{{ $labelClass }}">Product Name <span class="text-red-500">*</span></label>
                <input id="name" name="name" type="text" required maxlength="150" value="{{ old('name') }}" placeholder="e.g. Wireless Bluetooth Earbuds" class="{{ $fieldClass }}" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" aria-describedby="name-error">
                <p id="name-error" class="mt-1 text-xs text-red-600">@error('name'){{ $message }}@enderror</p>
            </div>
            <div>
                <label for="description" class="{{ $labelClass }}">Description <span class="text-red-500">*</span></label>
                <textarea id="description" name="description" rows="4" required maxlength="3000" placeholder="Describe the product's features, materials, size, and use." class="{{ $fieldClass }} resize-y" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" aria-describedby="description-error">{{ old('description') }}</textarea>
                <p id="description-error" class="mt-1 text-xs text-red-600">@error('description'){{ $message }}@enderror</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                @include('seller.partials.product-category')
            </div>
            <div>
                <label for="brand" class="{{ $labelClass }}">Brand <span class="font-normal text-[#728078]">(optional)</span></label>
                <input id="brand" name="brand" type="text" maxlength="150" value="{{ old('brand') }}" placeholder="Enter brand name" class="{{ $fieldClass }}">
                @error('brand')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="{{ $cardClass }}" aria-labelledby="pricingHeading">
            <div>
                <h3 id="pricingHeading" class="{{ $headingClass }}">Pricing &amp; Stock</h3>
                <p class="mt-2 text-xs leading-5 text-[#728078]">Set a single price and stock count, or generate options below to manage each variant separately.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div data-simple-config>
                    <label for="price" class="{{ $labelClass }}">Regular Price (₱) <span class="text-red-500">*</span></label>
                    <input id="price" name="price" type="number" required min="0" max="42949672.95" step="0.01" value="{{ old('price') }}" placeholder="0.00" class="{{ $fieldClass }}">
                    @error('price')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div data-simple-config>
                    <label for="stock" class="{{ $labelClass }}">Stock Quantity <span class="text-red-500">*</span></label>
                    <input id="stock" name="stock" type="number" required min="0" max="4294967295" step="1" value="{{ old('stock') }}" placeholder="0" class="{{ $fieldClass }}">
                    @error('stock')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="low_stock_threshold" class="{{ $labelClass }}">Low-stock threshold</label>
                    <input id="low_stock_threshold" name="low_stock_threshold" type="number" min="0" step="1" value="{{ old('low_stock_threshold', \App\Support\SellerStock::threshold()) }}" class="{{ $fieldClass }}">
                    @error('low_stock_threshold')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="rounded-xl bg-[#F5F8F6] p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#728078]">SKU</p>
                    <p class="mt-2 text-sm text-[#34483F]">Generated automatically after saving</p>
                </div>
            </div>
        </section>

        @include('seller.partials.product-variations')

        <section class="{{ $cardClass }}" aria-labelledby="photosHeading">
            <div>
                <h3 id="photosHeading" class="{{ $headingClass }}">Product Photos</h3>
                <p class="mt-2 text-xs leading-5 text-[#728078]">Upload up to 5 images. Your first photo is the cover image. JPG, PNG or WebP, maximum 5 MB each.</p>
            </div>
            <input id="imagePicker" type="file" accept="image/jpeg,image/png,image/webp" multiple hidden>
            <input id="images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple hidden>
            <button id="addProductImages" data-photo-upload type="button" class="group flex min-h-48 w-full flex-col items-center justify-center rounded-2xl border-2 border-dashed border-[#C8D6CF] bg-[#F5F8F6] px-6 py-8 text-center transition hover:border-[#1F6F5B] hover:bg-[#EEF5F1] focus-visible:outline-2 focus-visible:outline-[#1F6F5B] disabled:opacity-50" aria-describedby="photo-help">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-[#1F6F5B] shadow-sm">
                    <svg aria-hidden="true" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 16V4m0 0L8 8m4-4l4 4M5 20h14" /></svg>
                </span>
                <span class="mt-3 text-sm font-bold text-[#173F35]">Add Images</span>
                <span class="mt-1 text-xs text-[#728078]">Click to upload product photos</span>
            </button>
            <div id="imagePreview" data-photo-preview-grid class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5"></div>
            <p id="imageUploadStatus" role="status" aria-live="polite" class="text-xs text-red-600"></p>
            @error('images')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <p id="photo-help" class="text-xs text-[#728078]">Choose files again after a validation error.</p>
        </section>

        @include('seller.partials.product-sale-pricing')
        @include('seller.partials.product-payment-methods')

        <details class="rounded-2xl border border-[#DDE6E1] bg-white p-5">
            <summary class="cursor-pointer text-sm font-semibold text-[#52635B]">Extra media &amp; package details <span class="font-normal">(optional)</span></summary>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="additional_image" class="{{ $labelClass }}">Supporting photo</label>
                    <input id="additional_image" name="additional_image" type="file" accept="image/jpeg,image/png,image/webp" class="w-full text-xs">
                    <p class="mt-1 text-xs text-[#728078]">JPG, PNG or WebP, up to 2 MB.</p>
                    @error('additional_image')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="product_video" class="{{ $labelClass }}">Product video</label>
                    <input id="product_video" name="product_video" type="file" accept="video/mp4,video/quicktime,video/x-msvideo,video/webm" class="w-full text-xs">
                    <p class="mt-1 text-xs text-[#728078]">MP4, MOV, AVI or WebM, up to 10 MB.</p>
                    @error('product_video')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                @foreach(['weight' => 'Package Weight (kg)', 'length' => 'Length (cm)', 'width' => 'Width (cm)', 'height' => 'Height (cm)'] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="{{ $labelClass }}">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="number" min="0" step="0.01" value="{{ old($field) }}" class="{{ $fieldClass }}">
                        @error($field)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </details>

        <section class="{{ $cardClass }}" aria-labelledby="actionsHeading">
            <h3 id="actionsHeading" class="{{ $headingClass }}">Product Actions</h3>
            <div>
                <label for="status" class="{{ $labelClass }}">Product Status</label>
                <select id="status" name="status" class="{{ $fieldClass }}">
                    <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                </select>
                @error('status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <button id="submitProductButton" type="submit" name="submit_action" value="publish" class="w-full rounded-xl bg-[#1F6F5B] px-6 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#173F35] disabled:opacity-60">Add Product</button>
            <div class="flex items-center justify-between gap-3">
                <button type="submit" name="submit_action" value="draft" formnovalidate class="rounded-xl border border-[#DDE6E1] px-4 py-2.5 text-sm font-semibold text-[#52635B] hover:bg-[#F5F8F6]">Save as Draft</button>
                <a href="{{ route('seller.products') }}" class="text-sm font-semibold text-[#728078] hover:text-[#1F6F5B]">Cancel</a>
            </div>
        </section>
    </form>
</div>
@endsection
