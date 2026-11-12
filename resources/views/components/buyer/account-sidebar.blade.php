@props(['active' => 'profile'])

@php
    $buyer = auth()->user();
    $accountLinks = [
        'profile' => ['Profile', route('buyer.account'), 'user'],
        'addresses' => ['Addresses', route('buyer.addresses'), 'map-pin'],
        'password' => ['Change Password', route('buyer.account', ['section' => 'password']), 'lock-keyhole'],
        'purchases' => ['My Purchases', route('buyer.my-orders'), 'clipboard-list'],
        'wishlist' => ['My Wishlist', route('buyer.wishlist'), 'heart'],
        'following' => ['Following', route('buyer.stores.following'), 'users'],
        'messages' => ['Messages', route('buyer.messages.index'), 'message-circle'],
    ];
@endphp

<aside class="buyer-account-sidebar" aria-label="Buyer account" data-buyer-account-sidebar>
    <div class="buyer-account-identity">
        <x-profile-avatar :user="$buyer" size="h-12 w-12 shrink-0" :circular="true" />
        <div>
            <p class="buyer-account-name">{{ $buyer?->full_name ?: $buyer?->name }}</p>
            <a href="{{ route('buyer.account') }}#editProfileModal" class="buyer-account-edit" onclick="if (typeof openEditProfile === 'function') { event.preventDefault(); openEditProfile(); }">
                <i data-lucide="pencil" aria-hidden="true"></i> Edit Profile
            </a>
        </div>
    </div>

    <details class="buyer-account-navigation" data-buyer-account-navigation open>
        <summary>Account navigation <i data-lucide="chevron-down" aria-hidden="true"></i></summary>
        <nav aria-label="My Account">
            <p class="buyer-account-nav-heading">My Account</p>
            @foreach($accountLinks as $key => [$label, $url, $icon])
                <a href="{{ $url }}" @if($active === $key) aria-current="page" @endif class="buyer-account-link" @if($key === 'password') data-account-password-link @endif>
                    <i data-lucide="{{ $icon }}" aria-hidden="true"></i>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </nav>
    </details>
</aside>
