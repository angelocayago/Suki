<?php

use App\Models\User;
use App\Support\SellerAccount;
use App\Http\Controllers\Seller\StoreProfileController;
use App\Http\Controllers\Seller\MyProfileController;
use App\Models\BuyerEmailVerification;
use App\Models\Seller;
use App\Models\Address;
use App\Models\SellerDocument;
use App\Models\CartItem;
use App\Models\WishlistItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\LogisticsProvider;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Middleware\SuperAdminMiddleware;

use App\Http\Controllers\SuperAdmin\UserManagementController;
use App\Http\Controllers\SuperAdmin\BuyerManagementController;
use App\Http\Controllers\SuperAdmin\SellerManagementController;
use App\Http\Controllers\SuperAdmin\ApplicationController;
use App\Http\Controllers\SuperAdmin\OrderManagementController;
use App\Http\Controllers\SuperAdmin\CommissionController;
use App\Http\Controllers\SuperAdmin\ReportController;
use App\Http\Controllers\SuperAdmin\SettingController;
use App\Http\Controllers\Auth\BuyerEmailVerificationController;
use App\Services\BuyerEmailVerificationService;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

Route::get('/shop', function (Request $request) use ($products) {
    $selectedCategories = array_values(array_filter(
        (array) $request->query('category', []),
        fn ($category) => is_string($category) && $category !== ''
    ));
    $search = $request->query('search');
    $search = is_string($search) ? trim($search) : '';
    $minPrice = is_numeric($request->query('min_price'))
        ? (float) $request->query('min_price')
        : null;
    $maxPrice = is_numeric($request->query('max_price'))
        ? (float) $request->query('max_price')
        : null;
    $rating = in_array((string) $request->query('rating'), ['3', '4', '5'], true)
        ? (float) $request->query('rating')
        : null;
    $sort = $request->query('sort', 'recommended');
    $sort = in_array($sort, ['recommended', 'newest', 'best_selling', 'price_asc', 'price_desc'], true)
        ? $sort
        : 'recommended';

    $filteredProducts = collect($products)
        ->map(fn (array $product, string $slug) => array_merge($product, ['slug' => $slug]))
        ->filter(function (array $product) use ($selectedCategories, $search, $minPrice, $maxPrice, $rating) {
            $price = (float) str_replace(',', '', $product['price']);

            if ($selectedCategories && !in_array($product['category'], $selectedCategories, true)) {
                return false;
            }

            if ($search !== '' && stripos($product['name'], $search) === false) {
                return false;
            }

            if (($minPrice !== null && $price < $minPrice) || ($maxPrice !== null && $price > $maxPrice)) {
                return false;
            }

            return $rating === null || (float) $product['rating'] >= $rating;
        });

    if ($sort === 'newest') {
        $filteredProducts = $filteredProducts->reverse();
    } elseif ($sort === 'best_selling') {
        $filteredProducts = $filteredProducts->sortByDesc(function (array $product) {
            $sales = strtolower((string) $product['sold']);
            $multiplier = str_contains($sales, 'k') ? 1000 : 1;

            return (float) preg_replace('/[^0-9.]/', '', $sales) * $multiplier;
        });
    } elseif ($sort === 'price_asc') {
        $filteredProducts = $filteredProducts->sortBy(fn (array $product) => (float) str_replace(',', '', $product['price']));
    } elseif ($sort === 'price_desc') {
        $filteredProducts = $filteredProducts->sortByDesc(fn (array $product) => (float) str_replace(',', '', $product['price']));
    }

    return view('buyer.shop', [
        'products' => $filteredProducts->values()->all(),
        'selectedCategories' => $selectedCategories,
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

    if (!Auth::check()) {

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

    /** @var User $user */
    $user = Auth::user();

    if (
        $user->hasRole('buyer') &&
        $user->email_verified_at === null &&
        !\App\Support\BuyerAccess::localException($user) &&
        BuyerEmailVerification::query()
            ->where('user_id', $user->id)
            ->exists()
    ) {
        $userId = $user->id;
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();
        request()->session()->put('buyer_verification_user_id', $userId);

        return redirect()
            ->route('buyer.verification.show')
            ->with('error', 'Please verify your email address before accessing your account.');
    }

    if (
        !$user->hasRole('buyer') ||
        !\App\Support\BuyerAccess::approved($user) ||
        $user->is_suspended
    ) {
        Auth::logout();

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

$requireAdmin = function () {

    if (!Auth::check()) {
        return redirect()
            ->route('admin.login')
            ->with('error', 'Please log in as an administrator.');
    }

    /** @var User $user */
    $user = Auth::user();

    if (
        !$user->hasRole('admin') &&
        !$user->hasRole('superadmin')
    ) {
        abort(403);
    }

    return null;
};


// =====================================================
// PSGC API PROXY
// =====================================================

$fetchPsgcData = function (string $path): ?array {
    try {
        $response = Http::acceptJson()
            ->timeout(10)
            ->get('https://psgc.cloud/api/v2/' . $path);
    } catch (\Illuminate\Http\Client\ConnectionException $exception) {
        report($exception);

        return null;
    }

    if (! $response->successful()) {
        return null;
    }

    $data = $response->json('data');

    if (! is_array($data)) {
        return null;
    }

    foreach ($data as &$item) {
        if (! is_array($item) || ! is_string($item['name'] ?? null)) {
            continue;
        }

        if (
            preg_match('//u', $item['name']) !== 1 ||
            preg_match('/[^\x{0000}-\x{00FF}]/u', $item['name']) === 1
        ) {
            continue;
        }

        // Some PSGC names are UTF-8 bytes decoded as Latin-1 upstream.
        $reinterpretedName = iconv('UTF-8', 'ISO-8859-1', $item['name']);

        if (
            $reinterpretedName !== false &&
            preg_match('//u', $reinterpretedName) === 1
        ) {
            $item['name'] = $reinterpretedName;
        }
    }
    unset($item);

    return $data;
};

Route::get('/api/psgc/regions', function () use ($fetchPsgcData) {
    $regions = $fetchPsgcData('regions');

    if ($regions === null) {
        return response()->json([
            'message' => 'Unable to load regions.',
        ], 502);
    }

    return response()->json($regions);
});

Route::get('/api/psgc/regions/{region}/provinces', function (string $region) use ($fetchPsgcData) {
    $provinces = $fetchPsgcData(
        'regions/' . rawurlencode($region) . '/provinces'
    );

    if ($provinces === null) {
        return response()->json([
            'message' => 'Unable to load provinces.',
        ], 502);
    }

    return response()->json($provinces);
});

Route::get('/api/psgc/regions/{region}/cities-municipalities', function (string $region) use ($fetchPsgcData) {
    $municipalities = $fetchPsgcData(
        'regions/' . rawurlencode($region) . '/cities-municipalities'
    );

    if ($municipalities === null) {
        return response()->json([
            'message' => 'Unable to load municipalities/cities.',
        ], 502);
    }

    return response()->json($municipalities);
});

Route::get('/api/psgc/provinces', function () use ($fetchPsgcData) {
    $provinces = $fetchPsgcData('provinces');

    if ($provinces === null) {
        return response()->json([
            'message' => 'Unable to load provinces.',
        ], 502);
    }

    return response()->json($provinces);
});

Route::get('/api/psgc/provinces/{province}/cities-municipalities', function (string $province) use ($fetchPsgcData) {
    $municipalities = $fetchPsgcData(
        'provinces/' . rawurlencode($province) . '/cities-municipalities'
    );

    if ($municipalities === null) {
        return response()->json([
            'message' => 'Unable to load municipalities/cities.',
        ], 502);
    }

    return response()->json($municipalities);
});

Route::get('/api/psgc/cities-municipalities/{municipality}/barangays', function (string $municipality) use ($fetchPsgcData) {
    $barangays = $fetchPsgcData(
        'cities-municipalities/' . rawurlencode($municipality) . '/barangays'
    );

    if ($barangays === null) {
        return response()->json([
            'message' => 'Unable to load barangays.',
        ], 502);
    }

    return response()->json($barangays);
});


// =====================================================
// SHOPPING CART
// =====================================================

Route::get('/cart', function () use ($requireBuyer) {

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    /** @var User $user */
    $user = Auth::user();

    $cart = $user
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

    $user = Auth::user();

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

    $user = Auth::user();

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

    $user = Auth::user();

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

    $user = Auth::user();

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

    $user = Auth::user();

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

    $user = Auth::user();

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

    /** @var User $user */
    $user = Auth::user();

    $wishlist = $user
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

    $user = Auth::user();

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

    $user = Auth::user();

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

    $user = Auth::user();

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

    $user = Auth::user();

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

    /** @var User $user */
    $user = Auth::user();


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


        /** @var User $user */
        $user = Auth::user();


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

                        /** @var User $user */


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
                    Auth::id()
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
                    Auth::id()
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
                    Auth::id()
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
                Auth::id()
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
    ) use ($requireAdmin) {

        if ($redirect = $requireAdmin()) {
            return $redirect;
        }


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
                    Auth::id()
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
            /** @var User $user */
            $user = Auth::user();


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

    if (Auth::check()) {
        SellerAccount::sync(SellerAccount::current());
        return null;
    }

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

Route::get('/seller/orders', function () use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    return view('seller.orders', \App\Support\SellerOrders::data(\App\Support\SellerAccount::current(), request()));

})->name('seller.orders');

Route::get('/seller/orders/{order}', function (Request $request, string $order) use ($requireSeller) {
    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    return view('seller.order-details', \App\Support\SellerOrders::details(\App\Support\SellerAccount::current(), $order) + [
        'backUrl' => \App\Support\SellerOrders::backUrl($request),
    ]);
})->whereNumber('order')->name('seller.orders.show');


// =====================================================
// SELLER REGISTRATION
// =====================================================

Route::get('/seller/register', function () {

    return view('seller.register', [
        'sellerTypes' => config('seller_types', []),
        'sellerDocumentRequirements' => config(
            'seller_document_requirements.requirements',
            []
        ),
        'sellerDocumentLabels' => config(
            'seller_document_requirements.labels',
            []
        ),
        'latestAllowedBirthday' => \Illuminate\Support\Carbon::today('Asia/Manila')
            ->subYears(18)
            ->toDateString(),
    ]);

})->name('seller.register');


Route::post('/seller/register', function (Request $request) use ($fetchPsgcData) {

    $latestAllowedBirthday = \Illuminate\Support\Carbon::today('Asia/Manila')
        ->subYears(18)
        ->toDateString();

    $submittedPhone = $request->input('phone');
    $normalizedPhone = null;

    if (is_string($submittedPhone)) {
        if (preg_match('/\A09[0-9]{9}\z/', $submittedPhone) === 1) {
            $normalizedPhone = $submittedPhone;
        } elseif (preg_match('/\A\+639[0-9]{9}\z/', $submittedPhone) === 1) {
            $normalizedPhone = '0' . substr($submittedPhone, 3);
        }
    }

    $tin = $request->input('tin');
    $tinIsValid = is_string($tin) && (
        preg_match('/\A[0-9]{12}\z/', $tin) === 1 ||
        preg_match('/\A[0-9]{3}(?:-[0-9]{3}){3}\z/', $tin) === 1
    );
    $normalizedTin = is_string($tin)
        ? str_replace('-', '', $tin)
        : '';

    $sellerDocumentRequirements = config(
        'seller_document_requirements.requirements',
        []
    );
    $sellerDocumentLabels = config(
        'seller_document_requirements.labels',
        []
    );
    $requiredDocuments = $sellerDocumentRequirements[
        $request->input('seller_type')
    ] ?? [];
    $documentRules = [];

    foreach (array_keys($sellerDocumentLabels) as $documentType) {
        if ($documentType === 'business_permit') {
            $documentRules[$documentType] = [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ];
        } elseif (in_array($documentType, $requiredDocuments, true)) {
            $documentRules[$documentType] = [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ];
        } else {
            $documentRules[$documentType] = ['prohibited'];
        }
    }

    $documentValidationMessages = [];
    $documentValidationAttributes = [];

    foreach ($sellerDocumentLabels as $documentType => $documentLabel) {
        $documentValidationMessages[$documentType . '.required'] =
            $documentLabel . ' is required for the selected seller type.';
        $documentValidationMessages[$documentType . '.mimes'] =
            $documentLabel . ' must be a JPG, PNG, or PDF file.';
        $documentValidationMessages[$documentType . '.max'] =
            $documentLabel . ' may not exceed 5 MB.';
        $documentValidationAttributes[$documentType] = $documentLabel;
    }

    $validated = $request->validate([

    'first_name' => [
        'required',
        'string',
        'min:2',
        'max:50',
        'regex:/\A\p{L}+(?:[\x20\x27\x2D]\p{L}+)*\z/u',
    ],

    'last_name' => [
        'required',
        'string',
        'min:2',
        'max:50',
        'regex:/\A\p{L}+(?:[\x20\x27\x2D]\p{L}+)*\z/u',
    ],

    'middle_initial' => [
        'nullable',
        'string',
        'max:2',
        'regex:/\A\p{L}+\z/u',
    ],

    'sex' => [
        'required',
        'string',
        \Illuminate\Validation\Rule::in(['Male', 'Female']),
    ],

    'birthday' => [
        'bail',
        'required',
        'date',
        'before_or_equal:' . $latestAllowedBirthday,
        function ($attribute, $value, $fail) {
            $birthday = \Illuminate\Support\Carbon::parse(
                $value,
                'Asia/Manila'
            );

            if ($birthday->age < 18) {
                $fail('You must be at least 18 years old to register as a seller.');
            }
        },
    ],

    'email' => 'required|email|max:255|unique:users,email',
    'phone' => [
        'bail',
        'required',
        'string',
        function ($attribute, $value, $fail) use ($normalizedPhone) {
            if ($normalizedPhone === null) {
                $fail('Enter a valid Philippine mobile number in local or +63 format.');

                return;
            }

            $internationalPhone = '+63' . substr($normalizedPhone, 1);
            $phoneAlreadyRegistered = User::query()
                ->where(function ($query) use ($normalizedPhone, $internationalPhone) {
                    $query->where('phone', $normalizedPhone)
                        ->orWhere('phone', $internationalPhone);
                })
                ->exists();

            if ($phoneAlreadyRegistered) {
                $fail('This phone number has already been taken.');
            }
        },
    ],


    'region' => ['required', 'string', 'regex:/\A[0-9]{10}\z/'],
    'province' => ['nullable', 'string', 'regex:/\A[0-9]{10}\z/'],
    'municipality' => ['required', 'string', 'regex:/\A[0-9]{10}\z/'],
    'barangay' => ['required', 'string', 'regex:/\A[0-9]{10}\z/'],
    'address' => 'required|string|max:255',
    'postal_code' => ['required', 'string', 'regex:/\A[0-9]{4}\z/'],


    'business_name' => 'required|string|min:3|max:30',

    'business_category' => [
        'required',
        'string',
        \Illuminate\Validation\Rule::in(array_keys(config('seller_categories', []))),
    ],

    'seller_type' => [
        'required',
        'string',
        \Illuminate\Validation\Rule::in(config('seller_types', [])),
    ],

    'tin' => [
        'required',
        function ($attribute, $value, $fail) use ($tinIsValid, $normalizedTin) {
            if (! $tinIsValid) {
                $fail('TIN must contain exactly 12 digits, displayed as 000-000-000-000.');

                return;
            }

            if (Seller::query()->where('tin', $normalizedTin)->exists()) {
                $fail('This TIN is already registered.');
            }
        },
    ],

    ...$documentRules,
    'password' => 'required|string|min:8',
    'password_confirmation' => 'required|string|same:password',

    'terms' => 'required',

], [
    'first_name.required' => 'First name is required.',
    'first_name.min' => 'First name must be at least 2 characters.',
    'first_name.max' => 'First name may not exceed 50 characters.',
    'first_name.regex' => 'First name must contain letters only, with spaces, hyphens, or apostrophes allowed between words.',
    'last_name.required' => 'Last name is required.',
    'last_name.min' => 'Last name must be at least 2 characters.',
    'last_name.max' => 'Last name may not exceed 50 characters.',
    'last_name.regex' => 'Last name must contain letters only, with spaces, hyphens, or apostrophes allowed between words.',
    'middle_initial.regex' => 'Middle initial may contain letters only.',
    'middle_initial.max' => 'Middle initial may not exceed 2 characters.',
    'sex.required' => 'Select your sex.',
    'sex.in' => 'Select a valid sex.',
    'birthday.required' => 'Birthday is required.',
    'birthday.date' => 'Enter a valid birthday.',
    'birthday.before_or_equal' => 'You must be at least 18 years old to register as a seller.',
    'email.required' => 'Email is required.',
    'email.email' => 'Enter a valid email address.',
    'email.unique' => 'This email address has already been taken.',
    'phone.required' => 'Contact number is required.',
    'region.required' => 'Select a region.',
    'region.regex' => 'Select a valid region.',
    'province.required' => 'Select a province.',
    'province.regex' => 'Select a valid province.',
    'municipality.required' => 'Select a city or municipality.',
    'municipality.regex' => 'Select a valid city or municipality.',
    'barangay.required' => 'Select a barangay.',
    'barangay.regex' => 'Select a valid barangay.',
    'address.required' => 'Street address is required.',
    'address.max' => 'Street address may not exceed 255 characters.',
    'postal_code.required' => 'Postal code is required.',
    'postal_code.regex' => 'Postal code must contain exactly four numerical digits.',
    'business_name.required' => 'Business name is required.',
    'business_name.min' => 'Business name must be between 3 and 30 characters.',
    'business_name.max' => 'Business name must be between 3 and 30 characters.',
    'business_category.required' => 'Select a line of business.',
    'business_category.in' => 'Select a valid line of business.',
    'seller_type.required' => 'Select a seller type.',
    'seller_type.in' => 'Select a valid seller type.',
    'tin.required' => 'TIN is required.',
    'password.required' => 'Password is required.',
    'password.min' => 'Password must be at least 8 characters.',
    'password_confirmation.required' => 'Confirm password is required.',
    'password_confirmation.same' => 'Password confirmation must match the password.',
    'terms.required' => 'You must agree to the terms and policies.',
    ] + $documentValidationMessages, $documentValidationAttributes);

    $regions = $fetchPsgcData('regions');

    if ($regions === null) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'region' => 'Address data could not be verified. Please try again later.',
        ]);
    }

    $region = collect($regions)->firstWhere('code', $validated['region']);
    $locationErrors = [];

    if ($region === null) {
        $locationErrors['region'] = 'Select a valid region.';
    }

    $province = null;
    $municipalities = [];

    if ($region !== null) {
        $regionPath = 'regions/' . rawurlencode($region['code']);
        $provinces = $fetchPsgcData($regionPath . '/provinces');

        if ($provinces === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'region' => 'Address data could not be verified. Please try again later.',
            ]);
        }

        if ($provinces !== []) {
            if (empty($validated['province'])) {
                $locationErrors['province'] = 'Select a province.';
            } else {
                $province = collect($provinces)->firstWhere(
                    'code',
                    $validated['province']
                );

                if ($province === null) {
                    $locationErrors['province'] = 'Select a province in the selected region.';
                } else {
                    $municipalities = $fetchPsgcData(
                        'provinces/' . rawurlencode($province['code']) . '/cities-municipalities'
                    );
                }
            }
        } else {
            if (! empty($validated['province'])) {
                $locationErrors['province'] = 'This region does not have provinces.';
            }

            $municipalities = $fetchPsgcData(
                $regionPath . '/cities-municipalities'
            );
        }

        if ($municipalities === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'region' => 'Address data could not be verified. Please try again later.',
            ]);
        }
    }

    $municipality = collect($municipalities)->firstWhere(
        'code',
        $validated['municipality']
    );

    if ($municipality === null) {
        $locationErrors['municipality'] = 'Select a city or municipality in the selected administrative area.';
    }

    $barangay = null;

    if ($municipality !== null) {
        $barangays = $fetchPsgcData(
            'cities-municipalities/'
            . rawurlencode($municipality['code'])
            . '/barangays'
        );

        if ($barangays === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'region' => 'Address data could not be verified. Please try again later.',
            ]);
        }

        $barangay = collect($barangays)->firstWhere(
            'code',
            $validated['barangay']
        );

        if ($barangay === null) {
            $locationErrors['barangay'] = 'Select a barangay in the selected city or municipality.';
        }
    }

    if ($locationErrors !== []) {
        throw \Illuminate\Validation\ValidationException::withMessages(
            $locationErrors
        );
    }

    $resolvedAddress = [
        'region' => $region['name'],
        'province' => $province['name'] ?? null,
        'city' => $municipality['name'],
        'barangay' => $barangay['name'],
    ];

    $sellerSlug = Str::slug($request->business_name);
    $storedDocumentPaths = [];

    try {
        $documentPaths = DB::transaction(function () use (
            $request,
            $normalizedPhone,
            $normalizedTin,
            $sellerSlug,
            $resolvedAddress,
            $requiredDocuments,
            &$storedDocumentPaths
        ) {
            $user = User::create([
                'role' => 'seller',
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'name' => trim($request->first_name . ' ' . $request->last_name),
                'email' => $request->email,
                'phone' => $normalizedPhone,
                'status' => 'active',
                'password' => $request->password,
            ]);

            $address = Address::create([
                'user_id' => $user->id,
                'label' => 'Pickup',
                'recipient' => trim($request->first_name . ' ' . $request->last_name),
                'phone' => $normalizedPhone,
                'line1' => $request->address,
                'region' => $resolvedAddress['region'],
                'province' => $resolvedAddress['province'] ?? '',
                'city' => $resolvedAddress['city'],
                'barangay' => $resolvedAddress['barangay'],
                'postal_code' => $request->postal_code,
                'is_default' => true,
            ]);

            $baseSlug = $sellerSlug !== ''
                ? $sellerSlug
                : 'seller-' . $user->id;
            $uniqueSlug = $baseSlug;
            $suffix = 1;

            while (Seller::query()->where('slug', $uniqueSlug)->exists()) {
                $uniqueSlug = $baseSlug . '-' . $suffix++;
            }

            $seller = Seller::create([
                'user_id' => $user->id,
                'name' => $request->business_name,
                'slug' => $uniqueSlug,
                'business_category' => $request->business_category,
                'seller_type' => $request->seller_type,
                'tin' => $normalizedTin,
                'pickup_address_id' => $address->id,
            ]);

            $documentTypes = array_unique([
                ...$requiredDocuments,
                ...($request->hasFile('business_permit')
                    ? ['business_permit']
                    : []),
            ]);
            $documentPaths = [];

            foreach ($documentTypes as $documentType) {
                $file = $request->file($documentType);
                $directory = 'registration/' . $seller->id;
                $filename = Str::uuid()->toString() . '.' . $file->extension();
                $path = $directory . '/' . $filename;
                $storedDocumentPaths[] = $path;

                if ($file->storeAs($directory, $filename, 'seller_documents') !== $path) {
                    throw new \RuntimeException(
                        'A seller document could not be stored securely.'
                    );
                }

                $documentPaths[$documentType] = $path;

                SellerDocument::create([
                    'seller_id' => $seller->id,
                    'document_type' => $documentType,
                    'path' => $path,
                ]);
            }

            return $documentPaths;
        });
    } catch (\Throwable $exception) {
        if ($storedDocumentPaths !== []) {
            try {
                if (! Storage::disk('seller_documents')->delete($storedDocumentPaths)) {
                    report(new \RuntimeException(
                        'Seller document cleanup failed after registration rollback.'
                    ));
                }
            } catch (\Throwable $cleanupException) {
                report($cleanupException);
            }
        }

        throw $exception;
    }



    // Registration creates a pending store; it does not grant approval or authenticate it.
    session()->forget(['seller_profile', 'seller_logged_in']);

    return redirect()->route('login')->with(
        'success',
        app()->environment('local')
            ? 'Your seller registration was saved. You can sign in for local development while approval is pending.'
            : 'Your seller registration was saved. You can sign in after your store is approved.'
    );

})->name('seller.register.submit');


