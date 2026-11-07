<div id="buyerActionFeedback" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" aria-live="polite">
    @if($errors->any())<div role="alert" class="my-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><span aria-hidden="true">!</span> @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach<button type="button" data-dismiss-feedback aria-label="Dismiss error">Dismiss</button></div>@endif
    @foreach(['error' => 'alert', 'info' => 'status'] as $kind => $role)@if(session($kind) && ($kind !== 'error' || !request()->routeIs('buyer.cart', 'buyer.wishlist')))<div role="{{ $role }}" class="my-4 rounded-xl border {{ $kind === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-900' }} p-4 text-sm">{{ session($kind) }}</div>@endif @endforeach
    @if(session('success') && !request()->routeIs('buyer.product', 'buyer.cart', 'buyer.wishlist'))<div role="status" data-feedback-success class="my-4 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">{{ session('success') }}</div>@endif
    <div data-feedback-live hidden role="alert" class="my-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"></div>
    @if(request()->routeIs('buyer.product') && session('success'))<a class="text-sm font-semibold text-[#1F6F5B] underline" href="{{ route('buyer.cart') }}">View cart</a>@endif
</div>
