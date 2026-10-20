<?php

use App\Models\User;
use App\Models\CartItem;
use App\Models\WishlistItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Application;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\Role;
use App\Models\Seller;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Buyer\CartController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\Buyer\CheckoutController;
use App\Http\Controllers\Buyer\OrderCancellationController;
use App\Http\Controllers\DeliveryProofController;
use App\Http\Controllers\Buyer\ShipmentTrackingController as BuyerShipmentTrackingController;
use App\Http\Controllers\ComplaintEvidenceController;
use App\Http\Controllers\ParticipantComplaintController;
use App\Http\Controllers\Seller\FulfillmentController as SellerFulfillmentController;
use App\Http\Controllers\Seller\InventoryController as SellerInventoryController;
use App\Http\Controllers\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Seller\RegistrationController as SellerRegistrationController;
use App\Http\Controllers\Rider\FulfillmentController as RiderFulfillmentController;
use App\Http\Controllers\Rider\ShipmentTrackingController as RiderShipmentTrackingController;
use App\Http\Controllers\Logistics\FulfillmentController as LogisticsFulfillmentController;
use App\Http\Controllers\ShipmentWaybillController;
use App\Http\Controllers\Messaging\ConversationController as MessagingConversationController;
use App\Http\Controllers\Messaging\ContextConversationController;
use App\Http\Controllers\Messaging\ConversationReadController;
use App\Http\Controllers\Messaging\MessageController as MessagingMessageController;
use App\Http\Controllers\NotificationInboxController;
use App\Http\Controllers\AccountProfileController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\TransactionReviewController;
use App\Http\Controllers\ReviewPageController;
use App\Http\Controllers\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\Admin\ApplicationDocumentController;
use App\Http\Controllers\Admin\BuyerManagementController;
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderManagementController;
use App\Http\Controllers\Admin\ProductModerationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\ReportController as PhaseFiveReportController;
use App\Http\Controllers\Admin\SellerComplianceController;
use App\Http\Controllers\Admin\SellerComplianceEvidenceController;
use App\Http\Controllers\Admin\SellerManagementController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Auth\AccountVerificationController;
use App\Http\Controllers\Auth\BuyerRegistrationController;
use App\Http\Controllers\Auth\LogisticsRegistrationController;
use App\Http\Controllers\Auth\RiderActivationController;
use App\Services\PhilippineAddressService;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationInboxController::class, 'index'])->name('index');
    Route::get('/inbox', [NotificationInboxController::class, 'page'])->name('page');
    Route::get('/unread-count', [NotificationInboxController::class, 'unreadCount'])->name('unread-count');
    Route::post('/read-all', [NotificationInboxController::class, 'markAllRead'])->name('read-all');
    Route::post('/{notification}/read', [NotificationInboxController::class, 'markRead'])->name('read');
});

Route::get('/policies/{policy}', [PublicContentController::class, 'policy'])
    ->whereIn('policy', ['terms-of-service', 'privacy-policy', 'shipping-policy', 'returns-refund-policy'])
    ->name('policies.show');

Route::get('/buyer/help', [PublicContentController::class, 'buyerHelp'])
    ->middleware(['auth', 'approved', 'not_suspended', 'role:buyer'])
    ->name('buyer.help');

foreach (['admin', 'logistics', 'rider'] as $profileArea) {
    Route::get('/'.$profileArea.'/profile', [AccountProfileController::class, 'show'])
        ->defaults('area', $profileArea)->middleware(['auth', 'not_suspended', 'approved', 'role:'.$profileArea])->name($profileArea.'.profile');
    Route::patch('/'.$profileArea.'/profile', [AccountProfileController::class, 'update'])
        ->defaults('area', $profileArea)->middleware(['auth', 'not_suspended', 'approved', 'role:'.$profileArea])->name($profileArea.'.profile.update');
}

Route::middleware(['auth', 'approved', 'not_suspended', 'role:buyer'])->group(function () {
    Route::get('/buyer/reviews', [ReviewPageController::class, 'buyer'])->name('reviews.buyer-page');
    Route::post('/reviews/items/{item}', [TransactionReviewController::class, 'product'])->name('reviews.product');
    Route::post('/reviews/seller-orders/{sellerOrder}/seller', [TransactionReviewController::class, 'seller'])->name('reviews.seller');
    Route::post('/reviews/seller-orders/{sellerOrder}/rider', [TransactionReviewController::class, 'rider'])->name('reviews.rider');
    Route::patch('/reviews/{review}', [TransactionReviewController::class, 'edit'])->name('reviews.edit');
});
Route::get('/seller/reviews', [ReviewPageController::class, 'seller'])->middleware(['auth', 'approved', 'not_suspended', 'role:seller'])->name('reviews.seller-page');
Route::get('/admin/review-moderation', [ReviewPageController::class, 'admin'])->middleware(['auth', 'approved', 'not_suspended', 'role:admin'])->name('reviews.admin-page');
Route::get('/reviews/products/{product}', [TransactionReviewController::class, 'productSummary'])->name('reviews.product-summary');
Route::post('/seller/reviews/{review}/response', [TransactionReviewController::class, 'respond'])
    ->middleware(['auth', 'approved', 'not_suspended', 'role:seller'])->name('reviews.respond');
Route::post('/admin/reviews/{review}/moderate', [TransactionReviewController::class, 'moderate'])
    ->middleware(['auth', 'approved', 'not_suspended', 'role:admin'])->name('reviews.moderate');
Route::get('/admin/reviews', [TransactionReviewController::class, 'moderationIndex'])
    ->middleware(['auth', 'approved', 'not_suspended', 'role:admin'])->name('reviews.moderation-index');

// Narrow PSGC lookup endpoints for registration forms. Codes are validated by
// the service; callers never supply an upstream URL.
Route::prefix('address')->middleware('throttle:60,1')->group(function () {
    Route::get('regions', fn (PhilippineAddressService $address) => ($items = $address->locations('regions')) !== null ? response()->json($items) : response()->json(['message' => 'Address data is temporarily unavailable.'], 502));
    Route::get('regions/{region}/provinces', fn (string $region, PhilippineAddressService $address) => preg_match('/^\d+$/', $region) && ($items = $address->locations('regions/'.$region.'/provinces')) !== null ? response()->json($items) : response()->json(['message' => 'Address data is temporarily unavailable.'], 502));
    Route::get('regions/{region}/cities-municipalities', fn (string $region, PhilippineAddressService $address) => preg_match('/^\d+$/', $region) && ($items = $address->locations('regions/'.$region.'/cities-municipalities')) !== null ? response()->json($items) : response()->json(['message' => 'Address data is temporarily unavailable.'], 502));
    Route::get('provinces/{province}/cities-municipalities', fn (string $province, PhilippineAddressService $address) => preg_match('/^\d+$/', $province) && ($items = $address->locations('provinces/'.$province.'/cities-municipalities')) !== null ? response()->json($items) : response()->json(['message' => 'Address data is temporarily unavailable.'], 502));
    Route::get('cities-municipalities/{city}/barangays', fn (string $city, PhilippineAddressService $address) => preg_match('/^\d+$/', $city) && ($items = $address->locations('cities-municipalities/'.$city.'/barangays')) !== null ? response()->json($items) : response()->json(['message' => 'Address data is temporarily unavailable.'], 502));
});


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

$legacyProducts = $products;

$resolveProductCatalog = static function (): array {
    // Buyer mutations must never fall back to the presentation-only legacy
    // catalog. ProductCatalogService is the single marketplace visibility
    // authority for cart, wishlist, and buy-again actions.
    return app(\App\Services\ProductCatalogService::class)
        ->marketplaceProducts();
};

// =====================================================
// PUBLIC SUKI SHOP LANDING PAGE
// =====================================================

Route::get('/', function () {
    return view('landing');
})->name('landing');


// =====================================================
// BUYER HOME
// =====================================================

Route::get('/home', [ProductController::class, 'home'])
    ->middleware(['auth', 'approved', 'not_suspended', 'role:buyer'])
    ->name('buyer.home');