// =====================================================
// SELLER DASHBOARD
// =====================================================

Route::get('/seller', function () use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    \App\Support\SellerProducts::sync(\App\Support\SellerAccount::current());
    return view('seller.dashboard');

})->name('seller.dashboard');


// =====================================================
// SELLER STORE PROFILE
// =====================================================

Route::get('/seller/store-profile', function (Request $request) use ($requireSeller) {
    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    return app(StoreProfileController::class)->show();
})->name('seller.store.profile');

Route::post('/seller/store-profile/profile', function (Request $request) use ($requireSeller) {
    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    return app(StoreProfileController::class)->update($request);
})->name('seller.store.profile.update');

Route::post('/seller/store-profile/photo/remove', function (Request $request) use ($requireSeller) {
    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    return app(StoreProfileController::class)->removeImage($request);
})->name('seller.store.profile.photo.remove');

Route::post('/seller/store-profile/address', function (Request $request) use ($requireSeller, $fetchPsgcData) {
    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    return app(StoreProfileController::class)->updateAddress($request, $fetchPsgcData);
})->name('seller.store.address.update');

Route::post('/seller/store-profile/password', function (Request $request) use ($requireSeller) {
    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    return app(StoreProfileController::class)->updatePassword($request);
})->name('seller.store.password.update');


