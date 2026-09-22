<?php

use App\Models\User;
use App\Models\CartItem;
use App\Models\WishlistItem;
use App\Models\Order;
use App\Models\OrderItem;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;


// =====================================================
// PRODUCT DATA
// =====================================================

$products = [

    'shoulder-bag' => [
        'name' => 'Minimalist Shoulder Bag',
        'price' => '399',
        'old_price' => '599',
        'discount' => '33% OFF',
        'rating' => '4.9',
        'ratings' => '328',
        'sold' => '1.2k',
        'category' => 'Fashion',
        'stock' => 45,
        'colors' => ['Black', 'Beige', 'Brown'],

        'image' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=1000&q=85',

        'images' => [
            'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?auto=format&fit=crop&w=1000&q=85',
        ],

        'seller' => 'Everyday Finds PH',
        'seller_rating' => '98%',
        'seller_products' => '1.8k',

        'description' => 'A simple everyday shoulder bag designed for casual use. It features a clean minimalist design with enough space for your daily essentials.',

        'features' => [
            'Lightweight and comfortable for everyday use.',
            'Spacious main compartment.',
            'Adjustable shoulder strap.',
            'Suitable for casual and everyday outfits.',
        ],
    ],

    'wireless-headphones' => [
        'name' => 'Wireless Headphones',
        'price' => '899',
        'old_price' => '1,299',
        'discount' => '31% OFF',
        'rating' => '4.8',
        'ratings' => '214',
        'sold' => '856',
        'category' => 'Electronics',
        'stock' => 32,
        'colors' => ['Black', 'White'],

        'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=1000&q=85',

        'images' => [
            'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1484704849700-f032a568e944?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1546435770-a3e426bf472b?auto=format&fit=crop&w=1000&q=85',
        ],

        'seller' => 'Tech Finds PH',
        'seller_rating' => '97%',
        'seller_products' => '856',

        'description' => 'Comfortable wireless headphones with a clean modern design, perfect for music, calls, and everyday entertainment.',

        'features' => [
            'Wireless Bluetooth connectivity.',
            'Comfortable over-ear design.',
            'Built-in microphone for calls.',
            'Long-lasting battery life.',
        ],
    ],

    'ceramic-home-set' => [
        'name' => 'Ceramic Home Set',
        'price' => '549',
        'old_price' => '799',
        'discount' => '31% OFF',
        'rating' => '4.7',
        'ratings' => '189',
        'sold' => '642',
        'category' => 'Home',
        'stock' => 28,
        'colors' => ['White', 'Cream'],

        'image' => 'https://images.unsplash.com/photo-1610701596007-11502861dcfa?auto=format&fit=crop&w=1000&q=85',

        'images' => [
            'https://images.unsplash.com/photo-1610701596007-11502861dcfa?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1578749556568-bc2c40e68b61?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1581783898377-1c85bf937427?auto=format&fit=crop&w=1000&q=85',
        ],

        'seller' => 'Home Finds PH',
        'seller_rating' => '96%',
        'seller_products' => '642',

        'description' => 'A simple ceramic home set designed to add a clean and cozy touch to your dining and living space.',

        'features' => [
            'Minimalist ceramic design.',
            'Easy to clean and maintain.',
            'Suitable for everyday use.',
            'Perfect for modern home interiors.',
        ],
    ],

    'everyday-sneakers' => [
        'name' => 'Everyday Sneakers',
        'price' => '799',
        'old_price' => '1,099',
        'discount' => '27% OFF',
        'rating' => '4.9',
        'ratings' => '402',
        'sold' => '2.1k',
        'category' => 'Fashion',
        'stock' => 41,
        'colors' => ['White', 'Black', 'Gray'],

        'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1000&q=85',

        'images' => [
            'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1552346154-21d32810aba3?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=1000&q=85',
        ],

        'seller' => 'Streetwear PH',
        'seller_rating' => '99%',
        'seller_products' => '1.2k',

        'description' => 'Comfortable everyday sneakers with a simple design that works well with casual outfits.',

        'features' => [
            'Lightweight construction.',
            'Comfortable everyday fit.',
            'Durable rubber outsole.',
            'Suitable for casual outfits.',
        ],
    ],

    'skincare-essentials' => [
        'name' => 'Skincare Essentials Set',
        'price' => '459',
        'old_price' => '699',
        'discount' => '34% OFF',
        'rating' => '4.8',
        'ratings' => '276',
        'sold' => '934',
        'category' => 'Beauty',
        'stock' => 36,
        'colors' => ['Original Set'],

        'image' => 'https://images.unsplash.com/photo-1556229010-6c3f2c9ca5f8?auto=format&fit=crop&w=1000&q=85',

        'images' => [
            'https://images.unsplash.com/photo-1556229010-6c3f2c9ca5f8?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=1000&q=85',
        ],

        'seller' => 'Glow Essentials PH',
        'seller_rating' => '98%',
        'seller_products' => '934',

        'description' => 'A simple skincare essentials set for a clean and easy everyday routine.',

        'features' => [
            'Complete everyday skincare set.',
            'Easy-to-use products.',
            'Suitable for daily routines.',
            'Compact and travel-friendly.',
        ],
    ],

    'analog-watch' => [
        'name' => 'Classic Analog Watch',
        'price' => '699',
        'old_price' => '999',
        'discount' => '30% OFF',
        'rating' => '4.8',
        'ratings' => '167',
        'sold' => '721',
        'category' => 'Fashion',
        'stock' => 24,
        'colors' => ['Black', 'Brown'],

        'image' => 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?auto=format&fit=crop&w=1000&q=85',

        'images' => [
            'https://images.unsplash.com/photo-1524805444758-089113d48a6d?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=1000&q=85',
        ],

        'seller' => 'Timeless Finds',
        'seller_rating' => '97%',
        'seller_products' => '721',

        'description' => 'A classic analog watch with a clean and timeless design suitable for everyday wear.',

        'features' => [
            'Classic analog display.',
            'Minimalist design.',
            'Adjustable wrist strap.',
            'Suitable for casual and formal outfits.',
        ],
    ],

    'bluetooth-speaker' => [
        'name' => 'Portable Bluetooth Speaker',
        'price' => '649',
        'old_price' => '899',
        'discount' => '28% OFF',
        'rating' => '4.7',
        'ratings' => '143',
        'sold' => '534',
        'category' => 'Electronics',
        'stock' => 19,
        'colors' => ['Black', 'Blue'],

        'image' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?auto=format&fit=crop&w=1000&q=85',

        'images' => [
            'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1589003077984-894e133dabab?auto=format&fit=crop&w=1000&q=85',
        ],

        'seller' => 'Audio Hub PH',
        'seller_rating' => '96%',
        'seller_products' => '534',

        'description' => 'A compact portable Bluetooth speaker designed for music at home, outdoors, or while traveling.',

        'features' => [
            'Portable and lightweight.',
            'Bluetooth wireless connection.',
            'Clear audio output.',
            'Rechargeable battery.',
        ],
    ],

    'table-lamp' => [
        'name' => 'Modern Table Lamp',
        'price' => '499',
        'old_price' => '799',
        'discount' => '38% OFF',
        'rating' => '4.6',
        'ratings' => '121',
        'sold' => '438',
        'category' => 'Home',
        'stock' => 17,
        'colors' => ['White', 'Black'],

        'image' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=1000&q=85',

        'images' => [
            'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=1000&q=85',
            'https://images.unsplash.com/photo-1534073828943-f801091bb18c?auto=format&fit=crop&w=1000&q=85',
        ],

        'seller' => 'Home Lights PH',
        'seller_rating' => '95%',
        'seller_products' => '438',

        'description' => 'A modern table lamp that adds warm and practical lighting to desks, bedrooms, and living spaces.',

        'features' => [
            'Modern minimalist design.',
            'Compact tabletop size.',
            'Suitable for bedrooms and desks.',
            'Easy to use.',
        ],
    ],
];


// =====================================================
// PUBLIC SUKI SHOP LANDING PAGE
// =====================================================

Route::get('/', function () {
    return view('landing');
})->name('landing');


// =====================================================
// BUYER HOME
// =====================================================

Route::get('/home', function () {
    return view('buyer.home');
})->name('buyer.home');


// =====================================================
// BUYER SHOP
// =====================================================

Route::get('/shop', function () use ($products) {

    return view('buyer.shop', [
        'products' => $products,
    ]);

})->name('buyer.shop');


// =====================================================
// PRODUCT DETAILS
// =====================================================

Route::get('/product/{slug}', function ($slug) use ($products) {

    if (!isset($products[$slug])) {
        abort(404);
    }

    return view('buyer.product-details', [
        'product' => $products[$slug],
        'slug' => $slug,
    ]);

})->name('buyer.product');


// =====================================================
// BUYER AUTH CHECK
// =====================================================

$requireBuyer = function () {

    if (!auth()->check()) {

        $intendedUrl = request()->isMethod('GET')
            ? request()->fullUrl()
            : url()->previous();

        if (!str_starts_with($intendedUrl, url('/'))) {
            $intendedUrl = route('buyer.home');
        }

        session()->put('url.intended', $intendedUrl);

        return redirect()
            ->route('login')
            ->with(
                'error',
                'Please log in or create a buyer account to continue.'
            );
    }

    $user = auth()->user();

    if (
        $user->role !== 'buyer' ||
        $user->status !== 'active'
    ) {
        auth()->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with(
                'error',
                'This account does not have access to the Buyer portal.'
            );
    }

    return null;
};


// =====================================================
// SHOPPING CART
// =====================================================

Route::get('/cart', function () use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $cart = auth()
        ->user()
        ->cartItems()
        ->get()
        ->mapWithKeys(function ($item) {
            return [
                $item->product_slug => [
                    'name' => $item->product_name,
                    'price' => (float) $item->price,
                    'image' => $item->image,
                    'quantity' => $item->quantity,
                ],
            ];
        })
        ->toArray();

    return view('buyer.cart', [
        'cart' => $cart,
    ]);

})->name('buyer.cart');


// =====================================================
// ADD TO CART
// =====================================================

Route::post('/cart/add/{slug}', function (Request $request, $slug) use ($products, $requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    if (!isset($products[$slug])) {
        abort(404);
    }

    $user = auth()->user();

    $product = $products[$slug];

    $maxStock = (int) $product['stock'];

    $requestedQuantity = (int) $request->input('quantity', 1);

    $requestedQuantity = max(
        1,
        min($requestedQuantity, $maxStock)
    );

    $cartItem = CartItem::where('user_id', $user->id)
        ->where('product_slug', $slug)
        ->first();

    if ($cartItem) {

        $newQuantity = $cartItem->quantity + $requestedQuantity;

        $newQuantity = min(
            $newQuantity,
            $maxStock
        );

        $cartItem->update([
            'quantity' => $newQuantity,
        ]);

    } else {

        CartItem::create([
            'user_id' => $user->id,
            'product_slug' => $slug,
            'product_name' => $product['name'],
            'price' => $product['price'],
            'image' => $product['image'] ?? null,
            'quantity' => $requestedQuantity,
        ]);
    }

    return redirect()
        ->back()
        ->with(
            'success',
            $product['name'] . ' added to cart!'
        );

})->name('buyer.cart.add');

