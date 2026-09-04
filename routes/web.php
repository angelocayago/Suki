<?php

use Illuminate\Support\Facades\Route;
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
// BUYER HOME
// =====================================================

Route::get('/', function () {
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

    if (!session('buyer_logged_in')) {

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

    return null;
};


// =====================================================
// SHOPPING CART
// =====================================================

Route::get('/cart', function () use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $cart = session()->get('cart', []);

    return view('buyer.cart', [
        'cart' => $cart,
    ]);

})->name('buyer.cart');


// =====================================================
// ADD TO CART
// =====================================================

Route::post('/cart/add/{slug}', function ($slug) use ($products, $requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    if (!isset($products[$slug])) {
        abort(404);
    }

    $cart = session()->get('cart', []);

    if (isset($cart[$slug])) {

        $currentQuantity = $cart[$slug]['quantity'];
        $maxStock = $products[$slug]['stock'];

        if ($currentQuantity < $maxStock) {
            $cart[$slug]['quantity']++;
        }

    } else {

        $cart[$slug] = [
            'name' => $products[$slug]['name'],
            'price' => $products[$slug]['price'],
            'image' => $products[$slug]['image'],
            'quantity' => 1,
        ];
    }

    session()->put('cart', $cart);

    return redirect()
        ->back()
        ->with(
            'success',
            $products[$slug]['name'] . ' added to cart!'
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

    $cart = session()->get('cart', []);

    if (!isset($cart[$slug])) {
        abort(404);
    }

    $maxStock = $products[$slug]['stock'];

    if ($cart[$slug]['quantity'] < $maxStock) {
        $cart[$slug]['quantity']++;
    }

    session()->put('cart', $cart);

    return redirect()->back();

})->name('buyer.cart.increase');


// =====================================================
// DECREASE CART QUANTITY
// =====================================================

Route::post('/cart/decrease/{slug}', function ($slug) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $cart = session()->get('cart', []);

    if (!isset($cart[$slug])) {
        abort(404);
    }

    if ($cart[$slug]['quantity'] > 1) {
        $cart[$slug]['quantity']--;
    } else {
        unset($cart[$slug]);
    }

    session()->put('cart', $cart);

    return redirect()->back();

})->name('buyer.cart.decrease');


// =====================================================
// REMOVE FROM CART
// =====================================================

Route::post('/cart/remove/{slug}', function ($slug) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $cart = session()->get('cart', []);

    if (isset($cart[$slug])) {
        unset($cart[$slug]);
    }

    session()->put('cart', $cart);

    return redirect()
        ->back()
        ->with('success', 'Item removed from cart.');

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

    $cart = session()->get('cart', []);

    if (!isset($cart[$slug])) {
        abort(404);
    }

    $quantity = (int) request()->input('quantity', 1);

    $maxStock = $products[$slug]['stock'];

    $quantity = max(1, min($quantity, $maxStock));

    $cart[$slug]['quantity'] = $quantity;

    session()->put('cart', $cart);

    return redirect()
        ->back()
        ->with('success', 'Cart updated successfully.');

})->name('buyer.cart.update');


// =====================================================
// CLEAR CART
// =====================================================

Route::post('/cart/clear', function () use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    session()->forget('cart');

    return redirect()
        ->route('buyer.cart')
        ->with('success', 'Cart cleared.');

})->name('buyer.cart.clear');


// =====================================================
// WISHLIST
// =====================================================

Route::get('/wishlist', function () use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $wishlist = session()->get('wishlist', []);

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

    $wishlist = session()->get('wishlist', []);

    if (!isset($wishlist[$slug])) {

        $wishlist[$slug] = [
            'name' => $products[$slug]['name'],
            'price' => $products[$slug]['price'],
            'old_price' => $products[$slug]['old_price'],
            'rating' => $products[$slug]['rating'],
            'sold' => $products[$slug]['sold'],
            'image' => $products[$slug]['image'],
        ];
    }

    $cart = session()->get('cart', []);

    if (isset($cart[$slug])) {
        unset($cart[$slug]);
    }

    session()->put('wishlist', $wishlist);
    session()->put('cart', $cart);

    return redirect()
        ->back()
        ->with(
            'success',
            $products[$slug]['name'] . ' moved to your wishlist!'
        );

})->name('buyer.wishlist.add');


