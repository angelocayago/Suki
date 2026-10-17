<div class="min-w-0">
    <label for="category" class="mb-2 block text-xs font-semibold text-[#34483F]">Main Category / Line of Business</label>
    <input id="category" type="text" readonly value="{{ $sellerCategory }}"
        class="min-h-11 w-full rounded-xl border border-[#DDE6E1] bg-[#F7F9F8] px-4 py-2 text-sm text-[#52635B]">
    <p class="mt-1.5 text-[10px] leading-4 text-[#8C9992]">Assigned during registration. This category cannot be changed here.</p>
</div>
<div class="min-w-0">
    <label for="subcategory" class="mb-2 block text-xs font-semibold text-[#34483F]">Subcategory <span class="text-red-500">*</span></label>
    <select id="subcategory" name="subcategory" required
        aria-invalid="{{ $errors->has('subcategory') ? 'true' : 'false' }}" aria-describedby="subcategory-help"
        @disabled(!$categoryAvailable || $productSubcategories->isEmpty())
        class="h-11 w-full min-w-0 rounded-xl border border-[#DDE6E1] bg-white px-4 text-sm text-[#34483F] focus:border-[#1F6F5B] focus:ring-4 focus:ring-[#DDF3EC]/70">
        <option value="">Select subcategory</option>
        @foreach($productSubcategories as $subcategory)
            <option value="{{ $subcategory->id }}" @selected((string) old('subcategory', $product['subcategory_id'] ?? '') === (string) $subcategory->id)>{{ $subcategory->name }}</option>
        @endforeach
    </select>
    <div id="subcategory-help" class="mt-1.5 text-xs leading-5">
        @if(!$categoryAvailable || $productSubcategories->isEmpty())
            <p class="text-amber-700">No supported subcategories are configured for your assigned category. Contact support; your assignment has not been changed.</p>
        @elseif($legacyCategory ?? false)
            <p class="text-amber-700">Existing classification: {{ $product['category'] }}{{ !empty($product['subcategory']) ? ' / '.$product['subcategory'] : '' }}. It has been preserved. Select a supported subcategory to explicitly reclassify this product when saving.</p>
        @endif
        @error('subcategory')<p class="text-red-600" role="alert">{{ $message }}</p>@enderror
    </div>
</div>