// =====================================================
// INCREASE CART QUANTITY
// =====================================================

Route::post('/cart/increase/{slug}', function ($slug) use ($products, $requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    if (!isset($products[$slug])) {
        abort(404);
    }

    $user = auth()->user();

    $cartItem = CartItem::where('user_id', $user->id)
        ->where('product_slug', $slug)
        ->first();

    if (!$cartItem) {
        abort(404);
    }

    $maxStock = (int) $products[$slug]['stock'];

    if ($cartItem->quantity < $maxStock) {
        $cartItem->increment('quantity');
    }

    return redirect()->back();

})->name('buyer.cart.increase');


// =====================================================
// DECREASE CART QUANTITY
// =====================================================

Route::post('/cart/decrease/{slug}', function ($slug) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $user = auth()->user();

    $cartItem = CartItem::where('user_id', $user->id)
        ->where('product_slug', $slug)
        ->first();

    if (!$cartItem) {
        abort(404);
    }

    if ($cartItem->quantity > 1) {

        $cartItem->decrement('quantity');

    } else {

        $cartItem->delete();
    }

    return redirect()->back();

})->name('buyer.cart.decrease');

// =====================================================
// REMOVE FROM CART
// =====================================================

Route::post('/cart/remove/{slug}', function ($slug) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $user = auth()->user();

    $cartItem = CartItem::where('user_id', $user->id)
        ->where('product_slug', $slug)
        ->first();

    if ($cartItem) {
        $cartItem->delete();
    }

    return redirect()
        ->back()
        ->with(
            'success',
            'Item removed from cart.'
        );

})->name('buyer.cart.remove');

// =====================================================
// UPDATE CART QUANTITY
// =====================================================

Route::post('/cart/update/{slug}', function ($slug) use ($products, $requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    if (!isset($products[$slug])) {
        abort(404);
    }

    $user = auth()->user();

    $cartItem = CartItem::where('user_id', $user->id)
        ->where('product_slug', $slug)
        ->first();

    if (!$cartItem) {
        abort(404);
    }

    $quantity = (int) request()->input('quantity', 1);

    $maxStock = (int) $products[$slug]['stock'];

    $quantity = max(
        1,
        min($quantity, $maxStock)
    );

    $cartItem->update([
        'quantity' => $quantity,
    ]);

    return redirect()
        ->back()
        ->with(
            'success',
            'Cart updated successfully.'
        );

})->name('buyer.cart.update');

// =====================================================
// CLEAR CART
// =====================================================

Route::post('/cart/clear', function () use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $user = auth()->user();

    CartItem::where('user_id', $user->id)
        ->delete();

    return redirect()
        ->route('buyer.cart')
        ->with(
            'success',
            'Cart cleared.'
        );

})->name('buyer.cart.clear');

// =====================================================
// WISHLIST
// =====================================================

Route::get('/wishlist', function () use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $wishlist = auth()
        ->user()
        ->wishlistItems()
        ->get()
        ->mapWithKeys(function ($item) {
            return [
                $item->product_slug => [
                    'name' => $item->product_name,
                    'price' => (float) $item->price,
                    'image' => $item->image,
                ],
            ];
        })
        ->toArray();

    return view('buyer.wishlist', [
        'wishlist' => $wishlist,
    ]);

})->name('buyer.wishlist');

// =====================================================
// ADD TO WISHLIST
// =====================================================

Route::post('/wishlist/add/{slug}', function ($slug) use ($products, $requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    if (!isset($products[$slug])) {
        abort(404);
    }

    $user = auth()->user();

    $product = $products[$slug];

    WishlistItem::updateOrCreate(
        [
            'user_id' => $user->id,
            'product_slug' => $slug,
        ],
        [
            'product_name' => $product['name'],
            'price' => $product['price'],
            'image' => $product['image'] ?? null,
        ]
    );

    // Kapag nasa cart ang product,
    // alisin ito dahil nilipat na sa wishlist.
    CartItem::where('user_id', $user->id)
        ->where('product_slug', $slug)
        ->delete();

    return redirect()
        ->back()
        ->with(
            'success',
            $product['name'] . ' moved to your wishlist!'
        );

})->name('buyer.wishlist.add');

// =====================================================
// REMOVE FROM WISHLIST
// =====================================================

Route::post('/wishlist/remove/{slug}', function ($slug) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $user = auth()->user();

    WishlistItem::where('user_id', $user->id)
        ->where('product_slug', $slug)
        ->delete();

    return redirect()
        ->back()
        ->with(
            'success',
            'Item removed from wishlist.'
        );

})->name('buyer.wishlist.remove');

// =====================================================
// TOGGLE WISHLIST
// =====================================================

Route::post('/wishlist/toggle/{slug}', function ($slug) use ($products, $requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    if (!isset($products[$slug])) {
        abort(404);
    }

    $user = auth()->user();

    $product = $products[$slug];

    $wishlistItem = WishlistItem::where('user_id', $user->id)
        ->where('product_slug', $slug)
        ->first();

    if ($wishlistItem) {

        $wishlistItem->delete();

        return redirect()
            ->back()
            ->with(
                'success',
                $product['name'] . ' removed from your wishlist.'
            );
    }

    WishlistItem::create([
        'user_id' => $user->id,
        'product_slug' => $slug,
        'product_name' => $product['name'],
        'price' => $product['price'],
        'image' => $product['image'] ?? null,
    ]);

    return redirect()
        ->back()
        ->with(
            'success',
            $product['name'] . ' added to your wishlist!'
        );

})->name('buyer.wishlist.toggle');

// =====================================================
// MOVE WISHLIST ITEM TO CART
// =====================================================

Route::post('/wishlist/move-to-cart/{slug}', function ($slug) use ($products, $requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $user = auth()->user();

    // Hanapin muna ang item sa wishlist ng current buyer.
    $wishlistItem = WishlistItem::where('user_id', $user->id)
        ->where('product_slug', $slug)
        ->first();

    if (!$wishlistItem) {
        return redirect()
            ->back()
            ->with(
                'error',
                'Wishlist item not found.'
            );
    }

    // Kunin ang stock mula sa product catalog.
    if (!isset($products[$slug])) {
        abort(404);
    }

    $product = $products[$slug];

    $maxStock = (int) $product['stock'];

    // Check kung nasa cart na ang product.
    $cartItem = CartItem::where('user_id', $user->id)
        ->where('product_slug', $slug)
        ->first();

    if ($cartItem) {

        // Existing na sa cart, dagdagan ng 1.
        if ($cartItem->quantity < $maxStock) {
            $cartItem->increment('quantity');
        }

    } else {

        // Wala pa sa cart, gumawa ng bagong cart item.
        CartItem::create([
            'user_id' => $user->id,
            'product_slug' => $slug,
            'product_name' => $wishlistItem->product_name,
            'price' => $wishlistItem->price,
            'image' => $wishlistItem->image,
            'quantity' => 1,
        ]);
    }

    // Tanggalin na sa wishlist pagkatapos mailipat sa cart.
    $wishlistItem->delete();

    return redirect()
        ->route('buyer.cart')
        ->with(
            'success',
            $product['name'] . ' moved to your cart!'
        );

})->name('buyer.wishlist.move-to-cart');


// =====================================================
// ORDER VIEW FORMATTER
// =====================================================
//
// Existing Blade files still use array-style order data.
// Supabase/PostgreSQL is now the real source of order data.
//

$orderToViewArray = function (Order $order): array {

    $items = [];

    foreach ($order->items as $item) {

        $items[$item->product_slug] = [
            'slug' => $item->product_slug,
            'name' => $item->product_name,
            'price' => (float) $item->unit_price,
            'quantity' => (int) $item->quantity,
            'image' => $item->image,
            'variation' => $item->variation,
            'line_total' => (float) $item->line_total,
        ];
    }

    return [
        'id' => $order->order_number,

        'items' => $items,

        'subtotal' => (float) $order->subtotal,

        'shipping' => (float) $order->shipping_fee,

        'discount' => (float) $order->discount_amount,

        'total' => (float) $order->total_amount,

        /*
        |--------------------------------------------------------------------------
        | DELIVERY
        |--------------------------------------------------------------------------
        |
        | Wala nang J&T / Flash / LBC.
        |
        | SUKI Logistics ang bahala sa sorting,
        | rider assignment, at final delivery.
        |
        */

        'shipping_method' => 'SUKI Logistics',

        'payment_method' =>
            $order->payment_method,

        'payment_status' =>
            strtolower(
                $order->payment_status ?? 'UNPAID'
            ),

        /*
        |--------------------------------------------------------------------------
        | DELIVERY ADDRESS
        |--------------------------------------------------------------------------
        */

        'shipping_address' => [

            'id' => null,

            'name' =>
                $order->recipient_name,

            'phone' =>
                $order->recipient_phone,

            'province' =>
                $order->province,

            'municipality' =>
                $order->municipality,

            'barangay' =>
                $order->barangay,

            'street' =>
                $order->street_address,

            'house_number' =>
                $order->house_number,

            'postal_code' =>
                $order->postal_code,

            'label' =>
                $order->address_label,

            'is_default' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | ORDER STATUS
        |--------------------------------------------------------------------------
        */

        'status' =>
            strtolower($order->status),

        'created_at' =>
            optional(
                $order->placed_at
                ?? $order->created_at
            )->format('Y-m-d H:i:s'),

        'updated_at' =>
            optional(
                $order->updated_at
            )->format('Y-m-d H:i:s'),

        'confirmed_at' =>
            optional(
                $order->confirmed_at
            )->format('Y-m-d H:i:s'),

        'preparing_at' =>
            optional(
                $order->preparing_at
            )->format('Y-m-d H:i:s'),

        'ready_for_pickup_at' =>
            optional(
                $order->ready_for_pickup_at
            )->format('Y-m-d H:i:s'),

        'picked_up_at' =>
            optional(
                $order->picked_up_at
            )->format('Y-m-d H:i:s'),

        'at_sorting_center_at' =>
            optional(
                $order->at_sorting_center_at
            )->format('Y-m-d H:i:s'),

        'sorted_at' =>
            optional(
                $order->sorted_at
            )->format('Y-m-d H:i:s'),

        'assigned_to_rider_at' =>
            optional(
                $order->assigned_to_rider_at
            )->format('Y-m-d H:i:s'),

        'out_for_delivery_at' =>
            optional(
                $order->out_for_delivery_at
            )->format('Y-m-d H:i:s'),

        'delivered_at' =>
            optional(
                $order->delivered_at
            )->format('Y-m-d H:i:s'),

        'completed_at' =>
            optional(
                $order->completed_at
            )->format('Y-m-d H:i:s'),

        'delivery_failed_at' =>
            optional(
                $order->delivery_failed_at
            )->format('Y-m-d H:i:s'),

        'returned_at' =>
            optional(
                $order->returned_at
            )->format('Y-m-d H:i:s'),

        'cancel_reason' =>
            $order->cancel_reason,

        'cancelled_at' =>
            optional(
                $order->cancelled_at
            )->format('Y-m-d H:i:s'),
    ];
};


// =====================================================
// CHECKOUT
// =====================================================

Route::get('/checkout', function () use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $user = auth()->user();


    // =====================================================
    // GET CART FROM SUPABASE
    // =====================================================

    $cartRows = $user
        ->cartItems()
        ->orderBy('created_at')
        ->get();


    if ($cartRows->isEmpty()) {

        return redirect()
            ->route('buyer.cart')
            ->with(
                'error',
                'Your cart is empty.'
            );
    }


    // =====================================================
    // CONVERT CART TO EXISTING BLADE FORMAT
    // =====================================================

    $cart = [];


    foreach ($cartRows as $item) {

        $cart[$item->product_slug] = [

            'slug' =>
                $item->product_slug,

            'name' =>
                $item->product_name,

            'price' =>
                (float) $item->price,

            'image' =>
                $item->image,

            'quantity' =>
                (int) $item->quantity,
        ];
    }


    // =====================================================
    // BUYER ADDRESSES
    // =====================================================
    //
    // Address system is still session-based for now.
    //

    $addresses = session(
        'buyer_addresses',
        [
            [
                'id' => 1,

                'name' =>
                    'Juan Dela Cruz',

                'phone' =>
                    '0912 345 6789',

                'province' =>
                    'Laguna',

                'municipality' =>
                    'Santa Rosa',

                'barangay' =>
                    'San Antonio',

                'street' =>
                    '123 Main Street',

                'house_number' =>
                    '',

                'postal_code' =>
                    '4026',

                'label' =>
                    'Default Address',

                'is_default' =>
                    true,
            ],
        ]
    );


    if (!session()->has('buyer_addresses')) {

        session()->put(
            'buyer_addresses',
            $addresses
        );
    }


    $defaultAddress =
        collect($addresses)
            ->firstWhere(
                'is_default',
                true
            );


    if (
        !$defaultAddress
        && !empty($addresses)
    ) {

        $defaultAddress =
            $addresses[0];
    }


    // =====================================================
    // ORDER TOTAL
    // =====================================================

    $subtotal =
        $cartRows->sum(
            function ($item) {

                return
                    (float) $item->price
                    *
                    (int) $item->quantity;
            }
        );


    /*
    |--------------------------------------------------------------------------
    | SUKI LOGISTICS DELIVERY
    |--------------------------------------------------------------------------
    |
    | Wala nang courier selection.
    |
    | Logistics ang bahala sa rider/delivery.
    |
    | 0 muna ang delivery fee hangga't wala pang
    | final internal delivery-pricing computation.
    |
    */

    $shipping = 0;


    $total =
        $subtotal;


    return view(
        'buyer.checkout',
        [

            'cart' =>
                $cart,

            'addresses' =>
                $addresses,

            'defaultAddress' =>
                $defaultAddress,

            'subtotal' =>
                $subtotal,

            'shipping' =>
                $shipping,

            'total' =>
                $total,
        ]
    );

})->name('buyer.checkout');