// =====================================================
// REMOVE FROM WISHLIST
// =====================================================

Route::post('/wishlist/remove/{slug}', function ($slug) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $wishlist = session()->get('wishlist', []);

    if (isset($wishlist[$slug])) {
        unset($wishlist[$slug]);
    }

    session()->put('wishlist', $wishlist);

    return redirect()
        ->back()
        ->with('success', 'Item removed from wishlist.');

})->name('buyer.wishlist.remove');


// =====================================================
// MOVE WISHLIST ITEM TO CART
// =====================================================

Route::post('/wishlist/move-to-cart/{slug}', function ($slug) use ($products, $requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    if (!isset($products[$slug])) {
        abort(404);
    }

    $wishlist = session()->get('wishlist', []);
    $cart = session()->get('cart', []);

    $maxStock = $products[$slug]['stock'];

    if (isset($cart[$slug])) {

        if ($cart[$slug]['quantity'] < $maxStock) {
            $cart[$slug]['quantity']++;
        }

    } else {

        $cart[$slug] = [
            'name' => $products[$slug]['name'],
            'price' => $products[$slug]['price'],
            'image' => $products[$slug]['image'],
            'quantity' => 1,
        ];
    }

    if (isset($wishlist[$slug])) {
        unset($wishlist[$slug]);
    }

    session()->put('cart', $cart);
    session()->put('wishlist', $wishlist);

    return redirect()
        ->back()
        ->with(
            'success',
            $products[$slug]['name'] . ' moved to your cart!'
        );

})->name('buyer.wishlist.move-to-cart');


// =====================================================
// CHECKOUT
// =====================================================

Route::get('/checkout', function () use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $cart = session('cart', []);

    if (empty($cart)) {
        return redirect()
            ->route('buyer.cart')
            ->with('error', 'Your cart is empty.');
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

    $defaultAddress = collect($addresses)
        ->firstWhere('is_default', true);

    if (!$defaultAddress && !empty($addresses)) {
        $defaultAddress = $addresses[0];
    }

    $subtotal = collect($cart)->sum(function ($item) {
        return (float) $item['price'] * (int) $item['quantity'];
    });

    $shipping = 30;

    $total = $subtotal + $shipping;

    return view('buyer.checkout', [
        'cart' => $cart,
        'addresses' => $addresses,
        'defaultAddress' => $defaultAddress,
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'total' => $total,
    ]);

})->name('buyer.checkout');


// =====================================================
// PLACE ORDER
// =====================================================