// =====================================================
// SELLER PERSONAL PROFILE
// =====================================================

Route::middleware('auth')->group(function () use ($requireSeller, $fetchPsgcData) {
    Route::get('/seller/my-profile', function (Request $request) use ($requireSeller) {
        if ($redirect = $requireSeller()) {
            return $redirect;
        }

        return app(MyProfileController::class)->show();
    })->name('seller.profile');

    Route::post('/seller/my-profile', function (Request $request) use ($requireSeller) {
        if ($redirect = $requireSeller()) {
            return $redirect;
        }

        return app(MyProfileController::class)->update($request);
    })->name('seller.profile.update');

    Route::post('/seller/my-profile/photo', function (Request $request) use ($requireSeller) {
        if ($redirect = $requireSeller()) {
            return $redirect;
        }

        return app(MyProfileController::class)->updatePhoto($request);
    })->name('seller.profile.photo.update');

    Route::post('/seller/my-profile/photo/remove', function (Request $request) use ($requireSeller) {
        if ($redirect = $requireSeller()) {
            return $redirect;
        }

        return app(MyProfileController::class)->removePhoto();
    })->name('seller.profile.photo.remove');

    Route::post('/seller/my-profile/address', function (Request $request) use ($requireSeller, $fetchPsgcData) {
        if ($redirect = $requireSeller()) {
            return $redirect;
        }

        return app(MyProfileController::class)->updateAddress($request, $fetchPsgcData);
    })->name('seller.profile.address.update');
});

// =====================================================
// SELLER PRODUCTS
// =====================================================

Route::get('/seller/products', function () use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    return view('seller.products', \App\Support\SellerCatalogueFilter::page(\App\Support\SellerAccount::current(), request()));

})->name('seller.products');


// =====================================================
// SELLER ADD PRODUCT
// =====================================================

Route::get('/seller/products/create', function () use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    return app(\App\Http\Controllers\Seller\ProductController::class)->create();

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

    return app(\App\Http\Controllers\Seller\ProductController::class)->store($request);

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

    return app(\App\Http\Controllers\Seller\ProductController::class)->edit($productId);

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

    return app(\App\Http\Controllers\Seller\ProductController::class)->update($request, $productId);

})->name('seller.products.update');


// =====================================================
// SELLER INVENTORY
// =====================================================

Route::get('/seller/inventory', function () use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    return view('seller.inventory', \App\Support\SellerCatalogueFilter::page(\App\Support\SellerAccount::current(), request()));

})->name('seller.inventory');


Route::post('/seller/inventory/{product}/increase', function (
    $productId
) use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $stockInput = request()->validate(['variant_id' => 'nullable|integer|min:1']);
    \App\Support\SellerProducts::adjustStock(\App\Support\SellerAccount::current(), $productId, 1, isset($stockInput['variant_id']) ? (int) $stockInput['variant_id'] : null);

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

    $stockInput = request()->validate(['variant_id' => 'nullable|integer|min:1']);
    \App\Support\SellerProducts::adjustStock(\App\Support\SellerAccount::current(), $productId, -1, isset($stockInput['variant_id']) ? (int) $stockInput['variant_id'] : null);

    return back()->with(
        'success',
        'Product stock updated successfully.'
    );

})->name('seller.inventory.decrease');


// =====================================================
// SELLER SALES
// =====================================================

Route::get('/seller/sales', function () use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    return view('seller.sales', \App\Support\SellerSales::data(\App\Support\SellerAccount::current(), request()));

})->name('seller.sales');