// =====================================================
// PLACE ORDER
// =====================================================

Route::post(
    '/checkout/place-order',
    function (
        Request $request
    ) use (
        $requireBuyer
    ) {

        if ($redirect = $requireBuyer()) {
            return $redirect;
        }


        $user =
            auth()->user();


        // =====================================================
        // VALIDATION
        // =====================================================
        //
        // WALA NANG shipping_method.
        //
        // Hindi na pipili ang buyer ng:
        //
        // J&T
        // Flash
        // LBC
        //
        // SUKI Logistics na ang fulfillment.
        //

        $request->validate([

            'address_id' =>
                'required',

            'payment_method' =>
                'required|in:cod,gcash',
        ]);


        // =====================================================
        // CART FROM SUPABASE
        // =====================================================

        $cartItems =
            $user
                ->cartItems()
                ->get();


        if ($cartItems->isEmpty()) {

            return redirect()
                ->route('buyer.cart')
                ->with(
                    'error',
                    'Your cart is empty.'
                );
        }


        // =====================================================
        // DELIVERY ADDRESS
        // =====================================================

        $addresses =
            session(
                'buyer_addresses',
                []
            );


        $selectedAddress =
            collect($addresses)
                ->firstWhere(
                    'id',
                    (int)
                    $request->address_id
                );


        if (!$selectedAddress) {

            return redirect()
                ->route(
                    'buyer.checkout'
                )
                ->with(
                    'error',
                    'Please select a valid delivery address.'
                );
        }


        // =====================================================
        // SERVER-SIDE TOTAL
        // =====================================================

        $subtotal =
            $cartItems->sum(
                function ($item) {

                    return
                        (float) $item->price
                        *
                        (int) $item->quantity;
                }
            );


        /*
        |--------------------------------------------------------------------------
        | DELIVERY FEE
        |--------------------------------------------------------------------------
        |
        | Wala muna tayong arbitrary courier fee.
        |
        | Kapag nakagawa na tayo ng delivery area/rate
        | system para sa SUKI Logistics, dito natin
        | iko-compute ang delivery fee.
        |
        */

        $shipping = 0;


        $discount = 0;


        $total =
            $subtotal
            + $shipping
            - $discount;


        // =====================================================
        // UNIQUE ORDER NUMBER
        // =====================================================

        do {

            $orderNumber =
                'SKI-'
                .
                now()->format(
                    'YmdHis'
                )
                .
                '-'
                .
                Str::upper(
                    Str::random(6)
                );

        } while (

            Order::where(
                'order_number',
                $orderNumber
            )->exists()

        );


        // =====================================================
        // DATABASE TRANSACTION
        // =====================================================

        try {

            $order =
                DB::transaction(
                    function () use (

                        $user,

                        $cartItems,

                        $selectedAddress,

                        $request,

                        $subtotal,

                        $shipping,

                        $discount,

                        $total,

                        $orderNumber

                    ) {


                        // =====================================================
                        // CREATE ORDER
                        // =====================================================

                        $order =
                            Order::create([

                                'order_number' =>
                                    $orderNumber,


                                'buyer_id' =>
                                    $user->id,


                                /*
                                |--------------------------------------------------------------------------
                                | SELLER ID
                                |--------------------------------------------------------------------------
                                |
                                | Null muna dahil hardcoded pa ang products
                                | at wala pang actual seller user_id linkage.
                                |
                                */

                                'seller_id' =>
                                    null,


                                'status' =>
                                    'PLACED',


                                'payment_method' =>
                                    $request
                                        ->payment_method,


                                'payment_status' =>
                                    'UNPAID',


                                /*
                                |--------------------------------------------------------------------------
                                | DELIVERY METHOD
                                |--------------------------------------------------------------------------
                                */

                                'shipping_method' =>
                                    'suki_logistics',


                                'subtotal' =>
                                    $subtotal,


                                'shipping_fee' =>
                                    $shipping,


                                'discount_amount' =>
                                    $discount,


                                'total_amount' =>
                                    $total,


                                'voucher_code' =>
                                    null,


                                // =====================================================
                                // ADDRESS SNAPSHOT
                                // =====================================================

                                'recipient_name' =>
                                    $selectedAddress[
                                        'name'
                                    ],


                                'recipient_phone' =>
                                    $selectedAddress[
                                        'phone'
                                    ],


                                'province' =>
                                    $selectedAddress[
                                        'province'
                                    ],


                                'municipality' =>
                                    $selectedAddress[
                                        'municipality'
                                    ],


                                'barangay' =>
                                    $selectedAddress[
                                        'barangay'
                                    ],


                                'street_address' =>
                                    $selectedAddress[
                                        'street'
                                    ],


                                'house_number' =>
                                    $selectedAddress[
                                        'house_number'
                                    ] ?? null,


                                'postal_code' =>
                                    $selectedAddress[
                                        'postal_code'
                                    ] ?? null,


                                'address_label' =>
                                    $selectedAddress[
                                        'label'
                                    ] ?? null,


                                'delivery_notes' =>
                                    null,


                                'placed_at' =>
                                    now(),
                            ]);


                        // =====================================================
                        // CREATE ORDER ITEMS
                        // =====================================================

                        foreach (
                            $cartItems
                            as $cartItem
                        ) {


                            $unitPrice =
                                (float)
                                $cartItem->price;


                            $quantity =
                                (int)
                                $cartItem->quantity;


                            $lineTotal =
                                $unitPrice
                                *
                                $quantity;


                            $order
                                ->items()
                                ->create([

                                    'seller_id' =>
                                        null,


                                    'product_slug' =>
                                        $cartItem
                                            ->product_slug,


                                    'product_name' =>
                                        $cartItem
                                            ->product_name,


                                    'image' =>
                                        $cartItem
                                            ->image,


                                    'variation' =>
                                        null,


                                    'unit_price' =>
                                        $unitPrice,


                                    'quantity' =>
                                        $quantity,


                                    'line_total' =>
                                        $lineTotal,
                                ]);
                        }


                        // =====================================================
                        // CLEAR BUYER CART
                        // =====================================================

                        $user
                            ->cartItems()
                            ->delete();


                        return $order;
                    }
                );


        } catch (\Throwable $exception) {


            report($exception);


            return redirect()
                ->route(
                    'buyer.checkout'
                )
                ->with(
                    'error',
                    'Unable to place your order. Please try again.'
                );
        }


        // =====================================================
        // SUCCESS
        // =====================================================

        return redirect()
            ->route(
                'buyer.order-success',
                [

                    'order' =>
                        $order
                            ->order_number,
                ]
            )
            ->with(
                'success',
                'Your order has been placed successfully!'
            );

    }
)->name(
    'buyer.checkout.place-order'
);


// =====================================================
// ORDER SUCCESS
// =====================================================

Route::get(
    '/order-success/{order}',
    function (
        $orderNumber
    ) use (
        $requireBuyer,
        $orderToViewArray
    ) {

        if ($redirect = $requireBuyer()) {
            return $redirect;
        }


        $order =
            Order::with('items')
                ->where(
                    'buyer_id',
                    auth()->id()
                )
                ->where(
                    'order_number',
                    $orderNumber
                )
                ->first();


        if (!$order) {

            return redirect()
                ->route(
                    'buyer.home'
                )
                ->with(
                    'error',
                    'Order not found.'
                );
        }


        return view(
            'buyer.order-success',
            [

                'order' =>
                    $orderToViewArray(
                        $order
                    ),
            ]
        );

    }
)->name(
    'buyer.order-success'
);


// =====================================================
// MY ORDERS
// =====================================================

Route::get(
    '/my-orders',
    function () use (
        $requireBuyer,
        $orderToViewArray
    ) {

        if ($redirect = $requireBuyer()) {
            return $redirect;
        }


        $databaseOrders =
            Order::with('items')
                ->where(
                    'buyer_id',
                    auth()->id()
                )
                ->latest()
                ->get();


        $orders = [];


        foreach (
            $databaseOrders
            as $databaseOrder
        ) {

            $orders[
                $databaseOrder
                    ->order_number
            ] =
                $orderToViewArray(
                    $databaseOrder
                );
        }


        return view(
            'buyer.my-orders',
            [

                'orders' =>
                    $orders,
            ]
        );

    }
)->name(
    'buyer.my-orders'
);