Route::post('/checkout/place-order', function (Request $request) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $cart = session('cart', []);

    if (empty($cart)) {
        return redirect()
            ->route('buyer.cart')
            ->with('error', 'Your cart is empty.');
    }

    $request->validate([
        'address_id' => 'required',
        'shipping_method' => 'required|in:jnt,flash,lbc',
        'payment_method' => 'required|in:cod,gcash',
    ]);

    $addresses = session('buyer_addresses', []);

    $selectedAddress = collect($addresses)
        ->firstWhere('id', (int) $request->address_id);

    if (!$selectedAddress) {
        return redirect()
            ->route('buyer.checkout')
            ->with('error', 'Please select a valid delivery address.');
    }

    $subtotal = collect($cart)->sum(function ($item) {
        return (float) $item['price'] * (int) $item['quantity'];
    });

    $shippingFees = [
        'jnt' => 30,
        'flash' => 30,
        'lbc' => 49,
    ];

    $shipping = $shippingFees[$request->shipping_method];

    $total = $subtotal + $shipping;

    $orderId = 'SKI-' . now()->format('YmdHis');

    $orders = session('orders', []);

    $orders[$orderId] = [
        'id' => $orderId,
        'items' => $cart,
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'total' => $total,

        'shipping_method' => $request->shipping_method,
        'payment_method' => $request->payment_method,

        'shipping_address' => $selectedAddress,

        'status' => 'placed',

        'created_at' => now()->format('Y-m-d H:i:s'),
        'updated_at' => now()->format('Y-m-d H:i:s'),

        'confirmed_at' => null,
        'preparing_at' => null,
        'ready_for_pickup_at' => null,
        'picked_up_at' => null,
        'at_sorting_center_at' => null,
        'sorted_at' => null,
        'assigned_to_rider_at' => null,
        'out_for_delivery_at' => null,
        'delivered_at' => null,
        'completed_at' => null,

        'delivery_failed_at' => null,
        'returned_at' => null,

        'cancel_reason' => null,
        'cancelled_at' => null,
    ];

    session()->put('orders', $orders);

    session()->forget('cart');

    return redirect()
        ->route('buyer.order-success', [
            'order' => $orderId,
        ])
        ->with(
            'success',
            'Your order has been placed successfully!'
        );

})->name('buyer.checkout.place-order');


// =====================================================
// ORDER SUCCESS
// =====================================================

Route::get('/order-success/{order}', function ($orderId) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $orders = session('orders', []);

    if (!isset($orders[$orderId])) {
        return redirect()
            ->route('buyer.home')
            ->with('error', 'Order not found.');
    }

    return view('buyer.order-success', [
        'order' => $orders[$orderId],
    ]);

})->name('buyer.order-success');


// =====================================================
// MY ORDERS
// =====================================================

Route::get('/my-orders', function () use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $orders = session('orders', []);

    $orders = array_reverse($orders, true);

    return view('buyer.my-orders', [
        'orders' => $orders,
    ]);

})->name('buyer.my-orders');


// =====================================================
// ORDER DETAILS
// =====================================================

Route::get('/my-orders/{order}', function ($orderId) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $orders = session('orders', []);

    if (!isset($orders[$orderId])) {
        return redirect()
            ->route('buyer.my-orders')
            ->with('error', 'Order not found.');
    }

    return view('buyer.order-details', [
        'order' => $orders[$orderId],
    ]);

})->name('buyer.order-details');


// =====================================================
// CANCEL ORDER
// =====================================================

Route::post('/my-orders/{order}/cancel', function (
    Request $request,
    $orderId
) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $orders = session('orders', []);

    if (!isset($orders[$orderId])) {
        return back()->with('error', 'Order not found.');
    }

    if (($orders[$orderId]['status'] ?? '') !== 'placed') {

        return back()->with(
            'error',
            'This order can no longer be cancelled because the seller has already started processing it.'
        );
    }

    $request->validate([
        'cancel_reason' => 'required|in:changed_mind,ordered_by_mistake,found_better_price,wrong_product,seller_requested,other',
    ]);

    $orders[$orderId]['status'] = 'cancelled';

    $orders[$orderId]['cancel_reason'] =
        $request->cancel_reason;

    $orders[$orderId]['cancelled_at'] =
        now()->format('Y-m-d H:i:s');

    $orders[$orderId]['updated_at'] =
        now()->format('Y-m-d H:i:s');

    session()->put('orders', $orders);

    return redirect()
        ->route('buyer.order-details', [
            'order' => $orderId,
        ])
        ->with(
            'success',
            'Your order has been cancelled successfully!'
        );

})->name('buyer.order.cancel');


// =====================================================
// UPDATE ORDER STATUS
// =====================================================
//
// Seller:
// confirmed → preparing → ready_for_pickup
//
// Pickup Rider:
// ready_for_pickup → picked_up → at_sorting_center
//
// Logistics:
// at_sorting_center → sorted → assigned_to_rider
//
// Delivery Rider:
// assigned_to_rider → out_for_delivery → delivered
//
// Buyer:
// delivered → completed
// =====================================================