// =====================================================
// BUYER SHOP
// =====================================================

Route::get('/shop', [ProductController::class, 'shop'])
    ->name('buyer.shop');


// =====================================================
// PRODUCT DETAILS
// =====================================================

Route::get('/product/{slug}', [ProductController::class, 'show'])
    ->name('buyer.product');

Route::get('/stores/following', [StoreController::class, 'following'])
    ->middleware(['auth', 'approved', 'not_suspended', 'role:buyer'])
    ->name('buyer.stores.following');
Route::get('/stores/{seller:slug}', [StoreController::class, 'show'])->name('buyer.stores.show');
Route::post('/stores/{seller:slug}/follow', [StoreController::class, 'follow'])
    ->middleware(['auth', 'approved', 'not_suspended', 'role:buyer'])->name('buyer.stores.follow');
Route::delete('/stores/{seller:slug}/follow', [StoreController::class, 'unfollow'])
    ->middleware(['auth', 'approved', 'not_suspended', 'role:buyer'])->name('buyer.stores.unfollow');


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
        $user->status !== 'active' ||
        $user->is_suspended
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
// PSGC API PROXY
// =====================================================

Route::get('/api/psgc/provinces', function () {

    $response = Http::acceptJson()
        ->timeout(10)
        ->get('https://psgc.cloud/api/v2/provinces');

    if (!$response->successful()) {
        return response()->json([
            'message' => 'Unable to load provinces.',
        ], $response->status());
    }

    return response()->json($response->json('data'));

});

Route::get('/api/psgc/provinces/{province}/cities-municipalities', function (string $province) {

    $response = Http::acceptJson()
        ->timeout(10)
        ->get(
            'https://psgc.cloud/api/v2/provinces/'
            . rawurlencode($province)
            . '/cities-municipalities'
        );

    if (!$response->successful()) {
        return response()->json([
            'message' => 'Unable to load municipalities/cities.',
        ], $response->status());
    }

    return response()->json($response->json('data'));
});

Route::get('/api/psgc/cities-municipalities/{municipality}/barangays', function (string $municipality) {

    $response = Http::acceptJson()
        ->timeout(10)
        ->get(
            'https://psgc.cloud/api/v2/cities-municipalities/'
            . rawurlencode($municipality)
            . '/barangays'
        );

    if (!$response->successful()) {
        return response()->json([
            'message' => 'Unable to load barangays.',
        ], $response->status());
    }

    return response()->json($response->json('data'));
});


// =====================================================
// SHOPPING CART
// =====================================================