// =====================================================
// ORDER DETAILS
// =====================================================

Route::get(
    '/my-orders/{order}',
    function (
        $orderNumber
    ) use (
        $requireBuyer,
        $orderToViewArray
    ) {

        if ($redirect = $requireBuyer()) {
            return $redirect;
        }


        $order =
            Order::with('items')
                ->where(
                    'buyer_id',
                    auth()->id()
                )
                ->where(
                    'order_number',
                    $orderNumber
                )
                ->first();


        if (!$order) {

            return redirect()
                ->route(
                    'buyer.my-orders'
                )
                ->with(
                    'error',
                    'Order not found.'
                );
        }


        return view(
            'buyer.order-details',
            [

                'order' =>
                    $orderToViewArray(
                        $order
                    ),
            ]
        );

    }
)->name(
    'buyer.order-details'
);


// =====================================================
// CANCEL ORDER
// =====================================================

Route::post(
    '/my-orders/{order}/cancel',
    function (
        Request $request,
        $orderNumber
    ) use (
        $requireBuyer
    ) {

        if ($redirect = $requireBuyer()) {
            return $redirect;
        }


        $order =
            Order::where(
                'buyer_id',
                auth()->id()
            )
                ->where(
                    'order_number',
                    $orderNumber
                )
                ->first();


        if (!$order) {

            return back()
                ->with(
                    'error',
                    'Order not found.'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | ONLY PLACED ORDERS CAN BE CANCELLED
        |--------------------------------------------------------------------------
        */

        if (
            $order->status
            !== 'PLACED'
        ) {

            return back()
                ->with(
                    'error',
                    'This order can no longer be cancelled because the seller has already started processing it.'
                );
        }


        $request->validate([

            'cancel_reason' =>
                'required|in:changed_mind,ordered_by_mistake,found_better_price,wrong_product,seller_requested,other',
        ]);


        $order->update([

            'status' =>
                'CANCELLED',

            'cancel_reason' =>
                $request
                    ->cancel_reason,

            'cancelled_at' =>
                now(),
        ]);


        return redirect()
            ->route(
                'buyer.order-details',
                [

                    'order' =>
                        $order
                            ->order_number,
                ]
            )
            ->with(
                'success',
                'Your order has been cancelled successfully!'
            );

    }
)->name(
    'buyer.order.cancel'
);


// =====================================================
// UPDATE ORDER STATUS
// =====================================================
//
// ERP FLOW:
//
// PLACED
// ↓
// CONFIRMED
// ↓
// PREPARING
// ↓
// READY_FOR_PICKUP
// ↓
// PICKED_UP
// ↓
// AT_SORTING_CENTER
// ↓
// SORTED
// ↓
// ASSIGNED_TO_RIDER
// ↓
// OUT_FOR_DELIVERY
// ↓
// DELIVERED
// ↓
// COMPLETED
//
// Alternative:
// DELIVERY_FAILED
// RETURNED
//
// =====================================================

Route::post(
    '/orders/{order}/status',
    function (
        Request $request,
        $orderNumber
    ) {


        $order =
            Order::where(
                'order_number',
                $orderNumber
            )
                ->first();


        if (!$order) {

            return back()
                ->with(
                    'error',
                    'Order not found.'
                );
        }


        $allowedStatuses = [

            'placed',

            'confirmed',

            'preparing',

            'ready_for_pickup',

            'picked_up',

            'at_sorting_center',

            'sorted',

            'assigned_to_rider',

            'out_for_delivery',

            'delivered',

            'completed',

            'delivery_failed',

            'returned',

            'cancelled',
        ];


        $request->validate([

            'status' =>
                'required|in:'
                .
                implode(
                    ',',
                    $allowedStatuses
                ),
        ]);


        $currentStatus =
            strtolower(
                $order->status
            );


        if (
            $currentStatus
            === 'completed'
        ) {

            return back()
                ->with(
                    'error',
                    'This order has already been completed.'
                );
        }


        if (
            $currentStatus
            === 'cancelled'
        ) {

            return back()
                ->with(
                    'error',
                    'This order has already been cancelled.'
                );
        }


        $newStatus =
            strtolower(
                $request->status
            );


        $timestampFields = [

            'confirmed' =>
                'confirmed_at',

            'preparing' =>
                'preparing_at',

            'ready_for_pickup' =>
                'ready_for_pickup_at',

            'picked_up' =>
                'picked_up_at',

            'at_sorting_center' =>
                'at_sorting_center_at',

            'sorted' =>
                'sorted_at',

            'assigned_to_rider' =>
                'assigned_to_rider_at',

            'out_for_delivery' =>
                'out_for_delivery_at',

            'delivered' =>
                'delivered_at',

            'completed' =>
                'completed_at',

            'delivery_failed' =>
                'delivery_failed_at',

            'returned' =>
                'returned_at',
        ];


        $updates = [

            'status' =>
                strtoupper(
                    $newStatus
                ),
        ];


        if (
            isset(
                $timestampFields[
                    $newStatus
                ]
            )
        ) {

            $updates[
                $timestampFields[
                    $newStatus
                ]
            ] =
                now();
        }


        $order->update(
            $updates
        );


        return back()
            ->with(
                'success',
                'Order status updated successfully.'
            );

    }
)->name(
    'order.status.update'
);


// =====================================================
// BUY AGAIN
// =====================================================

Route::post(
    '/my-orders/{order}/buy-again',
    function (
        $orderNumber
    ) use (
        $requireBuyer,
        $products
    ) {

        if ($redirect = $requireBuyer()) {
            return $redirect;
        }


        $order =
            Order::with('items')
                ->where(
                    'buyer_id',
                    auth()->id()
                )
                ->where(
                    'order_number',
                    $orderNumber
                )
                ->first();


        if (!$order) {

            return redirect()
                ->route(
                    'buyer.my-orders'
                )
                ->with(
                    'error',
                    'Order not found.'
                );
        }


        $user =
            auth()->user();


        foreach (
            $order->items
            as $item
        ) {


            if (
                !isset(
                    $products[
                        $item->product_slug
                    ]
                )
            ) {

                continue;
            }


            $maxStock =
                (int)
                $products[
                    $item->product_slug
                ]['stock'];


            $cartItem =
                CartItem::where(
                    'user_id',
                    $user->id
                )
                    ->where(
                        'product_slug',
                        $item->product_slug
                    )
                    ->first();


            if ($cartItem) {


                $newQuantity =
                    min(

                        $cartItem->quantity
                        +
                        $item->quantity,

                        $maxStock
                    );


                $cartItem->update([

                    'quantity' =>
                        $newQuantity,
                ]);


            } else {


                CartItem::create([

                    'user_id' =>
                        $user->id,

                    'product_slug' =>
                        $item->product_slug,

                    'product_name' =>
                        $item->product_name,

                    'price' =>
                        $item->unit_price,

                    'image' =>
                        $item->image,

                    'quantity' =>
                        min(
                            $item->quantity,
                            $maxStock
                        ),
                ]);
            }
        }


        return redirect()
            ->route(
                'buyer.cart'
            )
            ->with(
                'success',
                'Items from your previous order have been added to your cart!'
            );

    }
)->name(
    'buyer.order.buy-again'
);


// =====================================================
// BUYER ACCOUNT
// =====================================================

Route::get('/my-account', function () use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $profile = session('buyer_profile', [
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'email' => 'juan@example.com',
        'phone' => '0912 345 6789',
    ]);

    if (!session()->has('buyer_profile')) {
        session()->put('buyer_profile', $profile);
    }

    return view('buyer.account', compact('profile'));

})->name('buyer.account');


// =====================================================
// UPDATE BUYER PROFILE
// =====================================================

Route::post('/my-account/profile', function (
    Request $request
) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $request->validate([
        'first_name' => 'required|string|max:100',
        'last_name' => 'required|string|max:100',
        'email' => 'required|email|max:255',
        'phone' => 'required|string|max:30',
    ]);

    session()->put('buyer_profile', [
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'email' => $request->email,
        'phone' => $request->phone,
    ]);

    return redirect()
        ->route('buyer.account')
        ->with(
            'success',
            'Your profile has been updated successfully.'
        );

})->name('buyer.account.update');


// =====================================================
// CHANGE PASSWORD
// =====================================================

Route::post('/my-account/password', function (
    Request $request
) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $request->validate([
        'current_password' => 'required|string',
        'password' => 'required|string|min:8|confirmed',
    ]);

    return redirect()
        ->route('buyer.account')
        ->with(
            'success',
            'Your password has been changed successfully.'
        );

})->name('buyer.account.password');


// =====================================================
// BUYER ADDRESSES
// =====================================================

Route::get('/my-account/addresses', function () use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $addresses = session('buyer_addresses', [
        [
            'id' => 1,
            'name' => 'Juan Dela Cruz',
            'phone' => '0912 345 6789',
            'province' => 'Laguna',
            'municipality' => 'Santa Rosa',
            'barangay' => 'San Antonio',
            'street' => '123 Main Street',
            'house_number' => '',
            'postal_code' => '4026',
            'label' => 'Default Address',
            'is_default' => true,
        ],
    ]);

    if (!session()->has('buyer_addresses')) {
        session()->put('buyer_addresses', $addresses);
    }

    return view('buyer.addresses', compact('addresses'));

})->name('buyer.addresses');


// =====================================================
// ADD ADDRESS
// =====================================================

Route::post('/my-account/addresses', function (
    Request $request
) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $request->validate([
        'name' => 'required|string|max:100',
        'phone' => 'required|string|max:30',
        'province' => 'required|string|max:100',
        'municipality' => 'required|string|max:100',
        'barangay' => 'required|string|max:100',
        'street' => 'required|string|max:255',
        'house_number' => 'nullable|string|max:100',
        'postal_code' => 'required|string|max:20',
        'label' => 'required|string|max:50',
    ]);

    $addresses = session('buyer_addresses', []);

    $isDefault = $request->boolean('is_default');

    if (empty($addresses)) {
        $isDefault = true;
    }

    if ($isDefault) {

        foreach ($addresses as &$address) {
            $address['is_default'] = false;
        }

        unset($address);
    }

    $addresses[] = [
        'id' => time(),
        'name' => $request->name,
        'phone' => $request->phone,
        'province' => $request->province,
        'municipality' => $request->municipality,
        'barangay' => $request->barangay,
        'street' => $request->street,
        'house_number' => $request->house_number,
        'postal_code' => $request->postal_code,
        'label' => $request->label,
        'is_default' => $isDefault,
    ];

    session()->put('buyer_addresses', $addresses);

    return redirect()
        ->route('buyer.addresses')
        ->with(
            'success',
            'New address added successfully.'
        );

})->name('buyer.addresses.store');