Route::post('/orders/{order}/status', function (
    Request $request,
    $orderId
) {

    $orders = session('orders', []);

    if (!isset($orders[$orderId])) {
        return back()->with('error', 'Order not found.');
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
        'status' => 'required|in:' . implode(',', $allowedStatuses),
    ]);

    $currentStatus =
        $orders[$orderId]['status'] ?? 'placed';

    if ($currentStatus === 'completed') {
        return back()->with(
            'error',
            'This order has already been completed.'
        );
    }

    if ($currentStatus === 'cancelled') {
        return back()->with(
            'error',
            'This order has already been cancelled.'
        );
    }

    $newStatus = $request->status;

    $orders[$orderId]['status'] = $newStatus;

    $orders[$orderId]['updated_at'] =
        now()->format('Y-m-d H:i:s');

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

    if (isset($timestampFields[$newStatus])) {

        $orders[$orderId][
            $timestampFields[$newStatus]
        ] = now()->format('Y-m-d H:i:s');
    }

    session()->put('orders', $orders);

    return back()->with(
        'success',
        'Order status updated successfully.'
    );

})->name('order.status.update');


// =====================================================
// BUY AGAIN
// =====================================================

Route::post('/my-orders/{order}/buy-again', function (
    $orderId
) use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $orders = session('orders', []);

    if (!isset($orders[$orderId])) {

        return redirect()
            ->route('buyer.my-orders')
            ->with('error', 'Order not found.');
    }

    $cart = session('cart', []);

    foreach ($orders[$orderId]['items'] as $slug => $item) {

        if (isset($cart[$slug])) {

            $cart[$slug]['quantity'] += $item['quantity'];

        } else {

            $cart[$slug] = $item;
        }
    }

    session()->put('cart', $cart);

    return redirect()
        ->route('buyer.cart')
        ->with(
            'success',
            'Items from your previous order have been added to your cart!'
        );

})->name('buyer.order.buy-again');


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
            'Your SUKI Seller account has been created successfully!'
        );

})->name('seller.register.submit');


// =====================================================
// SELLER DASHBOARD
// =====================================================

Route::get('/seller', function () {

    return view('seller.dashboard');

})->name('seller.dashboard');


// =====================================================
// SELLER PRODUCTS
// =====================================================