Route::get('/cart', [CartController::class, 'index'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:buyer'])
    ->name('buyer.cart');
Route::post('/cart/vouchers', [CartController::class, 'applyVoucher'])->middleware(['auth','approved','not_suspended','role:buyer'])->name('buyer.cart.vouchers.apply');
Route::delete('/cart/vouchers/{code}', [CartController::class, 'removeVoucher'])->middleware(['auth','approved','not_suspended','role:buyer'])->name('buyer.cart.vouchers.remove');


// =====================================================
// ADD TO CART
// =====================================================

Route::post('/cart/add/{slug}', function (Request $request, $slug) use ($resolveProductCatalog, $requireBuyer) {

    $products = $resolveProductCatalog();

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
            'product_variant_id' => $product['variant_id'] ?? $cartItem->product_variant_id,
        ]);

    } else {

        CartItem::create([
            'user_id' => $user->id,
            'product_slug' => $slug,
            'product_name' => $product['name'],
            'price' => $product['price'],
            'image' => $product['image'] ?? null,
            'quantity' => $requestedQuantity,
            'product_variant_id' => $product['variant_id'] ?? null,
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

Route::post('/cart/increase/{slug}', function ($slug) use ($resolveProductCatalog, $requireBuyer) {

    $products = $resolveProductCatalog();

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

Route::post('/cart/update/{slug}', function ($slug) use ($resolveProductCatalog, $requireBuyer) {

    $products = $resolveProductCatalog();

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

Route::post('/wishlist/add/{slug}', function ($slug) use ($resolveProductCatalog, $requireBuyer) {

    $products = $resolveProductCatalog();

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

Route::post('/wishlist/toggle/{slug}', function ($slug) use ($resolveProductCatalog, $requireBuyer) {

    $products = $resolveProductCatalog();

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

Route::post('/wishlist/move-to-cart/{slug}', function ($slug) use ($resolveProductCatalog, $requireBuyer) {

    $products = $resolveProductCatalog();

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

        if (! $cartItem->product_variant_id && isset($product['variant_id'])) {
            $cartItem->update(['product_variant_id' => $product['variant_id']]);
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
            'product_variant_id' => $product['variant_id'] ?? null,
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

        'seller_orders' => $order->sellerOrders
            ->map(fn ($sellerOrder) => [
                'seller_order_id' => $sellerOrder->id,
                'shipment_id' => $sellerOrder->shipment?->id,
                'has_proof' => (bool) $sellerOrder->shipment?->proof_path,
                'seller_name' => $sellerOrder->seller?->name ?? 'Seller',
                'status' => $sellerOrder->status === 'cancelled'
                    ? 'cancelled'
                    : strtolower($sellerOrder->workflow_status->value),
            ])
            ->values()
            ->all(),

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

Route::get('/checkout-legacy', function () use ($requireBuyer) {

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

})->name('buyer.checkout.legacy');


// =====================================================
// PLACE ORDER
// =====================================================

Route::post(
    '/checkout/place-order-legacy',
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
                'required|in:cod',
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
    'buyer.checkout.place-order.legacy'
);

Route::post('/checkout/place-order', [CheckoutController::class, 'store'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:buyer'])
    ->name('buyer.checkout.place-order');

Route::get('/checkout', [CheckoutController::class, 'index'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:buyer'])
    ->name('buyer.checkout');

Route::get('/shipments/{shipment}/delivery-proof', [DeliveryProofController::class, 'show'])
    ->middleware(['auth', 'not_suspended', 'approved'])
    ->name('shipments.delivery-proof');

Route::get('/my-orders/{order}/seller-orders/{sellerOrder}/track', [BuyerShipmentTrackingController::class, 'show'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:buyer'])
    ->name('buyer.seller-order.track');
Route::get('/my-orders/{order}/seller-orders/{sellerOrder}/tracking-data', [BuyerShipmentTrackingController::class, 'latest'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:buyer', 'throttle:60,1'])
    ->name('buyer.seller-order.tracking-data');
Route::get('/shipments/{shipment}/waybill', [ShipmentWaybillController::class, 'show'])
    ->middleware(['auth', 'not_suspended', 'approved'])
    ->name('shipments.waybill');
Route::post('/rider/shipments/{shipment}/location', [RiderShipmentTrackingController::class, 'store'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:rider', 'throttle:30,1'])
    ->name('rider.shipments.location.store');


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
            Order::with(['items', 'sellerOrders.seller'])
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
            Order::with(['items', 'sellerOrders.seller'])
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
            Order::with(['items', 'sellerOrders.seller'])
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
    '/my-orders/{order}/cancel-legacy',
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
    'buyer.order.cancel.legacy'
);

Route::post('/my-orders/{order}/cancel', [OrderCancellationController::class, 'store'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:buyer'])
    ->name('buyer.order.cancel');


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

        $user = auth()->user();
        $allowedTransition = false;

        if ($user->hasRole('buyer')) {
            abort_unless((int) $order->buyer_id === (int) $user->id, 403);
            $allowedTransition = $currentStatus === 'delivered' && $newStatus === 'completed';
        } elseif ($user->hasRole('seller')) {
            abort_unless((int) $order->seller_id === (int) $user->id, 403);
            $sellerTransitions = [
                'placed' => 'confirmed',
                'confirmed' => 'preparing',
                'preparing' => 'ready_for_pickup',
            ];
            $allowedTransition = ($sellerTransitions[$currentStatus] ?? null) === $newStatus;
        } else {
            abort(403, 'This role cannot update orders through this endpoint.');
        }

        abort_unless($allowedTransition, 422, 'Invalid order status transition for this account.');


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

        // Database-backed orders must complete through the centralized ERP
        // workflow so every seller order, shipment, and the parent order stay
        // synchronized. Keep the direct parent update below for legacy orders
        // that do not have seller-order fulfillment records.
        if (
            $user->hasRole('buyer') &&
            $newStatus === 'completed' &&
            $order->sellerOrders()->exists()
        ) {
            DB::transaction(function () use ($order, $user) {
                Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $sellerOrders = $order->sellerOrders()
                    ->lockForUpdate()
                    ->get();

                foreach ($sellerOrders as $sellerOrder) {
                    if ($sellerOrder->workflow_status === \App\Enums\OrderStatus::Completed) {
                        continue;
                    }
                    app(\App\Services\OrderWorkflowService::class)->transition(
                        $sellerOrder,
                        $user,
                        \App\Enums\OrderStatus::Completed
                    );
                }
            });

            return back()->with(
                'success',
                'Order status updated successfully.'
            );
        }


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
)->middleware(['auth', 'not_suspended', 'approved'])
->name(
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
        $resolveProductCatalog
    ) {

        $products = $resolveProductCatalog();

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

                    'product_variant_id' =>
                        $products[$item->product_slug]['variant_id']
                        ?? $cartItem->product_variant_id,
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

                    'product_variant_id' =>
                        $products[$item->product_slug]['variant_id']
                        ?? null,
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

    return app(\App\Http\Controllers\Buyer\AccountController::class)->account(request());

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

    return app(\App\Http\Controllers\Buyer\AccountController::class)->updateProfile($request);

    if ($redirect = $requireBuyer()) {
        return $redirect;
    }

    $validated = $request->validate([
        'first_name' => ['required', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \'\-][\pL]+)*$/u'],
        'last_name' => ['required', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \'\-][\pL]+)*$/u'],
        'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore(auth()->id())],
        'phone' => ['required', 'string', 'max:30', Rule::unique('users')->ignore(auth()->id())],
    ]);

    auth()->user()->update([
        'first_name' => $validated['first_name'],
        'last_name' => $validated['last_name'],
        'name' => trim($validated['first_name'].' '.$validated['last_name']),
        'email' => $validated['email'],
        'phone' => $validated['phone'],
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
        'current_password' => 'required|string|current_password',
        'password' => 'required|string|min:8|confirmed',
    ]);

    if (Hash::check($request->password, auth()->user()->password)) {
        return back()->withErrors([
            'password' => 'The new password must be different from your current password.',
        ]);
    }

    auth()->user()->update(['password' => $request->password]);

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

    return app(\App\Http\Controllers\Buyer\AccountController::class)->addresses(request());

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

    return app(\App\Http\Controllers\Buyer\AccountController::class)->storeAddress($request);

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

    return app(\App\Http\Controllers\Buyer\AccountController::class)->setDefault(request(), (int) $addressId);

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

    return app(\App\Http\Controllers\Buyer\AccountController::class)->deleteAddress(request(), (int) $addressId);

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

    if (!auth()->check()) {

        $intendedUrl = request()->isMethod('GET')
            ? request()->fullUrl()
            : url()->previous();

        if (!str_starts_with($intendedUrl, url('/seller'))) {
            $intendedUrl = route('seller.dashboard');
        }

        session()->put('url.intended', $intendedUrl);

        return redirect()
            ->route('login')
            ->with(
                'error',
                'Please log in as an approved seller to continue.'
            );
    }

    $user = auth()->user();

    if (! $user->hasRole('seller') || $user->status !== 'active' || $user->is_suspended) {
        abort(403, 'This account does not have approved Seller portal access.');
    }

    return null;
};

// =====================================================
// SELLER ORDERS
// =====================================================

Route::get('/seller/orders-legacy', function () {

    $orders = session('orders', []);

    $orders = array_reverse($orders, true);

    return view('seller.orders', [
        'orders' => $orders,
    ]);

})->middleware(['auth', 'not_suspended', 'approved', 'role:seller'])
    ->name('seller.orders.legacy');

Route::get('/seller/orders', [SellerFulfillmentController::class, 'orders'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:seller'])
    ->name('seller.orders');

Route::post('/seller/orders/{sellerOrder}/status', [SellerFulfillmentController::class, 'update'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:seller'])
    ->name('seller.orders.status');


// =====================================================
// SELLER REGISTRATION
// =====================================================

Route::get('/seller/register', [SellerRegistrationController::class, 'create'])
    ->name('seller.register');

Route::post('/seller/register', [SellerRegistrationController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('seller.register.submit');


// =====================================================
// SELLER DASHBOARD
// =====================================================

Route::get('/seller-legacy', function () use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    return view('seller.dashboard');

})->name('seller.dashboard.legacy');

Route::get('/seller', [SellerFulfillmentController::class, 'dashboard'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:seller'])
    ->name('seller.dashboard');
Route::get('/seller/vouchers', [VoucherController::class, 'sellerIndex'])->middleware(['auth','not_suspended','approved','role:seller'])->name('seller.vouchers.index');
Route::get('/seller/wallet', [PayoutController::class, 'seller'])->middleware(['auth','not_suspended','approved','role:seller'])->name('seller.wallet');
Route::post('/seller/wallet/payouts', [PayoutController::class, 'request'])->middleware(['auth','not_suspended','approved','role:seller'])->name('seller.payouts.store');
Route::post('/seller/vouchers', [VoucherController::class, 'store'])->middleware(['auth','not_suspended','approved','role:seller'])->name('seller.vouchers.store');
Route::patch('/seller/vouchers/{voucher}', [VoucherController::class, 'update'])->middleware(['auth','not_suspended','approved','role:seller'])->name('seller.vouchers.update');
Route::delete('/seller/vouchers/{voucher}', [VoucherController::class, 'archive'])->middleware(['auth','not_suspended','approved','role:seller'])->name('seller.vouchers.archive');


// =====================================================
// SELLER STORE PROFILE
// =====================================================

Route::get('/seller/store-profile', function () use ($requireSeller) {

    return app(\App\Http\Controllers\Seller\StoreProfileController::class)->show(request());

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    return view('seller.store-profile');

})->name('seller.store.profile');

Route::post('/seller/store-profile/profile', function (
    Request $request
) use ($requireSeller) {

    return app(\App\Http\Controllers\Seller\StoreProfileController::class)->update($request);

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $request->validate([
        'phone' => 'required|string|max:30',
        'description' => 'nullable|string|max:500',
        'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
    ]);

    $profile = session('seller_profile', []);

    $profile['phone'] = $request->phone;
    $profile['description'] = $request->description;

    if ($request->hasFile('profile_photo')) {

        if (! empty($profile['profile_photo'])) {
            Storage::disk('public')->delete($profile['profile_photo']);
        }

        $profile['profile_photo'] = $request
            ->file('profile_photo')
            ->store('seller-profiles', 'public');
    }

    session()->put('seller_profile', $profile);

    return redirect(route('seller.store.profile') . '#shop-profile')
        ->with('success', 'Shop profile updated successfully.');

})->name('seller.store.profile.update');


Route::post('/seller/store-profile/photo/remove', function () use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $profile = session('seller_profile', []);

    if (! empty($profile['profile_photo'])) {

        Storage::disk('public')->delete($profile['profile_photo']);

        unset($profile['profile_photo']);

        session()->put('seller_profile', $profile);
    }

    return redirect(route('seller.store.profile') . '#shop-profile')
        ->with('success', 'Shop photo removed successfully.');

})->name('seller.store.profile.photo.remove');


Route::post('/seller/store-profile/address', function (
    Request $request
) use ($requireSeller) {

    return app(\App\Http\Controllers\Seller\StoreProfileController::class)->address($request);

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $request->validate([
        'province' => 'required|string|max:100',
        'municipality' => 'required|string|max:100',
        'barangay' => 'required|string|max:100',
        'address' => 'required|string|max:255',
    ]);

    $profile = session('seller_profile', []);

    $profile['province'] = $request->province;
    $profile['municipality'] = $request->municipality;
    $profile['barangay'] = $request->barangay;
    $profile['address'] = $request->address;

    session()->put('seller_profile', $profile);

    return redirect(route('seller.store.profile') . '#pickup-address')
        ->with('success', 'Pickup address updated successfully.');

})->name('seller.store.address.update');


Route::post('/seller/store-profile/password', function (
    Request $request
) use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $request->validate([
        'current_password' => 'required|string|current_password',
        'password' => 'required|string|min:8|confirmed',
    ]);

    if (
        Hash::check($request->password, auth()->user()->password)
    ) {
        return back()
            ->withErrors([
                'password' => 'The new password must be different from your current password.',
            ])
            ->withInput();
    }

    auth()->user()->update(['password' => $request->password]);

    return redirect(route('seller.store.profile') . '#security')
        ->with('success', 'Password updated successfully.');

})->name('seller.store.password.update');


// =====================================================
// SELLER PRODUCTS
// =====================================================

Route::get('/seller/products', [SellerProductController::class, 'index'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:seller'])
    ->name('seller.products');


// =====================================================
// SELLER ADD PRODUCT
// =====================================================

Route::get('/seller/products/create', [SellerProductController::class, 'create'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:seller'])
    ->name('seller.products.create');


// =====================================================
// SELLER STORE PRODUCT
// =====================================================

Route::post('/seller/products', [SellerProductController::class, 'store'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:seller'])
    ->name('seller.products.store');

// =====================================================
// SELLER EDIT PRODUCT
// =====================================================

Route::get('/seller/products/{product}/edit', [SellerProductController::class, 'edit'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:seller'])
    ->name('seller.products.edit');

// =====================================================
// SELLER UPDATE PRODUCT
// =====================================================

Route::put('/seller/products/{product}', [SellerProductController::class, 'update'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:seller'])
    ->name('seller.products.update');

Route::delete('/seller/products/{product}', [SellerProductController::class, 'archive'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:seller'])
    ->name('seller.products.archive');


// =====================================================
// SELLER INVENTORY
// =====================================================

Route::get('/seller/inventory-legacy', function () use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $products = session()->get('seller_products', []);

    return view('seller.inventory', [
        'products' => $products,
    ]);

})->name('seller.inventory.legacy');

Route::get('/seller/inventory', [SellerFulfillmentController::class, 'inventory'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:seller'])
    ->name('seller.inventory');


Route::post('/seller/inventory/{variant}/increase', [SellerInventoryController::class, 'increase'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:seller'])
    ->name('seller.inventory.increase');


Route::post('/seller/inventory/{variant}/decrease', [SellerInventoryController::class, 'decrease'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:seller'])
    ->name('seller.inventory.decrease');

Route::put('/seller/inventory/{variant}', [SellerInventoryController::class, 'set'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:seller'])
    ->name('seller.inventory.set');


// =====================================================
// SELLER SALES
// =====================================================

Route::get('/seller/sales', function () use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $orders = collect(session('orders', []));

    return view('seller.sales', [
        'orders' => $orders,
    ]);

})->name('seller.sales');


// =====================================================
// SELLER PROMOTIONS
// =====================================================

Route::get('/seller/promotions', function () use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $promotions = session('seller_promotions', []);
    $products = session('seller_products', []);

    return view('seller.promotions', [
        'promotions' => $promotions,
        'products' => $products,
    ]);

})->name('seller.promotions');


Route::post('/seller/promotions', function (Request $request) use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $request->validate([
        'name' => 'required|string|max:100',
        'type' => 'required|in:percentage,fixed',
        'value' => 'required|numeric|min:0.01',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
        'product_id' => 'nullable|string|max:50',
        'status' => 'required|in:active,inactive',
    ]);

    if ($request->type === 'percentage' && (float) $request->value > 100) {
        return back()
            ->withErrors(['value' => 'Percentage discount cannot exceed 100%.'])
            ->withInput();
    }

    $promotions = session()->get('seller_promotions', []);

    $promotionId = 'PROMO-' . str_pad(
        count($promotions) + 1,
        4,
        '0',
        STR_PAD_LEFT
    );

    $promotions[$promotionId] = [
        'id' => $promotionId,
        'name' => $request->name,
        'type' => $request->type,
        'value' => (float) $request->value,
        'start_date' => $request->start_date,
        'end_date' => $request->end_date,
        'product_id' => $request->product_id,
        'status' => $request->status,
        'created_at' => now()->format('Y-m-d H:i:s'),
    ];

    session()->put('seller_promotions', $promotions);

    return redirect()
        ->route('seller.promotions')
        ->with('success', 'Promotion created successfully.');

})->name('seller.promotions.store');


Route::delete('/seller/promotions/{promotion}', function (
    $promotionId
) use ($requireSeller) {

    if ($redirect = $requireSeller()) {
        return $redirect;
    }

    $promotions = session()->get('seller_promotions', []);

    if (!isset($promotions[$promotionId])) {
        abort(404);
    }

    unset($promotions[$promotionId]);

    session()->put('seller_promotions', $promotions);

    return back()->with('success', 'Promotion deleted successfully.');

})->name('seller.promotions.destroy');


// =====================================================
// SELLER REPORTS & ANALYTICS
// =====================================================

Route::get('/seller/reports', [PhaseFiveReportController::class, 'seller'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:seller'])
    ->name('seller.reports');


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

    // Rider applications remain a legacy compatibility path. Normalize identity
    // before validation so the eventual activation flow cannot create aliases.
    $request->merge([
        'email' => \App\Support\IdentityNormalizer::email($request->input('email')),
        'phone' => \App\Support\IdentityNormalizer::philippinePhone($request->input('phone')),
    ]);

    $validated = $request->validate([

        'first_name' => 'required|string|max:100',
        'last_name' => 'required|string|max:100',
        'phone' => 'required|string|regex:/^09\d{9}$/|unique:users,phone',
        'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users')->where(fn ($query) => $query->whereRaw('LOWER(email) = ?', [$request->input('email')]))],
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
            ->store('application-documents/riders/government-ids');

    }


    if ($request->hasFile('drivers_license')) {

        $driversLicensePath = $request
            ->file('drivers_license')
            ->store('application-documents/riders/drivers-licenses');

    }


    if ($request->hasFile('vehicle_document')) {

        $vehicleDocumentPath = $request
            ->file('vehicle_document')
            ->store('application-documents/riders/vehicle-documents');

    }


    DB::transaction(function () use (
        $validated,
        $governmentIdPath,
        $driversLicensePath,
        $vehicleDocumentPath
    ) {
        $fullName = trim($validated['first_name'].' '.$validated['last_name']);

        $user = User::create([
            'role' => 'rider',
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => $fullName,
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'status' => 'pending',
            'password' => $validated['password'],
        ]);

        $role = Role::firstOrCreate(['name' => 'rider']);
        $user->roles()->syncWithoutDetaching([$role->id]);

        Application::create([
            'user_id' => $user->id,
            'application_type' => 'courier',
            'status' => 'pending',
            'application_data' => [
                'address' => $validated['address'],
                'vehicle_type' => $validated['vehicle_type'],
                'plate_number' => $validated['plate_number'] ?? null,
                'license_number' => $validated['license_number'],
            ],
            'documents' => [
                'government_id' => $governmentIdPath,
                'drivers_license' => $driversLicensePath,
                'vehicle_document' => $vehicleDocumentPath,
            ],
        ]);
    });


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

Route::get('/logistics/register', function () {
    return view('logistics.register');
})->name('logistics.register');

Route::post('/logistics/register', [LogisticsRegistrationController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('logistics.register.submit');

Route::get('/logistics/login', function () {
    return redirect()->route('login');
})->name('logistics.login');

$requireLogistics = function () {
    if (! auth()->check()) {
        return redirect()->route('login')->with('error', 'Please log in as Logistics.');
    }

    $user = auth()->user();

    if (! $user->hasRole('logistics') || $user->status !== 'active' || $user->is_suspended) {
        abort(403, 'This account does not have approved Logistics portal access.');
    }

    return null;
};

// =====================================================
// RIDER MANAGEMENT
// =====================================================

Route::get('/logistics/riders', function () use ($requireLogistics) {
    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

    $applications = Application::with('user')
        ->where('application_type', 'courier')
        ->latest()
        ->get()
        ->mapWithKeys(function (Application $application) {
            $data = $application->application_data ?? [];

            return [$application->id => array_merge($data, [
                'first_name' => $application->user->first_name,
                'last_name' => $application->user->last_name,
                'phone' => $application->user->phone,
                'email' => $application->user->email,
                'status' => $application->status,
                'documents' => $application->documents ?? [],
                'created_at' => optional($application->created_at)->format('Y-m-d H:i:s'),
            ])];
        })
        ->all();

    return view('logistics.riders', [
        'applications' => $applications,
        'riders' => $applications,
    ]);
})->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.riders');

// =====================================================
// RIDER REVIEW
// =====================================================

Route::get('/logistics/riders/{rider}/review', function ($riderId) use ($requireLogistics) {
    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

    $application = Application::with('user')
        ->where('application_type', 'courier')
        ->findOrFail($riderId);
    $rider = array_merge($application->application_data ?? [], [
        'first_name' => $application->user->first_name,
        'last_name' => $application->user->last_name,
        'phone' => $application->user->phone,
        'email' => $application->user->email,
        'status' => $application->status,
        'documents' => $application->documents ?? [],
    ]);

    return view('logistics.rider-review', compact('rider', 'riderId'));
})->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.riders.review');

// =====================================================
// APPROVE RIDER
// =====================================================

Route::post('/logistics/riders/{riderId}/approve', function ($riderId) use ($requireLogistics) {
    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

    $application = Application::with('user')
        ->where('application_type', 'courier')
        ->where('status', 'pending')
        ->findOrFail($riderId);
    $provider = auth()->user()->logisticsProvider;

    abort_unless($provider && $provider->status === 'approved', 403, 'An approved logistics provider is required.');

    DB::transaction(function () use ($application, $provider) {
        $data = $application->application_data ?? [];
        $documents = $application->documents ?? [];

        Rider::updateOrCreate(
            ['user_id' => $application->user_id],
            [
                'logistics_provider_id' => $provider->id,
                'vehicle_type' => $data['vehicle_type'] ?? 'Unspecified',
                'plate_no' => $data['plate_number'] ?? 'N/A',
                'license_number' => $data['license_number'] ?? null,
                'government_id_path' => $documents['government_id'] ?? null,
                'drivers_license_path' => $documents['drivers_license'] ?? null,
                'vehicle_document_path' => $documents['vehicle_document'] ?? null,
                'is_active' => false,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]
        );

        $application->user->update(['status' => 'pending', 'verification_required_at' => now()]);
        $application->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);
    });

    app(\App\Services\RiderActivationService::class)->send($application->user->fresh(), false);

    $application->user->notify(new \App\Notifications\ApplicationDecisionNotification($application->fresh()));

    return redirect()->route('logistics.riders')->with('success', 'Rider approved. A single-use activation link was sent by email.');
})->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.riders.approve');


// =====================================================
// DISAPPROVE RIDER
// =====================================================

Route::post('/logistics/riders/{riderId}/disapprove', function (Request $request, $riderId) use ($requireLogistics) {
    if ($redirect = $requireLogistics()) {
        return $redirect;
    }

    $validated = $request->validate([
        'rejection_reason' => 'nullable|string|max:500',
    ]);
    $application = Application::with('user')
        ->where('application_type', 'courier')
        ->where('status', 'pending')
        ->findOrFail($riderId);

    DB::transaction(function () use ($application, $validated) {
        $application->user->update(['status' => 'rejected']);
        $application->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'] ?? 'Application requirements were not approved.',
        ]);
    });

    $application->user->notify(new \App\Notifications\ApplicationDecisionNotification($application->fresh()));

    return redirect()->route('logistics.riders')->with('success', 'Rider application has been rejected.');
})->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.riders.disapprove');

// =====================================================
// RIDER LOGIN PAGE
// =====================================================

Route::get('/rider/login', function () {

    return view('rider.login');

})->name('rider.login');

Route::get('/rider/activate/{token}', [RiderActivationController::class, 'show'])
    ->middleware('throttle:10,1')->name('rider.activation.show');
Route::post('/rider/activate/{token}', [RiderActivationController::class, 'store'])
    ->middleware('throttle:5,1')->name('rider.activation.store');


// =====================================================
// RIDER LOGIN SUBMIT
// =====================================================

Route::post('/rider/login', function (Request $request) {
    $validated = $request->validate([
        'login' => 'required|string',
        'password' => 'required|string',
    ]);

    $loginField = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
    $authenticated = auth()->attempt([
        $loginField => $validated['login'],
        'password' => $validated['password'],
        'role' => 'rider',
        'status' => 'active',
    ]);

    if (! $authenticated || auth()->user()->is_suspended || ! auth()->user()->hasVerifiedContacts() || ! auth()->user()->rider?->is_active) {
        auth()->logout();

        return back()->withInput()->with('error', 'Invalid credentials or the rider account is not approved and active.');
    }

    $request->session()->regenerate();
    $rider = auth()->user()->rider;
    session()->put('rider_logged_in', true);
    session()->put('logged_in_rider_index', $rider->id);
    session()->put('rider_applications.'.$rider->id, [
        'first_name' => auth()->user()->first_name,
        'last_name' => auth()->user()->last_name,
        'phone' => auth()->user()->phone,
        'email' => auth()->user()->email,
        'vehicle_type' => $rider->vehicle_type,
        'plate_number' => $rider->plate_no,
        'status' => 'approved',
    ]);

    return redirect()->route('rider.dashboard')->with(
        'success',
        'Welcome back, '.(auth()->user()->first_name ?? 'Rider').'!'
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

})->middleware(['auth', 'not_suspended', 'approved', 'role:rider'])
    ->name('rider.dashboard');

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

})->middleware(['auth', 'not_suspended', 'approved', 'role:rider'])
    ->name('rider.deliveries');


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
)->middleware(['auth', 'not_suspended', 'approved', 'role:rider'])
    ->name('rider.pickup.accept');


// =====================================================
// RIDER EARNINGS
// =====================================================

Route::get('/legacy/rider/earnings', function (Request $request) {
    return app(RiderFulfillmentController::class)->performance($request);

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

    // No authoritative Rider compensation formula is configured yet.
    $deliveryFee = 0;


    $totalDeliveries =
        $completedOrders->count();


    $totalEarnings = null;


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

})->middleware(['auth', 'not_suspended', 'approved', 'role:rider'])
    ->name('rider.earnings.legacy');

Route::get('/rider/earnings', [RiderFulfillmentController::class, 'performance'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:rider'])
    ->name('rider.earnings');

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

})->middleware(['auth', 'not_suspended', 'approved', 'role:rider'])
    ->name('rider.history');

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
)->middleware(['auth', 'not_suspended', 'approved', 'role:rider'])
    ->name('rider.order.status');

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

})->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.dashboard');

// =====================================================
// LOGISTICS PARCEL SORTING
// =====================================================

Route::get('/logistics/sorting', function () {

    return view('logistics.sorting');

})->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.sorting');


// =====================================================
// LOGISTICS DELIVERY ASSIGNMENTS
// =====================================================

Route::get('/logistics/assignments', function () {

    return view('logistics.assignments');

})->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.assignments');


// =====================================================
// LOGISTICS DELIVERY MONITORING
// =====================================================

Route::get('/logistics/monitoring', function () {

    return view('logistics.monitoring');

})->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.monitoring');


// =====================================================
// LOGISTICS REPORTS
// =====================================================

Route::get('/logistics/reports', [PhaseFiveReportController::class, 'logistics'])
    ->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.reports');

// =====================================================
// LOGISTICS PARCELS
// =====================================================

Route::get('/logistics/parcels', function () {

    $orders = session('orders', []);

    return view('logistics.parcels', [
        'orders' => $orders,
    ]);

})->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.parcels');


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

})->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.shipments');


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

})->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.order.status');

// Phase 2 database-backed fulfillment routes. The legacy session closures above
// remain available as controller fallbacks when no database records exist.
Route::middleware(['auth', 'not_suspended', 'approved', 'role:rider'])->group(function () {
    Route::post('/rider/availability', [\App\Http\Controllers\Rider\AvailabilityController::class, 'update'])->name('rider.availability.update');
    Route::get('/rider/dashboard', [RiderFulfillmentController::class, 'dashboard'])->name('rider.dashboard');
    Route::get('/rider/deliveries', [RiderFulfillmentController::class, 'deliveries'])->name('rider.deliveries');
    Route::get('/rider/history', [RiderFulfillmentController::class, 'history'])->name('rider.history');
    Route::get('/rider/reports', [PhaseFiveReportController::class, 'rider'])->name('rider.reports');
    Route::post('/rider/erp-orders/{sellerOrder}/accept-pickup', [RiderFulfillmentController::class, 'accept'])
        ->name('rider.database.pickup.accept');
    Route::post('/rider/erp-orders/{sellerOrder}/decline-pickup', [RiderFulfillmentController::class, 'decline'])
        ->name('rider.database.pickup.decline');
    Route::post('/rider/erp-orders/{sellerOrder}/accept-delivery', [RiderFulfillmentController::class, 'acceptDelivery'])->name('rider.database.delivery.accept');
    Route::post('/rider/erp-orders/{sellerOrder}/decline-delivery', [RiderFulfillmentController::class, 'declineDelivery'])->name('rider.database.delivery.decline');
    Route::post('/rider/erp-orders/{sellerOrder}/status', [RiderFulfillmentController::class, 'update'])
        ->name('rider.database.order.status');
    Route::post('/rider/erp-orders/{sellerOrder}/failed-delivery', [RiderFulfillmentController::class, 'failed'])
        ->name('rider.database.delivery.failed');
    Route::post('/rider/erp-orders/{sellerOrder}/delivery-issue', [RiderFulfillmentController::class, 'issue'])
        ->name('rider.database.delivery.issue');
    Route::post('/rider/messages/context/seller-orders/{sellerOrder}/shipment', [ContextConversationController::class, 'riderShipment'])
        ->middleware('throttle:20,1')
        ->name('rider.messages.context.shipment.store');
    Route::get('/rider/erp-orders/{sellerOrder}/complaints/create', [ParticipantComplaintController::class, 'createForSellerOrder'])
        ->name('rider.complaints.create');
    Route::post('/rider/erp-orders/{sellerOrder}/complaints', [ParticipantComplaintController::class, 'storeForSellerOrder'])
        ->name('rider.complaints.store');
});

Route::middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])->group(function () {
    Route::get('/logistics', [LogisticsFulfillmentController::class, 'dashboard'])->name('logistics.dashboard');
    Route::get('/logistics/parcels', [LogisticsFulfillmentController::class, 'parcels'])->name('logistics.parcels');
    Route::get('/logistics/sorting', [LogisticsFulfillmentController::class, 'sorting'])->name('logistics.sorting');
    Route::get('/logistics/assignments', [LogisticsFulfillmentController::class, 'assignments'])->name('logistics.assignments');
    Route::get('/logistics/parcel-lookup', [LogisticsFulfillmentController::class, 'lookup'])->name('logistics.parcels.lookup');
    Route::get('/logistics/shipments', [LogisticsFulfillmentController::class, 'parcels'])->name('logistics.shipments');
    Route::post('/logistics/erp-orders/{sellerOrder}/receive', [LogisticsFulfillmentController::class, 'receive'])
        ->name('logistics.database.orders.receive');
    Route::post('/logistics/erp-orders/{sellerOrder}/pickup-rider', [LogisticsFulfillmentController::class, 'assignPickup'])
        ->name('logistics.database.pickup-rider.assign');
    Route::post('/logistics/erp-orders/{sellerOrder}/delivery-rider', [LogisticsFulfillmentController::class, 'reassignDelivery'])
        ->name('logistics.database.delivery-rider.reassign');
    Route::post('/logistics/erp-orders/{sellerOrder}/reschedule', [LogisticsFulfillmentController::class, 'reschedule'])->name('logistics.database.delivery.reschedule');
    Route::post('/logistics/erp-orders/{sellerOrder}/return', [LogisticsFulfillmentController::class, 'advanceReturn'])->name('logistics.database.delivery.return');
    Route::post('/logistics/erp-orders/{sellerOrder}/status', [LogisticsFulfillmentController::class, 'update'])
        ->name('logistics.database.order.status');
    Route::post('/logistics/messages/context/seller-orders/{sellerOrder}/shipment', [ContextConversationController::class, 'logisticsShipment'])
        ->middleware('throttle:20,1')
        ->name('logistics.messages.context.shipment.store');
    Route::get('/logistics/erp-orders/{sellerOrder}/complaints/create', [ParticipantComplaintController::class, 'createForSellerOrder'])
        ->name('logistics.complaints.create');
    Route::post('/logistics/erp-orders/{sellerOrder}/complaints', [ParticipantComplaintController::class, 'storeForSellerOrder'])
        ->name('logistics.complaints.store');
});

Route::middleware(['auth', 'not_suspended', 'approved', 'role:buyer'])->group(function () {
    Route::get('/my-orders/{order:order_number}/complaints/create', [ParticipantComplaintController::class, 'createForOrder'])
        ->name('buyer.complaints.create');
    Route::post('/my-orders/{order:order_number}/complaints', [ParticipantComplaintController::class, 'storeForOrder'])
        ->name('buyer.complaints.store');
});

Route::middleware(['auth', 'not_suspended', 'approved', 'role:seller'])->group(function () {
    Route::get('/seller/orders/{sellerOrder}/complaints/create', [ParticipantComplaintController::class, 'createForSellerOrder'])
        ->name('seller.complaints.create');
    Route::post('/seller/orders/{sellerOrder}/complaints', [ParticipantComplaintController::class, 'storeForSellerOrder'])
        ->name('seller.complaints.store');
});

Route::middleware(['auth', 'not_suspended', 'approved', 'role:buyer,seller,rider,logistics'])->group(function () {
    Route::get('/complaints', [ParticipantComplaintController::class, 'index'])->name('complaints.mine.index');
    Route::get('/complaints/{complaint}', [ParticipantComplaintController::class, 'show'])->name('complaints.mine.show');
    Route::post('/complaints/{complaint}/evidence', [ComplaintEvidenceController::class, 'store'])->name('complaints.evidence.store');
    Route::get('/complaints/{complaint}/evidence/{evidence}', [ComplaintEvidenceController::class, 'show'])->name('complaints.evidence.show');
    Route::get('/complaints/{complaint}/evidence/{evidence}/download', [ComplaintEvidenceController::class, 'download'])->name('complaints.evidence.download');
    Route::post('/complaints/{complaint}/conversation', [ContextConversationController::class, 'complaint'])
        ->middleware('throttle:20,1')
        ->name('complaints.messages.context.store');
});

Route::middleware(['auth', 'not_suspended', 'approved', 'role:buyer'])->group(function () {
    Route::post('/buyer/messages/context/products/{product}', [ContextConversationController::class, 'productInquiry'])
        ->middleware('throttle:20,1')
        ->name('buyer.messages.context.product.store');
    Route::post('/buyer/messages/context/seller-orders/{sellerOrder}', [ContextConversationController::class, 'buyerSellerOrder'])
        ->middleware('throttle:20,1')
        ->name('buyer.messages.context.seller-order.store');
});

Route::middleware(['auth', 'not_suspended', 'approved', 'role:seller'])->group(function () {
    Route::post('/seller/messages/context/seller-orders/{sellerOrder}', [ContextConversationController::class, 'sellerOrder'])
        ->middleware('throttle:20,1')
        ->name('seller.messages.context.seller-order.store');
});

// Phase 3.4 database-backed messaging interface. Conversation creation entry
// points remain intentionally separate for the next integration stage.
$registerMessagingRoutes = function (
    string $uriPrefix,
    string $namePrefix,
    string $role,
    string $portal
): void {
    Route::prefix($uriPrefix)
        ->name($namePrefix.'.messages.')
        ->middleware(['auth', 'not_suspended', 'approved', 'role:'.$role])
        ->group(function () use ($portal) {
            Route::get('/messages', [MessagingConversationController::class, 'index'])
                ->defaults('messaging_portal', $portal)
                ->name('index');
            Route::get('/messages/{conversation:reference}/poll', [MessagingMessageController::class, 'index'])
                ->middleware('throttle:120,1')
                ->defaults('messaging_portal', $portal)
                ->name('poll');
            Route::post('/messages/{conversation:reference}/send', [MessagingMessageController::class, 'store'])
                ->middleware('throttle:30,1')
                ->defaults('messaging_portal', $portal)
                ->name('store');
            Route::post('/messages/{conversation:reference}/read', [ConversationReadController::class, 'store'])
                ->middleware('throttle:60,1')
                ->defaults('messaging_portal', $portal)
                ->name('read');
            Route::get('/messages/{conversation:reference}', [MessagingConversationController::class, 'show'])
                ->defaults('messaging_portal', $portal)
                ->name('show');
        });
};

$registerMessagingRoutes('buyer', 'buyer', 'buyer', 'buyer');
$registerMessagingRoutes('seller', 'seller', 'seller', 'seller');
$registerMessagingRoutes('rider', 'rider', 'rider', 'rider');
$registerMessagingRoutes('logistics', 'logistics', 'logistics', 'logistics');
$registerMessagingRoutes('admin', 'admin', 'admin,superadmin', 'admin');
// Compatibility for existing bookmarks and integrations during the rename.
$registerMessagingRoutes('superadmin', 'superadmin', 'admin,superadmin', 'superadmin');

// =====================================================
// LOGISTICS RIDER MANAGEMENT
// =====================================================

// Retained temporarily for reference while the Phase 1 database-backed
// approval routes above replace the legacy session implementation.
if (false) {

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

})->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.riders');

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

})->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.riders.review');



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

})->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.riders.approve');



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

})->middleware(['auth', 'not_suspended', 'approved', 'role:logistics'])
    ->name('logistics.riders.disapprove');

}

$registerAdminPortalRoutes = function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/users', [UserManagementController::class, 'index'])->name('users');
        Route::get('/users/search', [UserManagementController::class, 'search'])->name('users.search');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}/status', [UserManagementController::class, 'updateStatus'])->name('users.status');

        Route::get('/buyers', [BuyerManagementController::class, 'index'])->name('buyers');
        Route::get('/buyers/search', [BuyerManagementController::class, 'search'])->name('buyers.search');
        Route::patch('/buyers/{buyer}/status', [BuyerManagementController::class, 'updateStatus'])->name('buyers.status');

        Route::get('/sellers', [SellerManagementController::class, 'index'])->name('sellers');
        Route::get('/sellers/search', [SellerManagementController::class, 'search'])->name('sellers.search');
        Route::get('/sellers/{seller}', [SellerManagementController::class, 'show'])->name('sellers.show');
        Route::patch('/sellers/{seller}/status', [SellerManagementController::class, 'updateStatus'])->name('sellers.status');
        Route::put('/sellers/{seller}/allowed-categories', [SellerManagementController::class, 'updateAllowedCategories'])
            ->name('sellers.allowed-categories.update');

        Route::get('/products', [ProductModerationController::class, 'index'])->name('products.index');
        Route::get('/vouchers', [VoucherController::class, 'adminIndex'])->name('vouchers.index');
        Route::get('/payouts', [PayoutController::class, 'admin'])->name('payouts.index');
        Route::patch('/payouts/{payout}', [PayoutController::class, 'review'])->name('payouts.review');
        Route::post('/payouts/{payout}/paid', [PayoutController::class, 'paid'])->name('payouts.paid');
        Route::post('/vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
        Route::patch('/vouchers/{voucher}', [VoucherController::class, 'update'])->name('vouchers.update');
        Route::delete('/vouchers/{voucher}', [VoucherController::class, 'archive'])->name('vouchers.archive');
        Route::get('/products/{product}', [ProductModerationController::class, 'show'])->name('products.show');
        Route::post('/products/{product}/approve', [ProductModerationController::class, 'approve'])->name('products.approve');
        Route::post('/products/{product}/reject', [ProductModerationController::class, 'reject'])->name('products.reject');
        Route::post('/products/{product}/hide', [ProductModerationController::class, 'hide'])->name('products.hide');
        Route::post('/products/{product}/restore', [ProductModerationController::class, 'restore'])->name('products.restore');
        Route::patch('/products/{product}/commission', [ProductModerationController::class, 'updateCommission'])->name('products.commission.update');

        Route::get('/compliance', [SellerComplianceController::class, 'index'])->name('compliance.index');
        Route::get('/compliance/create', [SellerComplianceController::class, 'create'])->name('compliance.create');
        Route::post('/compliance', [SellerComplianceController::class, 'store'])->name('compliance.store');
        Route::get('/compliance/{violation}', [SellerComplianceController::class, 'show'])->name('compliance.show');
        Route::patch('/compliance/{violation}/severity', [SellerComplianceController::class, 'updateSeverity'])->name('compliance.severity');
        Route::post('/compliance/{violation}/warnings', [SellerComplianceController::class, 'storeWarning'])->name('compliance.warnings.store');
        Route::post('/compliance/{violation}/evidence', [SellerComplianceEvidenceController::class, 'store'])->name('compliance.evidence.store');
        Route::get('/compliance/{violation}/evidence/{evidence}', [SellerComplianceEvidenceController::class, 'show'])->name('compliance.evidence.show');
        Route::get('/compliance/{violation}/evidence/{evidence}/download', [SellerComplianceEvidenceController::class, 'download'])->name('compliance.evidence.download');
        Route::post('/compliance/{violation}/resolve', [SellerComplianceController::class, 'resolve'])->name('compliance.resolve');
        Route::post('/compliance/{violation}/dismiss', [SellerComplianceController::class, 'dismiss'])->name('compliance.dismiss');
        Route::post('/compliance/{violation}/suspend-seller', [SellerComplianceController::class, 'suspendSeller'])->name('compliance.suspend-seller');

        Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
        Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
        Route::patch('/complaints/{complaint}/assignment', [ComplaintController::class, 'assign'])->name('complaints.assignment');
        Route::patch('/complaints/{complaint}/status', [ComplaintController::class, 'updateStatus'])->name('complaints.status');
        Route::post('/complaints/{complaint}/resolve', [ComplaintController::class, 'resolve'])->name('complaints.resolve');
        Route::post('/complaints/{complaint}/dismiss', [ComplaintController::class, 'dismiss'])->name('complaints.dismiss');
        Route::post('/complaints/{complaint}/conversation', [ContextConversationController::class, 'complaint'])
            ->middleware('throttle:20,1')
            ->name('messages.context.complaint.store');
        Route::post('/complaints/{complaint}/evidence', [ComplaintEvidenceController::class, 'store'])->name('complaints.evidence.store');
        Route::get('/complaints/{complaint}/evidence/{evidence}', [ComplaintEvidenceController::class, 'show'])->name('complaints.evidence.show');
        Route::get('/complaints/{complaint}/evidence/{evidence}/download', [ComplaintEvidenceController::class, 'download'])->name('complaints.evidence.download');

        Route::get('/applications', [AdminApplicationController::class, 'index'])->name('applications');
        Route::get('/applications/{application}/documents/{document}', [ApplicationDocumentController::class, 'show'])
            ->name('applications.documents.show');
        Route::get('/applications/{application}', [AdminApplicationController::class, 'show'])->name('applications.show');
        Route::post('/applications/{application}/approve', [AdminApplicationController::class, 'approve'])->name('applications.approve');
        Route::post('/applications/{application}/reject', [AdminApplicationController::class, 'reject'])->name('applications.reject');

        Route::get('/orders', [OrderManagementController::class, 'index'])->name('orders');
        Route::get('/orders/{order}', [OrderManagementController::class, 'show'])->name('orders.show');
        Route::get('/commission', [CommissionController::class, 'index'])->name('commission');
        Route::patch('/commission/{seller}', [CommissionController::class, 'updateRate'])->name('commission.rate');
        Route::get('/reports', [PhaseFiveReportController::class, 'admin'])->name('reports');

        Route::get('/settings', [SettingController::class, 'index'])->name('settings');
        Route::patch('/settings/platform', [SettingController::class, 'updatePlatform'])->name('settings.platform');
        Route::patch('/settings/policies', [SettingController::class, 'updatePolicies'])->name('settings.policies');
        Route::post('/settings/announcements', [SettingController::class, 'storeAnnouncement'])->name('settings.announcement.store');
        Route::patch('/settings/announcements/{announcement}', [SettingController::class, 'updateAnnouncement'])->name('settings.announcement.update');
        Route::patch('/settings/announcements/{announcement}/deactivate', [SettingController::class, 'deactivateAnnouncement'])->name('settings.announcement.deactivate');
};

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'not_suspended', 'approved', 'role:admin,superadmin'])
    ->group($registerAdminPortalRoutes);

// Temporary compatibility surface. The Admin portal now generates /admin URLs,
// but legacy /superadmin URLs and route names continue to execute the same code.
Route::prefix('superadmin')
    ->name('superadmin.')
    ->middleware(['auth', 'not_suspended', 'approved', 'role:admin,superadmin'])
    ->group($registerAdminPortalRoutes);

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
        ! $user->isAdmin() ||
        $user->status !== 'active' ||
        $user->is_suspended
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
        auth()->user()->isAdmin() &&
        auth()->user()->status === 'active' &&
        ! auth()->user()->is_suspended
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
        'status' => 'active',
    ]);

    if (
        ! $loggedIn ||
        ! auth()->user()->isAdmin() ||
        auth()->user()->is_suspended
    ) {
        auth()->logout();

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
// ADMIN BUYER APPLICATION COMPATIBILITY ACTIONS
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

        abort_unless($buyer->isBuyer(), 404);

        $application = $buyer->applications()
            ->where('application_type', 'buyer')
            ->where('status', 'pending')
            ->latest()
            ->firstOrFail();

        $application = app(\App\Services\Admin\AccountStatusService::class)
            ->approveApplication($application, auth()->user());

        $buyer->notify(new \App\Notifications\ApplicationDecisionNotification($application));

        return redirect()
            ->route('admin.applications.show', $application)
            ->with('success', $buyer->full_name . ' has been approved successfully.');

    }
)->name('admin.buyers.approve');


// =====================================================
// ADMIN REJECT BUYER
// =====================================================

Route::post(
    '/admin/buyers/{buyer}/reject',
    function (
        Request $request,
        User $buyer
    ) use (
        $requireAdmin
    ) {

        if ($redirect = $requireAdmin()) {
            return $redirect;
        }

        abort_unless($buyer->isBuyer(), 404);

        $application = $buyer->applications()
            ->where('application_type', 'buyer')
            ->where('status', 'pending')
            ->latest()
            ->firstOrFail();

        $reason = $request->input('reason', 'Application rejected by administrator.');
        $application = app(\App\Services\Admin\AccountStatusService::class)
            ->rejectApplication($application, auth()->user(), $reason);

        $buyer->notify(new \App\Notifications\ApplicationDecisionNotification($application));

        return redirect()
            ->route('admin.applications.show', $application)
            ->with('success', $buyer->full_name . ' has been rejected.');

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
            $user->isAdmin() &&
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

        if ($user->hasRole('seller') && $user->status === 'active') {
            return redirect()->route('seller.dashboard');
        }

        if ($user->hasRole('rider') && $user->status === 'active') {
            return redirect()->route('rider.dashboard');
        }

        if ($user->hasRole('logistics') && $user->status === 'active') {
            return redirect()->route('logistics.dashboard');
        }
    }

    return view('auth.login');

})->name('login');

// Landing-page account switch: intentionally end the current authenticated
// session before showing the normal guest-only login form.
Route::post('/login/switch', function (Request $request) {
    auth()->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('login.switch');


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

    if (! $user->hasVerifiedContacts()) {
        $verificationUserId = $user->id;
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('verification_user_id', $verificationUserId);

        return redirect()->route('verification.contact.show')
            ->with('error', 'Complete email verification before signing in.');
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

    if ($user->isAdmin()) {

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

    if ($user->hasRole('seller')) {
        $seller = $user->sellers()->first();
        $address = $seller?->pickupAddress;

        $request->session()->put('seller_logged_in', true);
        $request->session()->put('seller_profile', [
            'shop_name' => $seller?->name,
            'business_category' => $seller?->business_category,
            'seller_name' => $user->full_name,
            'phone' => $user->phone,
            'email' => $user->email,
            'province' => $address?->province,
            'municipality' => $address?->city,
            'barangay' => $address?->barangay,
            'address' => $address?->line1,
            'status' => $seller?->status,
        ]);

        return redirect()->intended(route('seller.dashboard'))->with('success', 'Welcome to the Seller portal.');
    }

    if ($user->hasRole('rider')) {
        $rider = $user->rider;

        if (! $rider || ! $rider->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => 'This rider account is not active.']);
        }

        $request->session()->put('rider_logged_in', true);
        $request->session()->put('logged_in_rider_index', $rider->id);
        $request->session()->put('rider_applications.'.$rider->id, [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone' => $user->phone,
            'email' => $user->email,
            'vehicle_type' => $rider->vehicle_type,
            'plate_number' => $rider->plate_no,
            'status' => 'approved',
        ]);

        return redirect()->intended(route('rider.dashboard'))->with('success', 'Welcome to the Rider portal.');
    }

    if ($user->hasRole('logistics')) {
        return redirect()->intended(route('logistics.dashboard'))->with('success', 'Welcome to the Logistics portal.');
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


// =====================================================
// REGISTER SUBMIT
// =====================================================

Route::post('/register', [BuyerRegistrationController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('register.submit');

Route::get('/auth/google/redirect', [\App\Http\Controllers\Auth\GoogleOAuthController::class, 'redirect'])
    ->middleware('guest')
    ->name('auth.google.redirect');
Route::get('/auth/google/callback', [\App\Http\Controllers\Auth\GoogleOAuthController::class, 'callback'])
    ->middleware('guest')
    ->name('auth.google.callback');

Route::get('/verify-contact', [AccountVerificationController::class, 'show'])
    ->name('verification.contact.show');
Route::post('/verify-contact', [AccountVerificationController::class, 'verify'])
    ->middleware('throttle:10,1')
    ->name('verification.contact.verify');
Route::post('/verify-contact/resend', [AccountVerificationController::class, 'resend'])
    ->middleware('throttle:3,5')
    ->name('verification.contact.resend');


// =====================================================
// LOGOUT
// =====================================================

Route::post('/logout', function (Request $request) {

    $user = $request->user();
    $redirectRoute = match (true) {
        $user?->isAdmin() => 'admin.login',
        $user?->hasAnyRole(['seller', 'rider', 'logistics']) => 'landing',
        default => 'landing',
    };

    auth()->logout();

    $request->session()->forget([
        'buyer_logged_in',
        'buyer_profile',
    ]);

    $request->session()->invalidate();

    $request->session()->regenerateToken();

    return redirect()
        ->route($redirectRoute)
        ->with(
            'success',
            'You have been logged out successfully.'
        );

})->name('logout');