// =====================================================
// SET DEFAULT ADDRESS
// =====================================================

Route::post('/my-account/addresses/{address}/default', function (
    $addressId
) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $addresses = session('buyer_addresses', []);

    foreach ($addresses as &$address) {

        $address['is_default'] =
            ((string) $address['id'] === (string) $addressId);
    }

    unset($address);

    session()->put('buyer_addresses', $addresses);

    return redirect()
        ->route('buyer.addresses')
        ->with(
            'success',
            'Default address updated.'
        );

})->name('buyer.addresses.default');


// =====================================================
// DELETE ADDRESS
// =====================================================

Route::post('/my-account/addresses/{address}/delete', function (
    $addressId
) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $addresses = session('buyer_addresses', []);

    $deletedWasDefault = false;

    foreach ($addresses as $address) {

        if (
            (string) $address['id'] === (string) $addressId
            && !empty($address['is_default'])
        ) {
            $deletedWasDefault = true;
        }
    }

    $addresses = array_values(
        array_filter(
            $addresses,
            fn ($address) =>
                (string) $address['id'] !== (string) $addressId
        )
    );

    if ($deletedWasDefault && !empty($addresses)) {
        $addresses[0]['is_default'] = true;
    }

    session()->put('buyer_addresses', $addresses);

    return redirect()
        ->route('buyer.addresses')
        ->with(
            'success',
            'Address removed successfully.'
        );

})->name('buyer.addresses.delete');

// =====================================================
// SELLER AUTH CHECK
// =====================================================

$requireSeller = function () {

    if (!session('seller_logged_in')) {

        $intendedUrl = request()->isMethod('GET')
            ? request()->fullUrl()
            : url()->previous();

        if (!str_starts_with($intendedUrl, url('/seller'))) {
            $intendedUrl = route('seller.dashboard');
        }

        session()->put('url.intended', $intendedUrl);

        return redirect()
            ->route('seller.register')
            ->with(
                'error',
                'Please register or log in as a seller to continue.'
            );
    }

    return null;
};

// =====================================================
// SELLER ORDERS
// =====================================================

Route::get('/seller/orders', function () {

    $orders = session('orders', []);

    $orders = array_reverse($orders, true);

    return view('seller.orders', [
        'orders' => $orders,
    ]);

})->name('seller.orders');


// =====================================================
// SELLER REGISTRATION
// =====================================================

Route::get('/seller/register', function () {

    return view('seller.register');

})->name('seller.register');


Route::post('/seller/register', function (Request $request) {

    $request->validate([
        'shop_name' => 'required|string|max:150',
        'seller_name' => 'required|string|max:150',
        'phone' => 'required|string|max:30',
        'email' => 'required|email|max:255',
        'address' => 'required|string|max:255',
        'password' => 'required|string|min:8|confirmed',
        'terms' => 'required',
    ]);

    session()->put('seller_profile', [
        'shop_name' => $request->shop_name,
        'seller_name' => $request->seller_name,
        'phone' => $request->phone,
        'email' => $request->email,
        'address' => $request->address,
        'status' => 'approved',
        'created_at' => now()->format('Y-m-d H:i:s'),
    ]);

    session()->put('seller_logged_in', true);

    return redirect()
        ->route('seller.dashboard')
        ->with(
            'success',
            'Your SUKI SHOP Seller account has been created successfully!'
        );

})->name('seller.register.submit');


// =====================================================
// SELLER DASHBOARD
// =====================================================

Route::get('/seller', function () use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    return view('seller.dashboard');

})->name('seller.dashboard');


// =====================================================
// SELLER PRODUCTS
// =====================================================

Route::get('/seller/products', function () use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $products = session()->get('seller_products', []);

    return view('seller.products', [
        'products' => $products,
    ]);

})->name('seller.products');


// =====================================================
// SELLER ADD PRODUCT
// =====================================================

Route::get('/seller/products/create', function () {

    return view('seller.products-create');

})->name('seller.products.create');


// =====================================================
// SELLER STORE PRODUCT
// =====================================================

Route::post('/seller/products', function (
    Request $request
) use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $request->validate([
        'name' => 'required|string|max:150',
        'category' => 'required|string|max:100',
        'description' => 'required|string|max:3000',
        'price' => 'required|numeric|min:0',
        'stock' => 'required|integer|min:0',
        'sku' => 'nullable|string|max:50',
        'status' => 'required|in:active,inactive',
    ]);

    $products = session()->get('seller_products', []);

    $productId = 'P' . str_pad(
        count($products) + 1,
        4,
        '0',
        STR_PAD_LEFT
    );

    $products[$productId] = [
        'id' => $productId,
        'name' => $request->name,
        'category' => $request->category,
        'brand' => $request->brand,
        'description' => $request->description,

        'price' => (float) $request->price,
        'stock' => (int) $request->stock,

        'sku' => $request->sku,
        'status' => $request->status,

        'weight' => $request->weight,
        'length' => $request->length,
        'width' => $request->width,
        'height' => $request->height,

        'variation_names' => $request->variation_name ?? [],
        'variation_values' => $request->variation_value ?? [],

        'shipping_options' => $request->shipping_options ?? [],

        'sold' => 0,

        'created_at' => now()->format('Y-m-d H:i:s'),
        'updated_at' => now()->format('Y-m-d H:i:s'),
    ];

    session()->put('seller_products', $products);

    return redirect()
        ->route('seller.products')
        ->with(
            'success',
            'Product added successfully.'
        );

})->name('seller.products.store');

// =====================================================
// SELLER EDIT PRODUCT
// =====================================================

Route::get('/seller/products/{product}/edit', function (
    $productId
) use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $products = session()->get('seller_products', []);

    if (!isset($products[$productId])) {
        abort(404);
    }

    return view('seller.products-edit', [
        'product' => $products[$productId],
    ]);

})->name('seller.products.edit');

// =====================================================
// SELLER UPDATE PRODUCT
// =====================================================

Route::put('/seller/products/{product}', function (
    Request $request,
    $productId
) use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $products = session()->get('seller_products', []);

    if (!isset($products[$productId])) {
        abort(404);
    }

    $request->validate([
        'name' => 'required|string|max:150',
        'category' => 'required|string|max:100',
        'description' => 'required|string|max:3000',
        'price' => 'required|numeric|min:0',
        'stock' => 'required|integer|min:0',
        'sku' => 'nullable|string|max:50',
        'status' => 'required|in:active,inactive',
    ]);

    $products[$productId] = array_merge(
        $products[$productId],
        [
            'name' => $request->name,
            'category' => $request->category,
            'brand' => $request->brand,
            'description' => $request->description,

            'price' => (float) $request->price,
            'stock' => (int) $request->stock,

            'sku' => $request->sku,
            'status' => $request->status,

            'weight' => $request->weight,
            'length' => $request->length,
            'width' => $request->width,
            'height' => $request->height,

            'variation_names' => $request->variation_name ?? [],
            'variation_values' => $request->variation_value ?? [],

            'shipping_options' => $request->shipping_options ?? [],

            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]
    );

    session()->put('seller_products', $products);

    return redirect()
        ->route('seller.products')
        ->with(
            'success',
            'Product updated successfully.'
        );

})->name('seller.products.update');


// =====================================================
// SELLER INVENTORY
// =====================================================

Route::get('/seller/inventory', function () use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $products = session()->get('seller_products', []);

    return view('seller.inventory', [
        'products' => $products,
    ]);

})->name('seller.inventory');


Route::post('/seller/inventory/{product}/increase', function (
    $productId
) use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $products = session()->get('seller_products', []);

    if (!isset($products[$productId])) {
        abort(404);
    }

    $products[$productId]['stock'] =
        (int) ($products[$productId]['stock'] ?? 0) + 1;

    $products[$productId]['updated_at'] =
        now()->format('Y-m-d H:i:s');

    session()->put('seller_products', $products);

    return back()->with(
        'success',
        'Product stock increased successfully.'
    );

})->name('seller.inventory.increase');


Route::post('/seller/inventory/{product}/decrease', function (
    $productId
) use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $products = session()->get('seller_products', []);

    if (!isset($products[$productId])) {
        abort(404);
    }

    $currentStock =
        (int) ($products[$productId]['stock'] ?? 0);

    if ($currentStock > 0) {
        $products[$productId]['stock'] = $currentStock - 1;
    }

    $products[$productId]['updated_at'] =
        now()->format('Y-m-d H:i:s');

    session()->put('seller_products', $products);

    return back()->with(
        'success',
        'Product stock updated successfully.'
    );

})->name('seller.inventory.decrease');


// =====================================================
// SELLER REPORTS & ANALYTICS
// =====================================================

Route::get('/seller/reports', function () {

    return view('seller.reports');

})->name('seller.reports');


// =====================================================
// RIDER APPLICATION
// =====================================================

Route::get('/rider/apply', function () {

    return view('rider.apply');

})->name('rider.apply');


// =====================================================
// RIDER APPLICATION SUBMIT
// =====================================================

Route::post('/rider/apply', function (Request $request) {

    $validated = $request->validate([

        'first_name' => 'required|string|max:100',
        'last_name' => 'required|string|max:100',
        'phone' => 'required|string|max:30',
        'email' => 'required|email|max:255',
        'address' => 'required|string|max:1000',

        'vehicle_type' => 'required|in:Motorcycle,E-bike,Bicycle',

        'plate_number' => 'nullable|string|max:30',

        'license_number' => 'required|string|max:100',

        'password' => 'required|string|min:8|confirmed',

        'terms' => 'required',

        // DOCUMENTS
        'government_id' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',

        'drivers_license' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',

        'vehicle_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',

    ]);


    // =====================================================
    // UPLOAD DOCUMENTS
    // =====================================================

    $governmentIdPath = null;
    $driversLicensePath = null;
    $vehicleDocumentPath = null;


    if ($request->hasFile('government_id')) {

        $governmentIdPath = $request
            ->file('government_id')
            ->store('rider-documents/government-ids', 'public');

    }


    if ($request->hasFile('drivers_license')) {

        $driversLicensePath = $request
            ->file('drivers_license')
            ->store('rider-documents/drivers-licenses', 'public');

    }


    if ($request->hasFile('vehicle_document')) {

        $vehicleDocumentPath = $request
            ->file('vehicle_document')
            ->store('rider-documents/vehicle-documents', 'public');

    }


    // =====================================================
    // GET EXISTING RIDER APPLICATIONS
    // =====================================================

    $riderApplications = session('rider_applications', []);


    if (!is_array($riderApplications)) {

        $riderApplications = [];

    }


    // =====================================================
    // CREATE NEW RIDER APPLICATION
    // =====================================================

    $newRider = [

        'first_name' => $validated['first_name'],

        'last_name' => $validated['last_name'],

        'phone' => $validated['phone'],

        'email' => $validated['email'],

        'address' => $validated['address'],

        'vehicle_type' => $validated['vehicle_type'],

        'plate_number' => $validated['plate_number'] ?? '',

        'license_number' => $validated['license_number'],

        // Login password
        'password' => $validated['password'],


        // =====================================================
        // DOCUMENT PATHS
        // =====================================================

        'government_id' => $governmentIdPath,

        'drivers_license' => $driversLicensePath,

        'vehicle_document' => $vehicleDocumentPath,


        // =====================================================
        // APPLICATION STATUS
        // =====================================================

        'status' => 'pending',

        'created_at' => now()->format('Y-m-d H:i:s'),

    ];


    // Add rider to applications list
    $riderApplications[] = $newRider;


    // Save ALL rider applications
    session()->put(
        'rider_applications',
        $riderApplications
    );


    return redirect()
        ->route('rider.application.pending')
        ->with(
            'success',
            'Your SUKI Rider application has been submitted successfully. Please wait for approval from SUKI Logistics.'
        );

})->name('rider.apply.submit');