Route::middleware('auth')->prefix('seller/vouchers')->name('seller.vouchers.')->group(function () {
    $controller = \App\Http\Controllers\Seller\VoucherController::class;
    Route::get('/', [$controller, 'index'])->name('index');
    Route::get('/create', [$controller, 'create'])->name('create');
    Route::post('/', [$controller, 'store'])->name('store');
    Route::get('/{voucher}', [$controller, 'show'])->whereNumber('voucher')->name('show');
    Route::get('/{voucher}/edit', [$controller, 'edit'])->whereNumber('voucher')->name('edit');
    Route::put('/{voucher}', [$controller, 'update'])->whereNumber('voucher')->name('update');
    Route::post('/{voucher}/activate', [$controller, 'activate'])->whereNumber('voucher')->name('activate');
    Route::post('/{voucher}/pause', [$controller, 'pause'])->whereNumber('voucher')->name('pause');
    Route::post('/{voucher}/archive', [$controller, 'archive'])->whereNumber('voucher')->name('archive');
});


Route::middleware('auth')->prefix('seller/notifications')->name('seller.notifications.')->group(function () {
    $controller = \App\Http\Controllers\Seller\NotificationController::class;
    Route::get('/', [$controller, 'index'])->name('index');
    Route::post('/read-all', [$controller, 'readAll'])->name('read-all');
    Route::post('/{notification}/read', [$controller, 'read'])->whereUuid('notification')->name('read');
    Route::post('/{notification}/unread', [$controller, 'unread'])->whereUuid('notification')->name('unread');
});

Route::middleware('auth')->prefix('seller/messages')->name('seller.messages.')->group(function () {
    $controller = \App\Http\Controllers\Seller\MessageController::class;
    Route::get('/', [$controller, 'index'])->name('index');
    Route::get('/{conversation}', [$controller, 'show'])->whereNumber('conversation')->name('show');
    Route::post('/{conversation}', [$controller, 'reply'])->whereNumber('conversation')->middleware('throttle:30,1')->name('reply');
});

Route::middleware('auth')->prefix('messages')->name('buyer.messages.')->group(function () {
    $controller = \App\Http\Controllers\Buyer\MessageController::class;
    Route::get('/', [$controller, 'index'])->name('index');
    Route::get('/contact/{kind}/{id}', [$controller, 'contact'])->whereIn('kind', ['products', 'shops'])->whereNumber('id')->name('contact');
    Route::post('/contact/{kind}/{id}', [$controller, 'start'])->whereIn('kind', ['products', 'shops'])->whereNumber('id')->middleware('throttle:30,1')->name('start');
    Route::get('/conversation/{conversation}', [$controller, 'show'])->whereNumber('conversation')->name('show');
    Route::post('/conversation/{conversation}', [$controller, 'send'])->whereNumber('conversation')->middleware('throttle:30,1')->name('send');
});
Route::get('/chat-images/{attachment}', [\App\Http\Controllers\ChatImageController::class, 'show'])->whereNumber('attachment')->middleware('auth')->name('messages.images.show');

// =====================================================
// SELLER REPORTS & ANALYTICS
// =====================================================

Route::get('/seller/reports', function () use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    return view('seller.reports', \App\Support\SellerReports::data(\App\Support\SellerAccount::current(), request()));

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


    $orders = session('orders', []);

    if (!is_array($orders)) {
        $orders = [];
    }


    $riderOrders = collect($orders)
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
                    'completed',
                    'delivery_failed',
                    'returned',
                ]
            );

        })
        ->reverse()
        ->toArray();


    return view('rider.deliveries', [
        'orders' => $riderOrders,
    ]);

})->name('rider.deliveries');


// =====================================================
// RIDER ACCEPT PICKUP
// =====================================================
//
// ERP FLOW:
//
// READY_FOR_PICKUP
//      ↓
// Accept Pickup
//      ↓
// Go to Seller
//      ↓
// Pick Up Parcel
//      ↓
// Scan / Confirm Parcel
//      ↓
// PICKED_UP
//
// Accepting does NOT create another ERP status.
// The order stays READY_FOR_PICKUP until pickup is confirmed.
// =====================================================

Route::post(
    '/rider/orders/{order}/accept-pickup',
    function ($orderId) {

        if (!session('rider_logged_in')) {

            return redirect()
                ->route('rider.login')
                ->with(
                    'error',
                    'Please log in as a SUKI Rider first.'
                );
        }


        $orders = session('orders', []);

        if (!is_array($orders)) {
            $orders = [];
        }


        if (!isset($orders[$orderId])) {

            return back()->with(
                'error',
                'Order not found.'
            );
        }


        if (
            ($orders[$orderId]['status'] ?? '')
            !== 'ready_for_pickup'
        ) {

            return back()->with(
                'error',
                'This parcel is no longer available for pickup.'
            );
        }


        $riders = session(
            'rider_applications',
            []
        );


        $riderIndex = session(
            'logged_in_rider_index'
        );


        if (
            $riderIndex === null ||
            !isset($riders[$riderIndex])
        ) {

            return redirect()
                ->route('rider.login')
                ->with(
                    'error',
                    'Your rider session could not be verified.'
                );
        }


        $existingPickupRider =
            $orders[$orderId]['pickup_rider_index']
            ?? null;


        if (
            $existingPickupRider !== null &&
            (string) $existingPickupRider !==
            (string) $riderIndex
        ) {

            return back()->with(
                'error',
                'Another rider has already accepted this pickup.'
            );
        }


        $rider =
            $riders[$riderIndex];


        $riderName = trim(
            ($rider['first_name'] ?? '') . ' ' .
            ($rider['last_name'] ?? '')
        );


        /*
        |--------------------------------------------------------------------------
        | PICKUP RIDER
        |--------------------------------------------------------------------------
        |
        | pickup_rider_index:
        | Rider who collects from Seller → Sorting Center.
        |
        | rider_index:
        | Final delivery rider assigned later by Logistics.
        |
        */

        $orders[$orderId]['pickup_rider_index'] =
            $riderIndex;


        $orders[$orderId]['pickup_rider_name'] =
            $riderName;


        $orders[$orderId]['pickup_rider'] = [

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


        $orders[$orderId]['pickup_accepted_at'] =
            now()->format('Y-m-d H:i:s');


        $orders[$orderId]['updated_at'] =
            now()->format('Y-m-d H:i:s');


        session()->put(
            'orders',
            $orders
        );


        return back()->with(
            'success',
            'Pickup accepted. Proceed to the seller, collect the parcel, then scan or confirm the parcel pickup.'
        );

    }
)->name('rider.pickup.accept');


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


    $riders = session(
        'rider_applications',
        []
    );


    $riderIndex = session(
        'logged_in_rider_index'
    );


    if (
        $riderIndex === null ||
        !isset($riders[$riderIndex])
    ) {

        return redirect()
            ->route('rider.login')
            ->with(
                'error',
                'Your rider session could not be verified.'
            );
    }


    $orders = session(
        'orders',
        []
    );


    if (!is_array($orders)) {
        $orders = [];
    }


    /*
    |--------------------------------------------------------------------------
    | ONLY THIS RIDER'S DELIVERIES
    |--------------------------------------------------------------------------
    */

    $completedOrders =
        collect($orders)
            ->filter(
                function ($order)
                use ($riderIndex) {

                    $assignedRider =
                        $order['rider_index']
                        ?? null;


                    $belongsToRider =
                        $assignedRider !== null &&
                        (string) $assignedRider ===
                        (string) $riderIndex;


                    $isCompleted =
                        in_array(
                            $order['status'] ?? '',
                            [
                                'delivered',
                                'completed',
                            ]
                        );


                    return
                        $belongsToRider &&
                        $isCompleted;

                }
            )
            ->reverse();


    /*
    |--------------------------------------------------------------------------
    | PROTOTYPE DELIVERY FEE
    |--------------------------------------------------------------------------
    |
    | ₱50 muna per successful delivery.
    | Later puwede itong palitan ng actual delivery-area pricing.
    |
    */

    $deliveryFee =
        50;


    $totalDeliveries =
        $completedOrders->count();


    $totalEarnings =
        $totalDeliveries *
        $deliveryFee;


    return view(
        'rider.earnings',
        [

            'orders' =>
                $completedOrders,

            'totalDeliveries' =>
                $totalDeliveries,

            'totalEarnings' =>
                $totalEarnings,

            'deliveryFee' =>
                $deliveryFee,
        ]
    );

})->name('rider.earnings');

// =====================================================
// RIDER DELIVERY HISTORY
// =====================================================

Route::get('/rider/history', function () {

    if (!session('rider_logged_in')) {

        return redirect()
            ->route('rider.login')
            ->with(
                'error',
                'Please log in as a SUKI Rider first.'
            );
    }


    $riders = session(
        'rider_applications',
        []
    );


    $riderIndex = session(
        'logged_in_rider_index'
    );


    if (
        $riderIndex === null ||
        !isset($riders[$riderIndex])
    ) {

        return redirect()
            ->route('rider.login')
            ->with(
                'error',
                'Your rider session could not be verified.'
            );
    }


    $orders = session(
        'orders',
        []
    );


    if (!is_array($orders)) {
        $orders = [];
    }


    /*
    |--------------------------------------------------------------------------
    | THIS RIDER'S DELIVERY HISTORY ONLY
    |--------------------------------------------------------------------------
    |
    | rider_index is the FINAL delivery rider assigned by Logistics.
    |
    | Pickup Rider assignments are intentionally not included here because
    | this page is the Delivery History required by the Rider module.
    |
    */

    $historyOrders =
        collect($orders)
            ->filter(
                function ($order)
                use ($riderIndex) {

                    $assignedRider =
                        $order['rider_index']
                        ?? null;


                    if (
                        $assignedRider === null ||
                        (string) $assignedRider !==
                        (string) $riderIndex
                    ) {

                        return false;
                    }


                    return in_array(
                        strtolower(
                            $order['status']
                            ?? ''
                        ),
                        [
                            'delivered',
                            'completed',
                            'delivery_failed',
                            'returned',
                        ]
                    );

                }
            )
            ->reverse();


    return view(
        'rider.history',
        [
            'orders' =>
                $historyOrders,
        ]
    );

})->name('rider.history');