Route::get('/seller/products', function () {

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

Route::post('/seller/products', function (Request $request) {

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

Route::get('/seller/products/{product}/edit', function ($productId) {

    $products = session()->get('seller_products', []);

    if (!isset($products[$productId])) {
        abort(404);
    }

    return view('seller.products-edit');

})->name('seller.products.edit');


// =====================================================
// SELLER UPDATE PRODUCT
// =====================================================

Route::put('/seller/products/{product}', function (
    Request $request,
    $productId
) {

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

    $products[$productId]['name'] =
        $request->name;

    $products[$productId]['category'] =
        $request->category;

    $products[$productId]['brand'] =
        $request->brand;

    $products[$productId]['description'] =
        $request->description;

    $products[$productId]['price'] =
        (float) $request->price;

    $products[$productId]['stock'] =
        (int) $request->stock;

    $products[$productId]['sku'] =
        $request->sku;

    $products[$productId]['status'] =
        $request->status;

    $products[$productId]['weight'] =
        $request->weight;

    $products[$productId]['length'] =
        $request->length;

    $products[$productId]['width'] =
        $request->width;

    $products[$productId]['height'] =
        $request->height;

    $products[$productId]['updated_at'] =
        now()->format('Y-m-d H:i:s');

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

Route::get('/seller/inventory', function () {

    return view('seller.inventory');

})->name('seller.inventory');


Route::post('/seller/inventory/{product}/increase', function ($productId) {

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


Route::post('/seller/inventory/{product}/decrease', function ($productId) {

    $products = session()->get('seller_products', []);

    if (!isset($products[$productId])) {
        abort(404);
    }

    $currentStock =
        (int) ($products[$productId]['stock'] ?? 0);

    if ($currentStock > 0) {
        $products[$productId]['stock'] =
            $currentStock - 1;
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
        'terms' => 'required',
    ]);

    session()->put('rider_application', [

        'first_name' =>
            $validated['first_name'],

        'last_name' =>
            $validated['last_name'],

        'phone' =>
            $validated['phone'],

        'email' =>
            $validated['email'],

        'address' =>
            $validated['address'],

        'vehicle_type' =>
            $validated['vehicle_type'],

        'plate_number' =>
            $validated['plate_number'] ?? '',

        'license_number' =>
            $validated['license_number'],

        // Application starts as pending.
        // Admin can approve this later.
        'status' =>
            'pending',

        'created_at' =>
            now()->format('Y-m-d H:i:s'),
    ]);

    return redirect()
        ->route('rider.dashboard')
        ->with(
            'success',
            'Your SUKI Rider application has been submitted successfully.'
        );

})->name('rider.apply.submit');


// =====================================================
// RIDER DASHBOARD
// =====================================================

Route::get('/rider/dashboard', function () {

    if (!session()->has('rider_application')) {

        return redirect()
            ->route('rider.apply')
            ->with(
                'error',
                'Please submit your rider application first.'
            );
    }

    $application =
        session('rider_application');

    return view('rider.dashboard', [
        'application' =>
            $application,
    ]);

})->name('rider.dashboard');


// =====================================================
// RIDER DELIVERIES
// =====================================================

Route::get('/rider/deliveries', function () {

    if (!session()->has('rider_application')) {

        return redirect()
            ->route('rider.apply')
            ->with(
                'error',
                'Please submit your rider application first.'
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

    if (!session()->has('rider_application')) {

        return redirect()
            ->route('rider.apply')
            ->with(
                'error',
                'Please submit your rider application first.'
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
// RIDER ERP STATUS ACTIONS
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
// Logistics:
//
// at_sorting_center
//        ↓
// sorted
//        ↓
// assigned_to_rider
//
// Delivery Rider:
//
// assigned_to_rider
//        ↓
// out_for_delivery
//        ↓
// delivered
//
// Buyer:
//
// delivered
//        ↓
// completed
// =====================================================

Route::post('/rider/orders/{order}/status', function (
    Request $request,
    $orderId
) {

    if (!session()->has('rider_application')) {

        return redirect()
            ->route('rider.apply')
            ->with(
                'error',
                'Please submit your rider application first.'
            );
    }

    $orders =
        session('orders', []);

    if (!isset($orders[$orderId])) {

        return back()->with(
            'error',
            'Order not found.'
        );
    }

    $currentStatus =
        $orders[$orderId]['status'] ?? 'placed';

    $allowedTransitions = [

        'ready_for_pickup' =>
            'picked_up',

        'picked_up' =>
            'at_sorting_center',

        'assigned_to_rider' =>
            'out_for_delivery',

        'out_for_delivery' =>
            'delivered',
    ];

    if (!isset($allowedTransitions[$currentStatus])) {

        return back()->with(
            'error',
            'This order cannot be updated by the rider at its current status.'
        );
    }

    $requestedStatus =
        $request->input('status');

    $expectedStatus =
        $allowedTransitions[$currentStatus];

    if ($requestedStatus !== $expectedStatus) {

        return back()->with(
            'error',
            'Invalid ERP status transition.'
        );
    }

    $orders[$orderId]['status'] =
        $requestedStatus;

    $orders[$orderId]['updated_at'] =
        now()->format('Y-m-d H:i:s');

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

    if (isset($timestampFields[$requestedStatus])) {

        $orders[$orderId][
            $timestampFields[$requestedStatus]
        ] =
            now()->format('Y-m-d H:i:s');
    }

    $rider =
        session('rider_application');

    $orders[$orderId]['rider'] = [

        'first_name' =>
            $rider['first_name'] ?? '',

        'last_name' =>
            $rider['last_name'] ?? '',

        'phone' =>
            $rider['phone'] ?? '',
    ];

    session()->put(
        'orders',
        $orders
    );

    $messages = [

        'picked_up' =>
            'Order picked up successfully.',

        'at_sorting_center' =>
            'Order marked as arrived at the sorting center.',

        'out_for_delivery' =>
            'Delivery has started.',

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

    $orders =
        session('orders', []);

    if (!isset($orders[$orderId])) {

        return back()->with(
            'error',
            'Order not found.'
        );
    }

    $currentStatus =
        $orders[$orderId]['status'] ?? 'placed';

    $allowedTransitions = [

        'at_sorting_center' =>
            'sorted',

        'sorted' =>
            'assigned_to_rider',
    ];

    if (!isset($allowedTransitions[$currentStatus])) {

        return back()->with(
            'error',
            'This order cannot be updated by Logistics at its current status.'
        );
    }

    $requestedStatus =
        $request->input('status');

    $expectedStatus =
        $allowedTransitions[$currentStatus];

    if ($requestedStatus !== $expectedStatus) {

        return back()->with(
            'error',
            'Invalid Logistics ERP status transition.'
        );
    }

    $orders[$orderId]['status'] =
        $requestedStatus;

    $orders[$orderId]['updated_at'] =
        now()->format('Y-m-d H:i:s');

    $timestampFields = [

        'sorted' =>
            'sorted_at',

        'assigned_to_rider' =>
            'assigned_to_rider_at',
    ];

    if (isset($timestampFields[$requestedStatus])) {

        $orders[$orderId][
            $timestampFields[$requestedStatus]
        ] =
            now()->format('Y-m-d H:i:s');
    }

    session()->put(
        'orders',
        $orders
    );

    $messages = [

        'sorted' =>
            'Shipment marked as sorted successfully.',

        'assigned_to_rider' =>
            'Shipment assigned to delivery rider successfully.',
    ];

    return back()->with(
        'success',
        $messages[$requestedStatus]
            ?? 'Shipment status updated successfully.'
    );

})->name('logistics.order.status');


// =====================================================
// AUTHENTICATION
// =====================================================


// =====================================================
// LOGIN PAGE
// =====================================================

Route::get('/login', function () {

    return view('auth.login');

})->name('login');


// =====================================================
// LOGIN SUBMIT
// =====================================================

Route::post('/login', function (Request $request) {

    $request->validate([
        'login' => 'required|string',
        'password' => 'required|string',
    ]);

    $request->session()->put(
        'buyer_logged_in',
        true
    );

    return redirect()
        ->intended(route('buyer.home'))
        ->with(
            'success',
            'Welcome back to SUKI!'
        );

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

    $request->validate([
        'first_name' => 'required|string|max:100',
        'last_name' => 'required|string|max:100',
        'phone' => 'required|string|max:30',
        'email' => 'required|email|max:255',
        'password' => 'required|string|min:8|confirmed',
        'terms' => 'required',
    ]);

    $request->session()->put('buyer_profile', [

        'first_name' =>
            $request->first_name,

        'last_name' =>
            $request->last_name,

        'email' =>
            $request->email,

        'phone' =>
            $request->phone,
    ]);

    $request->session()->put(
        'buyer_logged_in',
        true
    );

    return redirect()
        ->intended(route('buyer.home'))
        ->with(
            'success',
            'Your SUKI Buyer account has been created successfully!'
        );

})->name('register.submit');


// =====================================================
// LOGOUT
// =====================================================

Route::post('/logout', function (Request $request) {

    $request->session()->forget(
        'buyer_logged_in'
    );

    return redirect()
        ->route('buyer.home')
        ->with(
            'success',
            'You have been logged out successfully.'
        );

})->name('logout');