// =====================================================
// RIDER APPLICATION PENDING
// =====================================================

Route::get('/rider/application-pending', function () {

    return view('rider.application-pending');

})->name('rider.application.pending');

// =====================================================
// RIDER MANAGEMENT
// =====================================================

Route::get('/logistics/riders', function () {

    $riders = session('rider_applications', []);

    return view('logistics.riders', [
        'riders' => $riders,
    ]);

})->name('logistics.riders');

// =====================================================
// RIDER REVIEW
// =====================================================

Route::get('/logistics/riders/{rider}/review', function ($riderId) {

    $riders = session('rider_applications', []);

    if (!isset($riders[$riderId])) {

        return redirect()
            ->route('logistics.riders')
            ->with('error', 'Rider application not found.');
    }

    return view('logistics.rider-review', [
        'rider' => $riders[$riderId],
        'riderId' => $riderId,
    ]);

})->name('logistics.riders.review');

// =====================================================
// APPROVE RIDER
// =====================================================

Route::post('/logistics/riders/{riderId}/approve', function ($riderId) {

    $riders = session('rider_applications', []);

    if (!isset($riders[$riderId])) {

        return back()
            ->with('error', 'Rider application not found.');
    }

    // Update status
    $riders[$riderId]['status'] = 'approved';

    // Save approval date
    $riders[$riderId]['approved_at'] =
        now()->format('Y-m-d H:i:s');

    $riders[$riderId]['updated_at'] =
        now()->format('Y-m-d H:i:s');


    // Save back to session
    session()->put('rider_applications', $riders);


    return redirect()
        ->route('logistics.riders')
        ->with(
            'success',
            'Rider application approved successfully!'
        );

})->name('logistics.riders.approve');


// =====================================================
// DISAPPROVE RIDER
// =====================================================

Route::post('/logistics/riders/{riderId}/disapprove', function ($riderId) {

    $riders = session('rider_applications', []);

    if (!isset($riders[$riderId])) {

        return back()
            ->with('error', 'Rider application not found.');
    }

    // Update status
    $riders[$riderId]['status'] = 'disapproved';

    // Save disapproval date
    $riders[$riderId]['disapproved_at'] =
        now()->format('Y-m-d H:i:s');

    $riders[$riderId]['updated_at'] =
        now()->format('Y-m-d H:i:s');


    // Save back to session
    session()->put('rider_applications', $riders);


    return redirect()
        ->route('logistics.riders')
        ->with(
            'success',
            'Rider application has been disapproved.'
        );

})->name('logistics.riders.disapprove');

// =====================================================
// RIDER LOGIN PAGE
// =====================================================

Route::get('/rider/login', function () {

    return view('rider.login');

})->name('rider.login');


// =====================================================
// RIDER LOGIN SUBMIT
// =====================================================

Route::post('/rider/login', function (Request $request) {

    $request->validate([
        'login' => 'required|string',
        'password' => 'required|string',
    ]);

    // Get all rider applications
    $riders = session('rider_applications', []);


    // Make sure we have an array of riders
    if (empty($riders)) {

        return back()
            ->withInput()
            ->with(
                'error',
                'No rider account found. Please apply as a SUKI Rider first.'
            );
    }


    // Find rider using email OR phone number
    $riderIndex = null;
    $rider = null;


    foreach ($riders as $index => $application) {

        if (

            $request->login === ($application['email'] ?? '') ||

            $request->login === ($application['phone'] ?? '')

        ) {

            $riderIndex = $index;
            $rider = $application;

            break;

        }

    }


    // Rider not found
    if (!$rider) {

        return back()
            ->withInput()
            ->with(
                'error',
                'No rider account was found using that email or phone number.'
            );

    }


    // Check password
    if ($request->password !== ($rider['password'] ?? '')) {

        return back()
            ->withInput()
            ->with(
                'error',
                'Invalid password.'
            );

    }


    // IMPORTANT:
    // Only approved riders can log in

    if (($rider['status'] ?? 'pending') !== 'approved') {

        return back()
            ->withInput()
            ->with(
                'error',
                'Your SUKI Rider application is still pending approval from the SUKI Logistics Center.'
            );

    }


    // Save logged-in rider
    session()->put('rider_logged_in', true);

    session()->put(
        'logged_in_rider_index',
        $riderIndex
    );


    // Redirect to Rider Dashboard
    return redirect()
        ->route('rider.dashboard')
        ->with(
            'success',
            'Welcome back, ' . ($rider['first_name'] ?? 'Rider') . '!'
        );

})->name('rider.login.submit');

// =====================================================
// RIDER DASHBOARD
// =====================================================

Route::get('/rider/dashboard', function () {

    // Check if rider is logged in
    if (!session('rider_logged_in')) {

        return redirect()
            ->route('rider.login')
            ->with(
                'error',
                'Please log in as a SUKI Rider first.'
            );
    }


    // Get all rider applications
    $riders = session('rider_applications', []);


    // Get currently logged-in rider index
    $riderIndex = session('logged_in_rider_index');


    // Check if rider exists
    if (
        $riderIndex === null ||
        !isset($riders[$riderIndex])
    ) {

        session()->forget([
            'rider_logged_in',
            'logged_in_rider_index',
        ]);

        return redirect()
            ->route('rider.login')
            ->with(
                'error',
                'Your rider session has expired. Please log in again.'
            );
    }


    // Get the currently logged-in rider
    $application = $riders[$riderIndex];


    // Extra security:
    // Only approved riders can access the dashboard
    if (($application['status'] ?? '') !== 'approved') {

        session()->forget([
            'rider_logged_in',
            'logged_in_rider_index',
        ]);

        return redirect()
            ->route('rider.login')
            ->with(
                'error',
                'Your rider account is not approved for access.'
            );
    }


    return view('rider.dashboard', [
        'application' => $application,
    ]);

})->name('rider.dashboard');

// =====================================================
// RIDER DELIVERIES
// =====================================================

Route::get('/rider/deliveries', function () {

    if (!session('rider_logged_in')) {

    return redirect()
        ->route('rider.login')
        ->with(
            'error',
            'Please log in as a SUKI Rider first.'
        );
}
    $orders =
        session('orders', []);

    $riderOrders =
        collect($orders)
            ->filter(function ($order) {

                return in_array(
                    $order['status'] ?? '',
                    [
                        'ready_for_pickup',
                        'picked_up',
                        'at_sorting_center',
                        'sorted',
                        'assigned_to_rider',
                        'out_for_delivery',
                        'delivered',
                        'delivery_failed',
                    ]
                );

            })
            ->reverse()
            ->toArray();

    return view('rider.deliveries', [
        'orders' =>
            $riderOrders,
    ]);

})->name('rider.deliveries');


// =====================================================
// RIDER EARNINGS
// =====================================================

Route::get('/rider/earnings', function () {

    if (!session('rider_logged_in')) {

    return redirect()
        ->route('rider.login')
        ->with(
            'error',
            'Please log in as a SUKI Rider first.'
        );
}

    $orders =
        session('orders', []);

    $completedOrders =
        collect($orders)
            ->filter(function ($order) {

                return in_array(
                    $order['status'] ?? '',
                    [
                        'delivered',
                        'completed',
                    ]
                );

            });

    $deliveryFee =
        50;

    $totalDeliveries =
        $completedOrders->count();

    $totalEarnings =
        $totalDeliveries * $deliveryFee;

    return view('rider.earnings', [

        'orders' =>
            $completedOrders,

        'totalDeliveries' =>
            $totalDeliveries,

        'totalEarnings' =>
            $totalEarnings,

        'deliveryFee' =>
            $deliveryFee,
    ]);

})->name('rider.earnings');


// =====================================================
// RIDER ORDER STATUS ACTIONS
// =====================================================
//
// Pickup Rider:
//
// ready_for_pickup
//        ↓
// picked_up
//        ↓
// at_sorting_center
//
// Delivery Rider:
//
// assigned_to_rider
//        ↓
// out_for_delivery
//        ↓
// delivered
// =====================================================

Route::post('/rider/orders/{order}/status', function (
    Request $request,
    $orderId
) {

    // Check if rider has applied
    if (!session('rider_logged_in')) {

    return redirect()
        ->route('rider.login')
        ->with(
            'error',
            'Please log in as a SUKI Rider first.'
        );
}


    // Get all orders
    $orders = session('orders', []);


    // Check if order exists
    if (!isset($orders[$orderId])) {

        return back()->with(
            'error',
            'Order not found.'
        );
    }


    // Get current order status
    $currentStatus =
        $orders[$orderId]['status'] ?? 'placed';


    // Allowed Rider transitions
    $allowedTransitions = [

        // Pickup Rider
        'ready_for_pickup' => 'picked_up',

        'picked_up' => 'at_sorting_center',


        // Delivery Rider
        'assigned_to_rider' => 'out_for_delivery',

        'out_for_delivery' => 'delivered',
    ];


    // Check if rider can update this order
    if (!isset($allowedTransitions[$currentStatus])) {

        return back()->with(
            'error',
            'This order cannot be updated by the rider at its current status.'
        );
    }


    // Get requested status
    $requestedStatus =
        $request->input('status');


    // Get expected next status
    $expectedStatus =
        $allowedTransitions[$currentStatus];


    // Prevent skipping order statuses
    if ($requestedStatus !== $expectedStatus) {

        return back()->with(
            'error',
            'Invalid order status transition.'
        );
    }


    // Update order status
    $orders[$orderId]['status'] =
        $requestedStatus;


    // Update timestamp
    $orders[$orderId]['updated_at'] =
        now()->format('Y-m-d H:i:s');


    // Status timestamps
    $timestampFields = [

        'picked_up' =>
            'picked_up_at',

        'at_sorting_center' =>
            'at_sorting_center_at',

        'out_for_delivery' =>
            'out_for_delivery_at',

        'delivered' =>
            'delivered_at',
        ];


    // Save timestamp for the new status
    if (isset($timestampFields[$requestedStatus])) {

        $orders[$orderId][
            $timestampFields[$requestedStatus]
        ] =
            now()->format('Y-m-d H:i:s');
    }

    // Get the currently logged-in rider
    $riders = session('rider_applications', []);
    $riderIndex = session('logged_in_rider_index');

    $rider = [];

    if (
        $riderIndex !== null &&
        isset($riders[$riderIndex])
    ) {
        $rider = $riders[$riderIndex];
    }

    // Assign rider information to the order
    $orders[$orderId]['rider'] = [

        'first_name' =>
            $rider['first_name'] ?? '',

        'last_name' =>
            $rider['last_name'] ?? '',

        'phone' =>
            $rider['phone'] ?? '',

        'vehicle_type' =>
            $rider['vehicle_type'] ?? '',
    ];


    // Save updated orders
    session()->put(
        'orders',
        $orders
    );


    // Success messages
    $messages = [

        'picked_up' =>
            'Order picked up successfully.',

        'at_sorting_center' =>
            'Order has arrived at the sorting center.',

        'out_for_delivery' =>
            'Delivery is now out for delivery.',

        'delivered' =>
            'Order marked as delivered successfully.',
    ];


    return back()->with(
        'success',
        $messages[$requestedStatus]
            ?? 'Order status updated successfully.'
    );

})->name('rider.order.status');