// =====================================================
// RIDER ORDER STATUS ACTIONS
// =====================================================
//
// PICKUP FLOW:
//
// READY_FOR_PICKUP
//      ↓
// Accept Pickup
//      ↓
// PICKED_UP
//      ↓
// AT_SORTING_CENTER
//
//
// DELIVERY FLOW:
//
// AT_SORTING_CENTER
//      ↓
// Logistics sorts parcel
//      ↓
// SORTED
//      ↓
// Logistics assigns delivery rider
//      ↓
// ASSIGNED_TO_RIDER
//      ↓
// OUT_FOR_DELIVERY
//      ↓
// DELIVERED
//
// OR:
//
// OUT_FOR_DELIVERY
//      ↓
// DELIVERY_FAILED
//
//
// IMPORTANT:
//
// Rider does NOT set COMPLETED.
//
// DELIVERED
//      ↓
// Buyer confirms receipt
//      ↓
// COMPLETED
//
// =====================================================

Route::post(
    '/rider/orders/{order}/status',
    function (
        Request $request,
        $orderId
    ) {

        if (!session('rider_logged_in')) {

            return redirect()
                ->route('rider.login')
                ->with(
                    'error',
                    'Please log in as a SUKI Rider first.'
                );
        }


        $orders =
            session(
                'orders',
                []
            );


        if (!is_array($orders)) {
            $orders = [];
        }


        if (!isset($orders[$orderId])) {

            return back()->with(
                'error',
                'Order not found.'
            );
        }


        $riders =
            session(
                'rider_applications',
                []
            );


        $riderIndex =
            session(
                'logged_in_rider_index'
            );


        if (
            $riderIndex === null ||
            !isset($riders[$riderIndex])
        ) {

            return redirect()
                ->route('rider.login')
                ->with(
                    'error',
                    'Your rider session could not be verified.'
                );
        }


        $currentStatus =
            strtolower(
                $orders[$orderId]['status']
                ?? 'placed'
            );


        $requestedStatus =
            strtolower(
                $request->input(
                    'status',
                    ''
                )
            );


        // =====================================================
        // PICKUP RIDER OWNERSHIP
        // =====================================================

        $pickupRiderIndex =
            $orders[$orderId]['pickup_rider_index']
            ?? null;


        $isPickupRider =
            $pickupRiderIndex !== null &&
            (string) $pickupRiderIndex ===
            (string) $riderIndex;


        // =====================================================
        // DELIVERY RIDER OWNERSHIP
        // =====================================================

        $deliveryRiderIndex =
            $orders[$orderId]['rider_index']
            ?? null;


        $isDeliveryRider =
            $deliveryRiderIndex !== null &&
            (string) $deliveryRiderIndex ===
            (string) $riderIndex;


        // =====================================================
        // READY FOR PICKUP → PICKED UP
        // =====================================================

        if (
            $currentStatus ===
            'ready_for_pickup'
        ) {

            if (!$isPickupRider) {

                return back()->with(
                    'error',
                    'You must accept this pickup before confirming the parcel collection.'
                );
            }


            if (
                $requestedStatus !==
                'picked_up'
            ) {

                return back()->with(
                    'error',
                    'Invalid pickup status transition.'
                );
            }


            $orders[$orderId]['status'] =
                'picked_up';


            $orders[$orderId]['picked_up_at'] =
                now()->format(
                    'Y-m-d H:i:s'
                );


            $orders[$orderId]['updated_at'] =
                now()->format(
                    'Y-m-d H:i:s'
                );


            session()->put(
                'orders',
                $orders
            );


            return back()->with(
                'success',
                'Parcel pickup confirmed. Deliver the parcel to the Sorting Center.'
            );
        }


        // =====================================================
        // PICKED UP → AT SORTING CENTER
        // =====================================================

        if (
            $currentStatus ===
            'picked_up'
        ) {

            if (!$isPickupRider) {

                return back()->with(
                    'error',
                    'This pickup belongs to another rider.'
                );
            }


            if (
                $requestedStatus !==
                'at_sorting_center'
            ) {

                return back()->with(
                    'error',
                    'Invalid pickup status transition.'
                );
            }


            $orders[$orderId]['status'] =
                'at_sorting_center';


            $orders[$orderId]['at_sorting_center_at'] =
                now()->format(
                    'Y-m-d H:i:s'
                );


            $orders[$orderId]['updated_at'] =
                now()->format(
                    'Y-m-d H:i:s'
                );


            session()->put(
                'orders',
                $orders
            );


            return back()->with(
                'success',
                'Parcel successfully handed over to the Sorting Center.'
            );
        }


        // =====================================================
        // ASSIGNED TO RIDER → OUT FOR DELIVERY
        // =====================================================

        if (
            $currentStatus ===
            'assigned_to_rider'
        ) {

            if (!$isDeliveryRider) {

                return back()->with(
                    'error',
                    'This delivery assignment belongs to another rider.'
                );
            }


            if (
                $requestedStatus !==
                'out_for_delivery'
            ) {

                return back()->with(
                    'error',
                    'Invalid delivery status transition.'
                );
            }


            $orders[$orderId]['status'] =
                'out_for_delivery';


            $orders[$orderId]['out_for_delivery_at'] =
                now()->format(
                    'Y-m-d H:i:s'
                );


            $orders[$orderId]['updated_at'] =
                now()->format(
                    'Y-m-d H:i:s'
                );


            session()->put(
                'orders',
                $orders
            );


            return back()->with(
                'success',
                'Parcel picked up from the Sorting Center and marked as Out for Delivery.'
            );
        }


        // =====================================================
        // OUT FOR DELIVERY
        // =====================================================

        if (
            $currentStatus ===
            'out_for_delivery'
        ) {

            if (!$isDeliveryRider) {

                return back()->with(
                    'error',
                    'This delivery assignment belongs to another rider.'
                );
            }


            if (
                !in_array(
                    $requestedStatus,
                    [
                        'delivered',
                        'delivery_failed',
                    ]
                )
            ) {

                return back()->with(
                    'error',
                    'Invalid delivery status transition.'
                );
            }


            // =================================================
            // SUCCESSFUL DELIVERY
            // =================================================

            if (
                $requestedStatus ===
                'delivered'
            ) {

                $orders[$orderId]['status'] =
                    'delivered';


                $orders[$orderId]['delivered_at'] =
                    now()->format(
                        'Y-m-d H:i:s'
                    );


                $orders[$orderId]['updated_at'] =
                    now()->format(
                        'Y-m-d H:i:s'
                    );


                session()->put(
                    'orders',
                    $orders
                );


                return back()->with(
                    'success',
                    'Parcel marked as Delivered. Waiting for the buyer to confirm receipt.'
                );
            }


            // =================================================
            // FAILED DELIVERY
            // =================================================

            $request->validate([

                'failure_reason' =>
                    'required|string|max:500',

            ]);


            $orders[$orderId]['status'] =
                'delivery_failed';


            $orders[$orderId]['delivery_failure_reason'] =
                trim(
                    $request->input(
                        'failure_reason'
                    )
                );


            $orders[$orderId]['delivery_failed_at'] =
                now()->format(
                    'Y-m-d H:i:s'
                );


            $orders[$orderId]['updated_at'] =
                now()->format(
                    'Y-m-d H:i:s'
                );


            session()->put(
                'orders',
                $orders
            );


            return back()->with(
                'success',
                'Delivery failure recorded. Logistics can now review the parcel for rescheduling or return.'
            );
        }


        // =====================================================
        // NO RIDER ACTION
        // =====================================================

        return back()->with(
            'error',
            'This order cannot be updated by the rider at its current status.'
        );

    }
)->name('rider.order.status');

// =====================================================
// SUKI LOGISTICS DASHBOARD
// =====================================================

$requireLogistics = function () {

    if (!Auth::check()) {
        return redirect()
            ->route('login')
            ->with('error', 'Please log in as a logistics operator.');
    }

    /** @var User $user */
    $user = Auth::user();

    if (
        !$user->isLogistics() ||
        $user->status !== 'active' ||
        $user->is_suspended
    ) {
        abort(403);
    }

    return null;
};

Route::get('/logistics/register', function () {

    return view('logistics.register');

})->name('logistics.register');

