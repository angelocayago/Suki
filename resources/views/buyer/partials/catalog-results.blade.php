@forelse($products as $product)
    <article class="group relative overflow-hidden rounded-xl border border-gray-200 bg-white transition duration-200 hover:-translate-y-0.5 hover:border-[#D5EAE2] hover:shadow-lg">
        <a href="{{ route('buyer.product', ['slug' => $product['slug']]) }}" class="block">
            <div class="aspect-square overflow-hidden bg-gray-100"><img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105"></div>
            <div class="p-3.5 sm:p-4"><p class="mb-1 text-[11px] font-medium text-[#1F6F5B]">{{ $product['category'] }}</p><h3 class="min-h-[40px] text-sm font-medium leading-5 text-gray-800">{{ $product['name'] }}</h3><p class="mt-3 text-lg font-bold text-[#1F6F5B]">₱{{ $product['price'] }}</p><p class="mt-2 text-xs text-gray-500">{{ $product['sold'] }} sold · ₱50 shipping per seller</p></div>
        </a>
    </article>
@empty
    <p class="col-span-full rounded-xl border border-dashed border-gray-200 p-8 text-center text-sm text-gray-500">No products match these filters.</p>
@endforelse