// =====================================================
// SUKI LOGISTICS DASHBOARD
// =====================================================

Route::get('/logistics', function () {

    $application =
        session('rider_application', []);

    return view('logistics.dashboard', [

        'application' =>
            $application,

        'partner' =>
            $application['partner'] ?? null,
    ]);

})->name('logistics.dashboard');

// =====================================================
// LOGISTICS PARCEL SORTING
// =====================================================

Route::get('/logistics/sorting', function () {

    return view('logistics.sorting');

})->name('logistics.sorting');


// =====================================================
// LOGISTICS DELIVERY ASSIGNMENTS
// =====================================================

Route::get('/logistics/assignments', function () {

    return view('logistics.assignments');

})->name('logistics.assignments');


// =====================================================
// LOGISTICS DELIVERY MONITORING
// =====================================================

Route::get('/logistics/monitoring', function () {

    return view('logistics.monitoring');

})->name('logistics.monitoring');


// =====================================================
// LOGISTICS REPORTS
// =====================================================

Route::get('/logistics/reports', function () {

    return view('logistics.reports');

})->name('logistics.reports');

// =====================================================
// LOGISTICS PARCELS
// =====================================================

Route::get('/logistics/parcels', function () {

    $orders = session('orders', []);

    return view('logistics.parcels', [
        'orders' => $orders,
    ]);

})->name('logistics.parcels');


// =====================================================
// LOGISTICS SHIPMENTS
// =====================================================

Route::get('/logistics/shipments', function () {

    $orders =
        session('orders', []);

    $shipments =
        collect($orders)
            ->filter(function ($order) {

                return in_array(
                    $order['status'] ?? '',
                    [
                        'at_sorting_center',
                        'sorted',
                        'assigned_to_rider',
                        'out_for_delivery',
                        'delivered',
                        'delivery_failed',
                        'returned',
                    ]
                );

            })
            ->reverse()
            ->toArray();

    return view('logistics.shipments', [
        'orders' =>
            $shipments,
    ]);

})->name('logistics.shipments');


// =====================================================
// LOGISTICS ERP STATUS ACTIONS
// =====================================================
//
// Logistics:
//
// at_sorting_center
//        ↓
// sorted
//        ↓
// assigned_to_rider
// =====================================================

Route::post('/logistics/orders/{order}/status', function (
    Request $request,
    $orderId
) {
    $orders = session('orders', []);

    if (!isset($orders[$orderId])) {
        return back()->with(
            'error',
            'Order not found.'
        );
    }

    $currentStatus =
        $orders[$orderId]['status'] ?? 'placed';

    $requestedStatus =
        $request->input('status');

    // =====================================================
    // AT SORTING CENTER → SORTED
    // =====================================================

    if ($currentStatus === 'at_sorting_center') {

        if ($requestedStatus !== 'sorted') {
            return back()->with(
                'error',
                'Invalid Logistics ERP status transition.'
            );
        }

        $request->validate([
            'area' => 'required|string|max:100',
        ]);

        // Save delivery area
        $orders[$orderId]['area'] =
            $request->input('area');

        // Update status
        $orders[$orderId]['status'] =
            'sorted';

        $orders[$orderId]['sorted_at'] =
            now()->format('Y-m-d H:i:s');

        $orders[$orderId]['updated_at'] =
            now()->format('Y-m-d H:i:s');

        session()->put('orders', $orders);

        return back()->with(
            'success',
            'Shipment sorted successfully and assigned to ' .
            $request->input('area') . '.'
        );
    }


    // =====================================================
    // SORTED → ASSIGNED TO RIDER
    // =====================================================

    if ($currentStatus === 'sorted') {

        if ($requestedStatus !== 'assigned_to_rider') {
            return back()->with(
                'error',
                'Invalid Logistics ERP status transition.'
            );
        }

        $request->validate([
            'rider_index' => 'required',
        ]);

        $riders = session(
            'rider_applications',
            []
        );

        $riderIndex =
            $request->input('rider_index');

        if (!isset($riders[$riderIndex])) {
            return back()->with(
                'error',
                'Selected rider was not found.'
            );
        }

        $rider = $riders[$riderIndex];

        if (
            ($rider['status'] ?? 'pending')
            !== 'approved'
        ) {
            return back()->with(
                'error',
                'The selected rider is not approved.'
            );
        }

        $riderName = trim(
            ($rider['first_name'] ?? '') . ' ' .
            ($rider['last_name'] ?? '')
        );

        // Save assigned rider
        $orders[$orderId]['rider_index'] =
            $riderIndex;

        $orders[$orderId]['rider_name'] =
            $riderName;

        $orders[$orderId]['rider'] = [
            'first_name' =>
                $rider['first_name'] ?? '',

            'last_name' =>
                $rider['last_name'] ?? '',

            'phone' =>
                $rider['phone'] ?? '',

            'vehicle_type' =>
                $rider['vehicle_type'] ?? '',

            'plate_number' =>
                $rider['plate_number'] ?? '',
        ];

        // Keep the sorted area
        $orders[$orderId]['assigned_area'] =
            $orders[$orderId]['area'] ?? '';

        // Update status
        $orders[$orderId]['status'] =
            'assigned_to_rider';

        $orders[$orderId]['assigned_to_rider_at'] =
            now()->format('Y-m-d H:i:s');

        $orders[$orderId]['updated_at'] =
            now()->format('Y-m-d H:i:s');

        session()->put('orders', $orders);

        return back()->with(
            'success',
            'Shipment assigned to ' .
            $riderName .
            ' successfully.'
        );
    }


    // =====================================================
    // INVALID LOGISTICS ACTION
    // =====================================================

    return back()->with(
        'error',
        'This order cannot be updated by Logistics at its current status.'
    );

})->name('logistics.order.status');

// =====================================================
// LOGISTICS RIDER MANAGEMENT
// =====================================================

Route::get('/logistics/riders', function () {

    $applications = session('rider_applications', []);

    $finalApplications = [];


    // OLD / MIXED SESSION FORMAT
    // Example:
    //
    // [
    //     'first_name' => 'Angelo',
    //     'last_name' => 'Cayago',
    //     0 => [...],
    //     1 => [...],
    // ]


    // Check if root data is a rider
    if (
        is_array($applications) &&
        isset($applications['first_name'])
    ) {

        $firstRider = [];

        foreach ($applications as $key => $value) {

            // Only get string keys
            if (!is_int($key)) {

                $firstRider[$key] = $value;

            }

        }

        $finalApplications[] = $firstRider;

    }


    // Get riders stored in numeric indexes
    foreach ($applications as $key => $application) {

        if (
            is_int($key) &&
            is_array($application)
        ) {

            $finalApplications[] = $application;

        }

    }


    // If already a normal numeric array
    if (
        empty($finalApplications) &&
        is_array($applications)
    ) {

        foreach ($applications as $application) {

            if (
                is_array($application) &&
                isset($application['first_name'])
            ) {

                $finalApplications[] = $application;

            }

        }

    }


    // Save cleaned format permanently
    session()->put(
        'rider_applications',
        $finalApplications
    );


    return view('logistics.riders', [

        'applications' => $finalApplications,

    ]);

})->name('logistics.riders');

// =====================================================
// LOGISTICS RIDER REVIEW
// =====================================================

Route::get('/logistics/riders/{rider}/review', function ($rider) {

    $applications = session('rider_applications', []);


    if (!isset($applications[$rider])) {

        return redirect()
            ->route('logistics.riders')
            ->with(
                'error',
                'Rider application not found.'
            );

    }


    return view('logistics.rider-review', [

        'rider' => $applications[$rider],

        'riderId' => $rider,

    ]);

})->name('logistics.riders.review');



// =====================================================
// LOGISTICS APPROVE RIDER
// =====================================================

Route::post('/logistics/riders/{rider}/approve', function ($rider) {

    $applications = session('rider_applications', []);


    if (!isset($applications[$rider])) {

        return back()->with(
            'error',
            'Rider application not found.'
        );

    }


    // APPROVED RIDERS CAN LOG IN
    $applications[$rider]['status'] = 'approved';


    $applications[$rider]['approved_at'] =
        now()->format('Y-m-d H:i:s');


    $applications[$rider]['updated_at'] =
        now()->format('Y-m-d H:i:s');


    session()->put(
        'rider_applications',
        $applications
    );


    return redirect()
        ->route('logistics.riders')
        ->with(
            'success',
            'Rider application approved successfully!'
        );

})->name('logistics.riders.approve');



// =====================================================
// LOGISTICS DISAPPROVE RIDER
// =====================================================

Route::post('/logistics/riders/{rider}/disapprove', function (
    Request $request,
    $rider
) {

    $applications = session('rider_applications', []);


    if (!isset($applications[$rider])) {

        return back()->with(
            'error',
            'Rider application not found.'
        );

    }


    $applications[$rider]['status'] = 'disapproved';


    $applications[$rider]['disapproved_at'] =
        now()->format('Y-m-d H:i:s');


    $applications[$rider]['disapproval_reason'] =
        $request->input('reason');


    $applications[$rider]['updated_at'] =
        now()->format('Y-m-d H:i:s');


    session()->put(
        'rider_applications',
        $applications
    );


    return redirect()
        ->route('logistics.riders')
        ->with(
            'success',
            'Rider application has been disapproved.'
        );

})->name('logistics.riders.disapprove');

// =====================================================
// ADMIN AUTH CHECK
// =====================================================

$requireAdmin = function () {

    if (!auth()->check()) {

        return redirect()
            ->route('admin.login')
            ->with(
                'error',
                'Please log in as an administrator.'
            );
    }

    $user = auth()->user();

    if (
        $user->role !== 'admin' ||
        $user->status !== 'active'
    ) {

        return redirect()
            ->route('admin.login')
            ->with(
                'error',
                'You do not have administrator access.'
            );
    }

    return null;
};


// =====================================================
// ADMIN LOGIN PAGE
// =====================================================

Route::get('/admin/login', function () {

    if (
        auth()->check() &&
        auth()->user()->role === 'admin'
    ) {

        return redirect()
            ->route('admin.dashboard');
    }

    return view('admin.login');

})->name('admin.login');