Route::post('/logistics/register', function (Request $request) use ($fetchPsgcData) {

    $submittedEmail = $request->input('email');
    $normalizedEmail = is_string($submittedEmail)
        ? strtolower(trim($submittedEmail))
        : null;

    if ($normalizedEmail !== null) {
        $request->merge(['email' => $normalizedEmail]);
    }

    $submittedMobile = $request->input('mobile_number');
    $normalizedMobile = null;

    if (is_string($submittedMobile)) {
        if (preg_match('/\A09[0-9]{9}\z/', $submittedMobile) === 1) {
            $normalizedMobile = $submittedMobile;
        } elseif (preg_match('/\A\+639[0-9]{9}\z/', $submittedMobile) === 1) {
            $normalizedMobile = '0' . substr($submittedMobile, 3);
        }
    }

    if ($normalizedMobile !== null) {
        $request->merge(['mobile_number' => $normalizedMobile]);
    }

    $validated = $request->validate([
        'company_name' => [
            'bail',
            'required',
            'string',
            'min:2',
            'max:255',
            function ($attribute, $value, $fail) {
                if (
                    LogisticsProvider::query()
                        ->whereRaw(
                            'LOWER(TRIM(name)) = ?',
                            [mb_strtolower(trim($value))]
                        )
                        ->exists()
                ) {
                    $fail('Company name is already registered.');
                }
            },
        ],
        'sec_registration_number' => [
            'bail',
            'required',
            'string',
            'regex:/\A[A-Za-z]{2}[0-9]{9}\z/',
        ],
        'tin' => [
            'bail',
            'required',
            'string',
            'regex:/\A(?:[0-9]{12}|[0-9]{3}(?:-[0-9]{3}){3})\z/',
        ],
        'region' => ['required', 'string', 'regex:/\A[0-9]{10}\z/'],
        'province' => ['nullable', 'string', 'regex:/\A[0-9]{10}\z/'],
        'city_municipality' => ['required', 'string', 'regex:/\A[0-9]{10}\z/'],
        'barangay' => ['required', 'string', 'regex:/\A[0-9]{10}\z/'],
        'street_address' => 'required|string|max:255',
        'postal_code' => [
            'required',
            'string',
            'regex:/\A[0-9]{4}\z/',
        ],
        'first_name' => [
            'required',
            'string',
            'min:2',
            'max:50',
            'regex:/\A\p{L}+(?:[\x20\x27\x2D]\p{L}+)*\z/u',
        ],
        'last_name' => [
            'required',
            'string',
            'min:2',
            'max:50',
            'regex:/\A\p{L}+(?:[\x20\x27\x2D]\p{L}+)*\z/u',
        ],
        'position' => 'required|string|max:100',
        'email' => [
            'bail',
            'required',
            'string',
            'email',
            'max:255',
            function ($attribute, $value, $fail) use ($normalizedEmail) {
                if (
                    $normalizedEmail !== null &&
                    User::query()
                        ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                        ->exists()
                ) {
                    $fail('Email is already registered.');
                }
            },
        ],
        'mobile_number' => [
            'bail',
            'required',
            'string',
            function ($attribute, $value, $fail) use ($normalizedMobile) {
                if ($normalizedMobile === null) {
                    $fail('Enter a valid Philippine mobile number in local or +63 format.');

                    return;
                }

                $internationalMobile = '+63' . substr($normalizedMobile, 1);
                $mobileAlreadyRegistered = User::query()
                    ->whereIn('phone', [$normalizedMobile, $internationalMobile])
                    ->exists();

                if ($mobileAlreadyRegistered) {
                    $fail('This mobile number is already registered.');
                }
            },
        ],
        'service_areas' => 'required|string|max:1000',
        'sec_certificate' => [
            'required',
            'file',
            'mimes:jpg,jpeg,png,pdf',
            'max:5120',
        ],
        'bir_form_2303' => [
            'required',
            'file',
            'mimes:jpg,jpeg,png,pdf',
            'max:5120',
        ],
        'business_permit' => [
            'required',
            'file',
            'mimes:jpg,jpeg,png,pdf',
            'max:5120',
        ],
        'authorized_representative_id' => [
            'required',
            'file',
            'mimes:jpg,jpeg,png,pdf',
            'max:5120',
        ],
        'password' => [
            'bail',
            'required',
            'string',
            'min:8',
            'max:255',
            'regex:/\A(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[[:punct:]]).*\z/',
        ],
        'password_confirmation' => 'required|string|same:password',
        'terms' => 'accepted',
    ], [
        'company_name.required' => 'Company name is required.',
        'sec_registration_number.required' => 'SEC registration number is required.',
        'sec_registration_number.regex' => 'SEC registration number must contain exactly 2 letters followed by 9 digits (for example, CS202012345).',
        'tin.required' => 'TIN is required.',
        'tin.regex' => 'TIN must contain 12 digits, optionally displayed as 000-000-000-000.',
        'region.required' => 'Region is required.',
        'province.required' => 'Province is required.',
        'city_municipality.required' => 'City or municipality is required.',
        'street_address.required' => 'Street address is required.',
        'postal_code.required' => 'Postal code is required.',
        'postal_code.regex' => 'Postal code must contain exactly four digits.',
        'first_name.required' => 'First name is required.',
        'first_name.regex' => 'First name must contain letters only, with spaces, hyphens, or apostrophes allowed between words.',
        'last_name.required' => 'Last name is required.',
        'last_name.regex' => 'Last name must contain letters only, with spaces, hyphens, or apostrophes allowed between words.',
        'position.required' => 'Position is required.',
        'email.required' => 'Email is required.',
        'email.email' => 'Enter a valid email address.',
        'mobile_number.required' => 'Mobile number is required.',
        'password.required' => 'Password is required.',
        'password.min' => 'Password must be at least 8 characters.',
        'password.regex' => 'Password must include an uppercase letter, a lowercase letter, a number, and a special character.',
        'password_confirmation.required' => 'Confirm password is required.',
        'password_confirmation.same' => 'Password confirmation must match the password.',
        'service_areas.required' => 'Service areas are required.',
        'sec_certificate.required' => 'SEC Certificate is required.',
        'sec_certificate.mimes' => 'SEC Certificate must be a JPG, PNG, or PDF file.',
        'sec_certificate.max' => 'SEC Certificate may not exceed 5 MB.',
        'bir_form_2303.required' => 'BIR Form 2303 is required.',
        'bir_form_2303.mimes' => 'BIR Form 2303 must be a JPG, PNG, or PDF file.',
        'bir_form_2303.max' => 'BIR Form 2303 may not exceed 5 MB.',
        'business_permit.required' => 'Mayor’s / Business Permit is required.',
        'business_permit.mimes' => 'Mayor’s / Business Permit must be a JPG, PNG, or PDF file.',
        'business_permit.max' => 'Mayor’s / Business Permit may not exceed 5 MB.',
        'authorized_representative_id.required' => 'Valid ID of the authorized representative is required.',
        'authorized_representative_id.mimes' => 'Authorized representative ID must be a JPG, PNG, or PDF file.',
        'authorized_representative_id.max' => 'Authorized representative ID may not exceed 5 MB.',
        'password.required' => 'Password is required.',
        'password.min' => 'Password must be at least 8 characters.',
        'password_confirmation.required' => 'Confirm password is required.',
        'password_confirmation.same' => 'Password confirmation must match the password.',
        'terms.accepted' => 'You must agree to the Terms and Privacy Policy.',
    ], [
        'city_municipality' => 'city or municipality',
        'sec_registration_number' => 'SEC registration number',
        'sec_certificate' => 'SEC Certificate',
        'bir_form_2303' => 'BIR Form 2303',
        'business_permit' => 'Mayor’s / Business Permit',
        'authorized_representative_id' => 'Valid ID of Authorized Representative',
    ]);

    $regions = $fetchPsgcData('regions');

    if ($regions === null) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'region' => 'Address data could not be verified. Please try again later.',
        ]);
    }

    $region = collect($regions)->firstWhere('code', $validated['region']);
    if ($region === null) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'region' => 'Select a valid region.',
        ]);
    }

    $regionPath = 'regions/' . rawurlencode($region['code']);
    $provinces = $fetchPsgcData($regionPath . '/provinces');

    if ($provinces === null) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'region' => 'Address data could not be verified. Please try again later.',
        ]);
    }

    $locationErrors = [];
    $province = null;

    if ($provinces !== [] && empty($validated['province'])) {
        $locationErrors['province'] = 'Select a province.';
    } elseif ($provinces !== []) {
        $province = collect($provinces)->firstWhere(
            'code',
            $validated['province']
        );

        if ($province === null) {
            $locationErrors['province'] = 'Select a province in the selected region.';
        }
    } elseif (! empty($validated['province'])) {
        $locationErrors['province'] = 'This region does not have provinces.';
    }

    if ($locationErrors !== []) {
        throw \Illuminate\Validation\ValidationException::withMessages($locationErrors);
    }

    $municipalityPath = $province !== null
        ? 'provinces/' . rawurlencode($province['code']) . '/cities-municipalities'
        : $regionPath . '/cities-municipalities';
    $municipalities = $fetchPsgcData($municipalityPath);

    if ($municipalities === null) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'city_municipality' => 'Address data could not be verified. Please try again later.',
        ]);
    }

    $municipality = collect($municipalities)->firstWhere(
        'code',
        $validated['city_municipality']
    );

    if ($municipality === null) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'city_municipality' => 'Select a city or municipality in the selected area.',
        ]);
    }

    $barangays = $fetchPsgcData(
        'cities-municipalities/'
        . rawurlencode($municipality['code'])
        . '/barangays'
    );

    if ($barangays === null) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'barangay' => 'Address data could not be verified. Please try again later.',
        ]);
    }

    if (collect($barangays)->firstWhere('code', $validated['barangay']) === null) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'barangay' => 'Select a barangay in the selected city or municipality.',
        ]);
    }

    return back()->with(
        'status',
        'All required fields passed validation. Registration processing is not enabled yet.'
    );

})->name('logistics.register.submit');

Route::get('/logistics', function () use ($requireLogistics) {

    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

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

Route::get('/logistics/sorting', function () use ($requireLogistics) {

    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

    return view('logistics.sorting');

})->name('logistics.sorting');


// =====================================================
// LOGISTICS DELIVERY ASSIGNMENTS
// =====================================================

Route::get('/logistics/assignments', function () use ($requireLogistics) {

    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

    return view('logistics.assignments');

})->name('logistics.assignments');


// =====================================================
// LOGISTICS DELIVERY MONITORING
// =====================================================

Route::get('/logistics/monitoring', function () use ($requireLogistics) {

    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

    return view('logistics.monitoring');

})->name('logistics.monitoring');


// =====================================================
// LOGISTICS REPORTS
// =====================================================

Route::get('/logistics/reports', function () use ($requireLogistics) {

    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

    return view('logistics.reports');

})->name('logistics.reports');

// =====================================================
// LOGISTICS PARCELS
// =====================================================

Route::get('/logistics/parcels', function () use ($requireLogistics) {

    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

    $orders = session('orders', []);

    return view('logistics.parcels', [
        'orders' => $orders,
    ]);

})->name('logistics.parcels');


// =====================================================
// LOGISTICS SHIPMENTS
// =====================================================

Route::get('/logistics/shipments', function () use ($requireLogistics) {

    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

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
) use ($requireLogistics) {
    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

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

Route::get('/logistics/riders', function () use ($requireLogistics) {

    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

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

Route::get('/logistics/riders/{rider}/review', function ($rider) use ($requireLogistics) {

    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

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

Route::post('/logistics/riders/{rider}/approve', function ($rider) use ($requireLogistics) {

    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

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
) use ($requireLogistics) {

    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

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
// ADMIN LOGIN PAGE
// =====================================================




Route::get('/superadmin', [DashboardController::class, 'index'])
    ->middleware(SuperAdminMiddleware::class)
    ->name('superadmin.dashboard');

Route::middleware(SuperAdminMiddleware::class)
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {

        Route::get(
            '/users',
            [UserManagementController::class, 'index']
        )->name('users');

        Route::get(
            '/users/search',
            [UserManagementController::class, 'search']
        )->name('users.search');

        Route::get(
            '/users/{user}',
            [UserManagementController::class, 'show']
        )->name('users.show');

        Route::post(
            '/users/{user}/status',
            [UserManagementController::class, 'updateStatus']
        )->name('users.status');

                // =====================================================
        // BUYERS
        // =====================================================

        Route::get(
            '/buyers',
            [BuyerManagementController::class, 'index']
        )->name('buyers');

        Route::get(
            '/buyers/search',
            [BuyerManagementController::class, 'search']
        )->name('buyers.search');

        Route::post(
            '/buyers/{buyer}/status',
            [BuyerManagementController::class, 'updateStatus']
        )->name('buyers.status');

        // =====================================================
// SELLERS
// =====================================================

Route::get(
    '/sellers',
    [SellerManagementController::class, 'index']
)->name('sellers');

Route::get(
    '/sellers/search',
    [SellerManagementController::class, 'search']
)->name('sellers.search');

Route::get(
    '/sellers/{seller}',
    [SellerManagementController::class, 'show']
)->name('sellers.show');

Route::post(
    '/sellers/{seller}/status',
    [SellerManagementController::class, 'updateStatus']
)->name('sellers.status');

// =====================================================
// APPLICATIONS
// =====================================================

Route::get(
    '/applications',
    [ApplicationController::class, 'index']
)->name('applications');

Route::get(
    '/applications/{application}',
    [ApplicationController::class, 'show']
)->name('applications.show');

Route::post(
    '/applications/{application}/approve',
    [ApplicationController::class, 'approve']
)->name('applications.approve');

Route::post(
    '/applications/{application}/reject',
    [ApplicationController::class, 'reject']
)->name('applications.reject');

// =====================================================
// ORDERS
// =====================================================

Route::get(
    '/orders',
    [OrderManagementController::class, 'index']
)->name('orders');

Route::get(
    '/orders/{order}',
    [OrderManagementController::class, 'show']
)->name('orders.show');

// =====================================================
// COMMISSION
// =====================================================

Route::get(
    '/commission',
    [CommissionController::class, 'index']
)->name('commission');

Route::post(
    '/commission/{seller}/rate',
    [CommissionController::class, 'updateRate']
)->name('commission.rate');

// =====================================================
// REPORTS
// =====================================================

Route::get(
    '/reports',
    [ReportController::class, 'index']
)->name('reports');

// =====================================================
// SETTINGS
// =====================================================

Route::get(
    '/settings',
    [SettingController::class, 'index']
)->name('settings');

Route::post(
    '/settings/platform',
    [SettingController::class, 'updatePlatform']
)->name('settings.platform');

Route::post(
    '/settings/marketplace',
    [SettingController::class, 'updateMarketplace']
)->name('settings.marketplace');

Route::post(
    '/settings/policies',
    [SettingController::class, 'updatePolicies']
)->name('settings.policies');

Route::post(
    '/settings/announcements',
    [SettingController::class, 'storeAnnouncement']
)->name('settings.announcement.store');

Route::post(
    '/settings/announcements/{announcement}',
    [SettingController::class, 'updateAnnouncement']
)->name('settings.announcement.update');

Route::post(
    '/settings/announcements/{announcement}/delete',
    [SettingController::class, 'deleteAnnouncement']
)->name('settings.announcement.delete');

    });

Route::get('/admin/login', function () {

    return redirect()
        ->route('login');

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

    $loggedIn = Auth::attempt([
        $loginField => $validated['login'],
        'password' => $validated['password'],
    ]);

    if (!$loggedIn) {
        return back()
            ->withErrors([
                'login' => 'Invalid administrator credentials.',
            ])
            ->onlyInput('login');
    }

    /** @var User $user */
    $user = Auth::user();

    $userRole = $user->hasRole('superadmin')
        ? 'superadmin'
        : ($user->hasRole('admin') ? 'admin' : null);

    if (!in_array($userRole, ['admin', 'superadmin'], true)) {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return back()
            ->withErrors([
                'login' => 'You do not have administrator access.',
            ])
            ->onlyInput('login');
    }

    $request
        ->session()
        ->regenerate();

    return redirect()
        ->route(
            $userRole === 'superadmin'
                ? 'superadmin.dashboard'
                : 'admin.dashboard'
        )
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


        if (!$buyer->hasRole('buyer')) {

            return back()
                ->with(
                    'error',
                    'Invalid buyer account.'
                );
        }

        if ($buyer->email_verified_at === null) {
            return back()
                ->with(
                    'error',
                    'Buyer email must be verified before the account can be approved.'
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


        if (!$buyer->hasRole('buyer')) {

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

        Auth::logout();

        $request
            ->session()
            ->invalidate();

        $request
            ->session()
            ->regenerateToken();

        return redirect()
            ->route('login')
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
    if (Auth::check()) {

        /** @var User $user */
        $user = Auth::user();

        if ($user->isSeller()) {
            SellerAccount::sync(SellerAccount::current());
            return redirect()->route('seller.dashboard');
        }

        if ($user->hasRole('superadmin')) {
            return redirect()
                ->route('superadmin.dashboard');
        }

        if ($user->hasRole('admin')) {
            return redirect()
                ->route('admin.dashboard');
        }

        if ($user->hasRole('logistics')) {
            return redirect()
                ->route('logistics.dashboard');
        }

        if ($user->hasRole('buyer')) {
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
    $loggedIn = Auth::attempt([
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

    /** @var User $user */
    $user = Auth::user();


    // =====================================================
    // SUSPENDED ACCOUNT CHECK
    // =====================================================

    if ($user->is_suspended ?? false) {

        Auth::logout();

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

    if (
        $user->hasRole('buyer') &&
        $user->email_verified_at === null &&
        !\App\Support\BuyerAccess::localException($user) &&
        BuyerEmailVerification::query()
            ->where('user_id', $user->id)
            ->exists()
    ) {
        $userId = $user->id;
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('buyer_verification_user_id', $userId);

        return redirect()
            ->route('buyer.verification.show')
            ->with(
                'error',
                'Please verify your email address before logging in.'
            );
    }


    // =====================================================
    // ACCOUNT STATUS CHECK
    // =====================================================

    if ($user->status !== 'active' && !\App\Support\BuyerAccess::localException($user)) {

        $status = strtolower(
            $user->status ?? ''
        );

        Auth::logout();

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
    // ADMIN / SUPERADMIN LOGIN
    // =====================================================

    $userRole = $user->hasRole('superadmin')
        ? 'superadmin'
        : ($user->hasRole('admin') ? 'admin' : null);

    if ($userRole === 'superadmin') {

        return redirect()
            ->route('superadmin.dashboard')
            ->with(
                'success',
                'Welcome to the SUKI SHOP Super Admin Portal.'
            );
    }

    if ($userRole === 'admin') {

        return redirect()
            ->route('admin.dashboard')
            ->with(
                'success',
                'Welcome to the SUKI SHOP Admin Portal.'
            );
    }

    if ($user->hasRole('logistics')) {

        return redirect()
            ->route('logistics.dashboard')
            ->with(
                'success',
                'Welcome to the SUKI Logistics Portal.'
            );
    }

    // =====================================================
    // BUYER LOGIN
    // =====================================================

    if ($user->hasRole('buyer')) {

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


    if ($user->isSeller()) {
        try {
            $seller = SellerAccount::current();
            SellerAccount::sync($seller);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['login' => $exception->getMessage()])->onlyInput('login');
        }

        return redirect()->intended(route('seller.dashboard'))->with('success', 'Welcome back to SUKI SHOP Seller Centre.');
    }

    // =====================================================
    // OTHER / UNSUPPORTED ROLES
    // =====================================================

    Auth::logout();

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
// REGISTER ACCOUNT TYPE PAGE
// =====================================================

Route::get('/register', function () {

    return view('auth.register');

})->name('register');


// =====================================================
// BUYER REGISTRATION PAGE
// =====================================================

Route::get('/register/buyer', function () {

    return view('auth.register-buyer');

})->name('register.buyer');

Route::get(
    '/buyer/email/verify',
    [BuyerEmailVerificationController::class, 'show']
)->name('buyer.verification.show');

Route::post(
    '/buyer/email/verify',
    [BuyerEmailVerificationController::class, 'verify']
)->middleware('throttle:6,1')
    ->name('buyer.verification.verify');

Route::post(
    '/buyer/email/verify/resend',
    [BuyerEmailVerificationController::class, 'resend']
)->middleware('throttle:6,1')
    ->name('buyer.verification.resend');

// =====================================================
// REGISTER SUBMIT
// =====================================================

Route::post('/register', function (
    Request $request,
    BuyerEmailVerificationService $verificationService
) use ($fetchPsgcData) {

    $submittedEmail = $request->input('email');
    $normalizedEmail = is_string($submittedEmail)
        ? strtolower(trim($submittedEmail))
        : null;

    if ($normalizedEmail !== null) {
        $request->merge(['email' => $normalizedEmail]);
    }

    $submittedPhone = $request->input('phone');
    $normalizedPhone = null;

    if (is_string($submittedPhone)) {
        if (preg_match('/\A09[0-9]{9}\z/', $submittedPhone) === 1) {
            $normalizedPhone = $submittedPhone;
        } elseif (preg_match('/\A\+639[0-9]{9}\z/', $submittedPhone) === 1) {
            $normalizedPhone = '0' . substr($submittedPhone, 3);
        }
    }

    if ($normalizedPhone !== null) {
        $request->merge(['phone' => $normalizedPhone]);
    }

    $latestAllowedBirthday = \Illuminate\Support\Carbon::today('Asia/Manila')
        ->subYears(18)
        ->toDateString();

    $validated = $request->validate([
        'last_name' => [
            'required',
            'string',
            'min:2',
            'max:50',
            'regex:/\A\p{L}+(?: \p{L}+)*\z/u',
        ],
        'first_name' => [
            'required',
            'string',
            'min:2',
            'max:50',
            'regex:/\A\p{L}+(?: \p{L}+)*\z/u',
        ],
        'middle_initial' => 'nullable|string|max:10',
        'sex' => 'required|string|max:20',

        'email' => [
            'bail',
            'required',
            'string',
            'email',
            'regex:/\A[^@\s]+@(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,}\z/',
            'max:255',
            function ($attribute, $value, $fail) use ($normalizedEmail) {
                if (
                    $normalizedEmail !== null &&
                    User::query()
                        ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                        ->exists()
                ) {
                    $fail('This email address has already been taken.');
                }
            },
        ],
        'phone' => [
            'bail',
            'required',
            'string',
            function ($attribute, $value, $fail) use ($normalizedPhone) {
                if ($normalizedPhone === null) {
                    $fail('Enter a valid Philippine mobile number in local or +63 format.');

                    return;
                }

                $internationalPhone = '+63' . substr($normalizedPhone, 1);

                if (
                    User::query()
                        ->whereIn('phone', [$normalizedPhone, $internationalPhone])
                        ->exists()
                ) {
                    $fail('This phone number has already been taken.');
                }
            },
        ],

        'birthday' => [
            'bail',
            'required',
            'date_format:Y-m-d',
            'before_or_equal:' . $latestAllowedBirthday,
        ],

        'region' => ['bail', 'required', 'string', 'regex:/\A[0-9]{10}\z/'],
        'province' => [
            'bail',
            'required_without:region',
            'nullable',
            'string',
            'regex:/\A[0-9]{10}\z/',
        ],
        'municipality' => ['bail', 'required', 'string', 'regex:/\A[0-9]{10}\z/'],
        'barangay' => ['bail', 'required', 'string', 'regex:/\A[0-9]{10}\z/'],
        'street_address' => 'required|string|max:255',

        'government_id' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',

        'password' => [
            'bail',
            'required',
            'string',
            'min:8',
            'max:255',
            'regex:/\A(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[\p{P}\p{S}]).*\z/u',
        ],
        'password_confirmation' => [
            'required',
            'string',
            'same:password',
        ],
        'terms' => 'accepted',
    ], [
        'first_name.required' => 'First name is required.',
        'first_name.min' => 'First name must be at least 2 characters.',
        'first_name.max' => 'First name may not exceed 50 characters.',
        'first_name.regex' => 'First name may contain letters only, with spaces allowed between names.',
        'last_name.required' => 'Last name is required.',
        'last_name.min' => 'Last name must be at least 2 characters.',
        'last_name.max' => 'Last name may not exceed 50 characters.',
        'last_name.regex' => 'Last name may contain letters only, with spaces allowed between names.',
        'email.required' => 'Email is required.',
        'email.email' => 'Enter a valid email address.',
        'email.regex' => 'Enter a valid email address with a domain extension.',
        'email.max' => 'Email may not exceed 255 characters.',
        'phone.required' => 'Contact number is required.',
        'password.required' => 'Password is required.',
        'password.min' => 'Password must be at least 8 characters.',
        'password.max' => 'Password may not exceed 255 characters.',
        'password.regex' => 'Password must include an uppercase letter, a lowercase letter, a number, and a special character.',
        'password_confirmation.required' => 'Confirm password is required.',
        'password_confirmation.same' => 'Password confirmation must match the password.',
        'terms.accepted' => "You must agree to SUKI SHOP's Terms & Conditions and Privacy Policy before continuing.",
        'region.required' => 'Region is required.',
        'region.regex' => 'Select a valid region.',
        'province.required_without' => 'Province is required.',
        'province.regex' => 'Select a valid province.',
        'municipality.required' => 'Municipality or city is required.',
        'municipality.regex' => 'Select a valid municipality or city.',
        'barangay.required' => 'Barangay is required.',
        'barangay.regex' => 'Select a valid barangay.',
    ]);

    $regions = $fetchPsgcData('regions');

    if ($regions === null) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'region' => 'Address data could not be verified. Please try again later.',
        ]);
    }

    $region = collect($regions)->firstWhere('code', $validated['region']);

    if (! is_array($region) || ! is_string($region['name'] ?? null)) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'region' => 'Select a valid region.',
        ]);
    }

    $regionPath = 'regions/' . rawurlencode($region['code']);
    $provinces = $fetchPsgcData($regionPath . '/provinces');

    if ($provinces === null) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'province' => 'Address data could not be verified. Please try again later.',
        ]);
    }

    $province = null;
    $locationErrors = [];

    if ($provinces !== [] && empty($validated['province'])) {
        $locationErrors['province'] = 'Province is required.';
    } elseif ($provinces !== []) {
        $province = collect($provinces)->firstWhere(
            'code',
            $validated['province']
        );

        if (! is_array($province) || ! is_string($province['name'] ?? null)) {
            $locationErrors['province'] = 'Select a province in the selected region.';
        }
    } elseif (! empty($validated['province'])) {
        $locationErrors['province'] = 'This region does not have provinces.';
    }

    if ($locationErrors !== []) {
        throw \Illuminate\Validation\ValidationException::withMessages($locationErrors);
    }

    $municipalityPath = $province !== null
        ? 'provinces/' . rawurlencode($province['code']) . '/cities-municipalities'
        : $regionPath . '/cities-municipalities';
    $municipalities = $fetchPsgcData($municipalityPath);

    if ($municipalities === null) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'municipality' => 'Address data could not be verified. Please try again later.',
        ]);
    }

    $municipality = collect($municipalities)->firstWhere(
        'code',
        $validated['municipality']
    );

    if (! is_array($municipality) || ! is_string($municipality['name'] ?? null)) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'municipality' => 'Select a municipality or city in the selected area.',
        ]);
    }

    $barangays = $fetchPsgcData(
        'cities-municipalities/'
        . rawurlencode($municipality['code'])
        . '/barangays'
    );

    if ($barangays === null) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'barangay' => 'Address data could not be verified. Please try again later.',
        ]);
    }

    $barangay = collect($barangays)->firstWhere(
        'code',
        $validated['barangay']
    );

    if (! is_array($barangay) || ! is_string($barangay['name'] ?? null)) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'barangay' => 'Select a barangay in the selected municipality or city.',
        ]);
    }

    $addressLocation = [
        'region' => $region['name'],
        'province' => $province['name'] ?? null,
        'municipality' => $municipality['name'],
        'barangay' => $barangay['name'],
    ];

    $governmentIdPath = $request
        ->file('government_id')
        ->store('government-ids', 'public');

    $fullName = implode(' ', array_filter([
        $validated['first_name'],
        $validated['middle_initial'] ?? null,
        $validated['last_name'],
    ], fn ($part) => is_string($part) && trim($part) !== ''));

    $user = \Illuminate\Support\Facades\DB::transaction(
        function () use ($validated, $addressLocation, $governmentIdPath, $fullName) {

            $user = User::create([
                'role' => 'buyer',
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'middle_initial' => $validated['middle_initial'] ?? null,
                'sex' => $validated['sex'],
                'birthday' => $validated['birthday'],
                'name' => $fullName,
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'status' => 'pending',
                'email_verified_at' => null,
                'government_id' => $governmentIdPath,
                'password' => $validated['password'],
            ]);

            /*
             * Keep the new roles / role_user architecture populated
             * while legacy users.role is still supported.
             */
            $buyerRole = \App\Models\Role::where('name', 'buyer')->first();

            if ($buyerRole) {
                $user->roles()->syncWithoutDetaching([
                    $buyerRole->id,
                ]);
            }

            /*
             * Save the buyer's default address.
             */
            $user->addresses()->create([
                'label' => 'Home',
                'recipient' => $fullName,
                'phone' => $validated['phone'],
                'line1' => $validated['street_address'],
                'barangay' => $addressLocation['barangay'],
                'city' => $addressLocation['municipality'],
                'province' => $addressLocation['province'],
                'region' => $addressLocation['region'],
                'postal_code' => null,
                'is_default' => true,
            ]);

            return $user;
        }
    );

    if (\App\Support\BuyerAccess::localException($user)) {
        return redirect()->route('login')->with('success', 'Buyer account created. Local development access is available without OTP or Admin approval; your saved verification and approval status are unchanged.');
    }

    $request->session()->put('buyer_verification_user_id', $user->id);

    try {
        $verificationService->issue($user);
    } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $exception) {
        report($exception);

        return redirect()
            ->route('buyer.verification.show')
            ->withErrors([
                'email' => 'Your account was created, but we could not send a verification code. Please request a new code.',
            ]);
    }

    return redirect()
        ->route('buyer.verification.show')
        ->with(
            'success',
            'Your buyer account was created. Verify your email to continue; administrator approval is still required before login.'
        );

})->name('register.submit');


// =====================================================
// LOGOUT
// =====================================================

Route::post('/logout', function (Request $request) {

    Auth::logout();

    $request->session()->forget([
        'buyer_logged_in',
        'buyer_profile',
    ]);

    $request->session()->invalidate();

    $request->session()->regenerateToken();

    return redirect()
        ->route('login')
        ->with(
            'success',
            'You have been logged out successfully.'
        );

})->name('logout');