// =====================================================
// ADMIN LOGIN SUBMIT
// =====================================================

Route::post('/admin/login', function (Request $request) {

    $validated = $request->validate([
        'login' => 'required|string',
        'password' => 'required|string',
    ]);

    $loginField = filter_var(
        $validated['login'],
        FILTER_VALIDATE_EMAIL
    )
        ? 'email'
        : 'phone';


    $loggedIn = auth()->attempt([
        $loginField => $validated['login'],
        'password' => $validated['password'],
        'role' => 'admin',
        'status' => 'active',
    ]);


    if (!$loggedIn) {

        return back()
            ->withErrors([
                'login' =>
                    'Invalid administrator credentials.',
            ])
            ->onlyInput('login');
    }


    $request
        ->session()
        ->regenerate();


    return redirect()
        ->route('admin.dashboard')
        ->with(
            'success',
            'Welcome to the SUKI SHOP Admin Portal.'
        );

})->name('admin.login.submit');

// =====================================================
// ADMIN DASHBOARD
// =====================================================

Route::get('/admin', function () use ($requireAdmin) {

    if ($redirect = $requireAdmin()) {
        return $redirect;
    }


    // =====================================================
    // BUYER STATISTICS
    // =====================================================

    $pendingBuyers = User::where('role', 'buyer')
        ->where('status', 'pending')
        ->count();

    $activeBuyers = User::where('role', 'buyer')
        ->where('status', 'active')
        ->count();

    $rejectedBuyers = User::where('role', 'buyer')
        ->where('status', 'rejected')
        ->count();

    $totalBuyers = User::where('role', 'buyer')
        ->count();


    // =====================================================
    // ORDER STATISTICS
    // =====================================================

    $totalOrders = Order::count();

    $placedOrders = Order::where(
        'status',
        'PLACED'
    )->count();

    $processingOrders = Order::whereIn(
        'status',
        [
            'CONFIRMED',
            'PREPARING',
            'READY_FOR_PICKUP',
            'PICKED_UP',
            'AT_SORTING_CENTER',
            'SORTED',
            'ASSIGNED_TO_RIDER',
        ]
    )->count();

    $deliveryOrders = Order::whereIn(
        'status',
        [
            'OUT_FOR_DELIVERY',
            'DELIVERED',
        ]
    )->count();

    $completedOrders = Order::where(
        'status',
        'COMPLETED'
    )->count();


    // =====================================================
    // COMPLETED ORDER VALUE
    // =====================================================

    $completedOrderValue = Order::where(
        'status',
        'COMPLETED'
    )->sum('total_amount');


    // =====================================================
    // LAST 7 DAYS ORDER TREND
    // =====================================================

    $orderTrend = [];

    for ($i = 6; $i >= 0; $i--) {

        $date = now()
            ->subDays($i);

        $orderTrend[] = [

            'label' =>
                $date->format('D'),

            'date' =>
                $date->format('M d'),

            'count' =>
                Order::whereDate(
                    'created_at',
                    $date->toDateString()
                )->count(),
        ];
    }


    // =====================================================
    // RECENT BUYERS
    // =====================================================

    $recentBuyers = User::where(
        'role',
        'buyer'
    )
        ->latest()
        ->take(5)
        ->get();


    // =====================================================
    // RECENT ORDERS
    // =====================================================

    $recentOrders = Order::with('buyer')
        ->latest()
        ->take(5)
        ->get();


    return view(
        'admin.dashboard',
        compact(
            'pendingBuyers',
            'activeBuyers',
            'rejectedBuyers',
            'totalBuyers',

            'totalOrders',
            'placedOrders',
            'processingOrders',
            'deliveryOrders',
            'completedOrders',

            'completedOrderValue',

            'orderTrend',

            'recentBuyers',
            'recentOrders'
        )
    );

})->name('admin.dashboard');

// =====================================================
// ADMIN BUYER APPLICATIONS
// =====================================================

Route::get(
    '/admin/buyers',
    function () use ($requireAdmin) {

        if ($redirect = $requireAdmin()) {
            return $redirect;
        }


        $buyers =
            User::where('role', 'buyer')
                ->latest()
                ->get();


        return view(
            'admin.buyers',
            [
                'buyers' =>
                    $buyers,
            ]
        );

    }
)->name('admin.buyers');


// =====================================================
// ADMIN APPROVE BUYER
// =====================================================

Route::post(
    '/admin/buyers/{buyer}/approve',
    function (
        User $buyer
    ) use (
        $requireAdmin
    ) {

        if ($redirect = $requireAdmin()) {
            return $redirect;
        }


        if ($buyer->role !== 'buyer') {

            return back()
                ->with(
                    'error',
                    'Invalid buyer account.'
                );
        }


        if ($buyer->status === 'active') {

            return back()
                ->with(
                    'error',
                    'Buyer account is already approved.'
                );
        }


        $buyer->update([
            'status' => 'active',
        ]);


        return back()
            ->with(
                'success',
                $buyer->full_name .
                ' has been approved successfully.'
            );

    }
)->name('admin.buyers.approve');


// =====================================================
// ADMIN REJECT BUYER
// =====================================================

Route::post(
    '/admin/buyers/{buyer}/reject',
    function (
        User $buyer
    ) use (
        $requireAdmin
    ) {

        if ($redirect = $requireAdmin()) {
            return $redirect;
        }


        if ($buyer->role !== 'buyer') {

            return back()
                ->with(
                    'error',
                    'Invalid buyer account.'
                );
        }


        if ($buyer->status === 'rejected') {

            return back()
                ->with(
                    'error',
                    'Buyer account is already rejected.'
                );
        }


        $buyer->update([
            'status' => 'rejected',
        ]);


        return back()
            ->with(
                'success',
                $buyer->full_name .
                ' has been rejected.'
            );

    }
)->name('admin.buyers.reject');


// =====================================================
// ADMIN LOGOUT
// =====================================================

Route::post(
    '/admin/logout',
    function (
        Request $request
    ) {

        auth()->logout();

        $request
            ->session()
            ->invalidate();

        $request
            ->session()
            ->regenerateToken();


        return redirect()
            ->route('admin.login')
            ->with(
                'success',
                'Administrator logged out successfully.'
            );

    }
)->name('admin.logout');

// =====================================================
// AUTHENTICATION
// =====================================================


// =====================================================
// LOGIN PAGE
// =====================================================

Route::get('/login', function () {

    // If an authenticated account visits the normal login page,
    // send it directly to the correct portal.
    if (auth()->check()) {

        $user = auth()->user();

        if (
            $user->role === 'admin' &&
            $user->status === 'active'
        ) {
            return redirect()
                ->route('admin.dashboard');
        }

        if (
            $user->role === 'buyer' &&
            $user->status === 'active'
        ) {
            return redirect()
                ->route('buyer.home');
        }
    }

    return view('auth.login');

})->name('login');


// =====================================================
// LOGIN SUBMIT
// =====================================================

Route::post('/login', function (Request $request) {

    $validated = $request->validate([
        'login' => 'required|string',
        'password' => 'required|string',
    ]);

    $loginField = filter_var(
        $validated['login'],
        FILTER_VALIDATE_EMAIL
    )
        ? 'email'
        : 'phone';


    // Authenticate by credentials first.
    // The account role is checked only after authentication succeeds.
    $loggedIn = auth()->attempt([
        $loginField => $validated['login'],
        'password' => $validated['password'],
    ]);


    if (!$loggedIn) {

        return back()
            ->withErrors([
                'login' =>
                    'Invalid email/phone number or password.',
            ])
            ->onlyInput('login');
    }


    $request->session()->regenerate();

    $user = auth()->user();


    // =====================================================
    // SUSPENDED ACCOUNT CHECK
    // =====================================================

    if ($user->is_suspended ?? false) {

        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors([
                'login' =>
                    'This account has been suspended.',
            ])
            ->onlyInput('login');
    }


    // =====================================================
    // ACCOUNT STATUS CHECK
    // =====================================================

    if ($user->status !== 'active') {

        $status = strtolower(
            $user->status ?? ''
        );

        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();


        if ($status === 'pending') {

            return redirect()
                ->route('login')
                ->withErrors([
                    'login' =>
                        'Your account is still pending administrator approval.',
                ])
                ->onlyInput('login');
        }


        if ($status === 'rejected') {

            return redirect()
                ->route('login')
                ->withErrors([
                    'login' =>
                        'Your account application has been rejected.',
                ])
                ->onlyInput('login');
        }


        return redirect()
            ->route('login')
            ->withErrors([
                'login' =>
                    'This account is currently inactive.',
            ])
            ->onlyInput('login');
    }


    // =====================================================
    // ADMIN LOGIN
    // =====================================================

    if ($user->role === 'admin') {

        return redirect()
            ->route('admin.dashboard')
            ->with(
                'success',
                'Welcome to the SUKI SHOP Admin Portal.'
            );
    }


    // =====================================================
    // BUYER LOGIN
    // =====================================================

    if ($user->role === 'buyer') {

        $request->session()->put(
            'buyer_profile',
            [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
            ]
        );

        $request->session()->put(
            'buyer_logged_in',
            true
        );

        return redirect()
            ->intended(
                route('buyer.home')
            )
            ->with(
                'success',
                'Welcome back to SUKI SHOP!'
            );
    }


    // =====================================================
    // OTHER / UNSUPPORTED ROLES
    // =====================================================

    auth()->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()
        ->route('login')
        ->withErrors([
            'login' =>
                'This account cannot use this login portal.',
        ])
        ->onlyInput('login');

})->name('login.submit');


// =====================================================
// REGISTER PAGE
// =====================================================

Route::get('/register', function () {

    return view('auth.register');

})->name('register');


// =====================================================
// REGISTER SUBMIT
// =====================================================

Route::post('/register', function (Request $request) {

    $validated = $request->validate([
        'first_name' => 'required|string|max:100',
        'last_name' => 'required|string|max:100',
        'phone' => 'required|string|max:30|unique:users,phone',
        'email' => 'required|email|max:255|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
        'terms' => 'required',
    ]);

$user = User::create([
    'role' => 'buyer',
    'first_name' => $validated['first_name'],
    'last_name' => $validated['last_name'],
    'name' => $validated['first_name'] . ' ' . $validated['last_name'],
    'email' => $validated['email'],
    'phone' => $validated['phone'],
    'status' => 'pending',
    'password' => $validated['password'],
]);

return redirect()
    ->route('login')
    ->with(
        'success',
        'Your buyer application has been submitted. Please wait for administrator approval before logging in.'
    );

})->name('register.submit');


// =====================================================
// LOGOUT
// =====================================================

Route::post('/logout', function (Request $request) {

    auth()->logout();

    $request->session()->forget([
        'buyer_logged_in',
        'buyer_profile',
    ]);

    $request->session()->invalidate();

    $request->session()->regenerateToken();

    return redirect()
        ->route('buyer.home')
        ->with(
            'success',
            'You have been logged out successfully.'
        );

})->name('logout');
