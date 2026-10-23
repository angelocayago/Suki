<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\Category;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\AddressApiController;

// ============================================================
// MOBILE RIDER API CONTROLLERS
// ============================================================

use App\Http\Controllers\Api\Rider\DashboardController as RiderDashboardController;
use App\Http\Controllers\Api\Rider\OrderController as RiderOrderController;
use App\Http\Controllers\Api\Rider\ScannerController as RiderScannerController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are used by the BiliHub mobile application.
|
*/

// ============================================================
// PUBLIC ROUTES
// ============================================================

Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'BiliHub Flutter successfully connected to Laravel API.',
        'app' => 'BiliHub',
    ]);
});

// ============================================================
// AUTH
// ============================================================

Route::post('/auth/register', [
    AuthController::class,
    'register',
]);

Route::post('/auth/login', [
    AuthController::class,
    'login',
]);

Route::post('/auth/social/google', [
    AuthController::class,
    'googleLogin',
]);

Route::post('/auth/social/facebook', [
    AuthController::class,
    'facebookLogin',
]);

Route::post('/auth/forgot-password', [
    AuthController::class,
    'forgotPassword',
]);

Route::post('/auth/reset-password', [
    AuthController::class,
    'resetPassword',
]);

// ============================================================
// PUBLIC PRODUCT BROWSING
// ============================================================

Route::get('/products', [
    ProductController::class,
    'index',
]);

Route::get('/products/categories', [
    ProductController::class,
    'categories',
]);

Route::get('/products/sizes', [
    ProductController::class,
    'sizes',
]);

Route::get('/products/{product}', [
    ProductController::class,
    'show',
]);

// ============================================================
// PUBLIC ADDRESS API
// Philippine geographic data
// ============================================================

Route::prefix('addresses')
    ->name('api.addresses.')
    ->group(function () {

        Route::get(
            '/regions',
            [AddressApiController::class, 'regions']
        )->name('regions');

        Route::get(
            '/regions/{regionCode}',
            [AddressApiController::class, 'region']
        )->name('region');

        Route::get(
            '/regions/{regionCode}/provinces',
            [AddressApiController::class, 'regionProvinces']
        )->name('regionProvinces');

        Route::get(
            '/regions/{regionCode}/cities',
            [AddressApiController::class, 'regionCities']
        )->name('regionCities');

        Route::get(
            '/provinces',
            [AddressApiController::class, 'provinces']
        )->name('provinces');

        Route::get(
            '/provinces/{provinceCode}',
            [AddressApiController::class, 'province']
        )->name('province');

        Route::get(
            '/provinces/{provinceCode}/municipalities',
            [AddressApiController::class, 'provinceMunicipalities']
        )->name('provinceMunicipalities');

        Route::get(
            '/provinces/{provinceCode}/cities',
            [AddressApiController::class, 'provinceCities']
        )->name('provinceCities');

        Route::get(
            '/cities',
            [AddressApiController::class, 'cities']
        )->name('cities');

        Route::get(
            '/cities/{cityCode}',
            [AddressApiController::class, 'city']
        )->name('city');

        Route::get(
            '/cities/{cityCode}/barangays',
            [AddressApiController::class, 'cityBarangays']
        )->name('cityBarangays');

        Route::get(
            '/municipalities',
            [AddressApiController::class, 'municipalities']
        )->name('municipalities');

        Route::get(
            '/municipalities/{municipalityCode}',
            [AddressApiController::class, 'municipality']
        )->name('municipality');

        Route::get(
            '/municipalities/{municipalityCode}/barangays',
            [AddressApiController::class, 'municipalityBarangays']
        )->name('municipalityBarangays');

        Route::get(
            '/barangays',
            [AddressApiController::class, 'barangays']
        )->name('barangays');

        Route::get(
            '/barangays/{barangayCode}',
            [AddressApiController::class, 'barangay']
        )->name('barangay');

        Route::get(
            '/districts',
            [AddressApiController::class, 'districts']
        )->name('districts');

        Route::get(
            '/districts/{districtCode}',
            [AddressApiController::class, 'district']
        )->name('district');

        Route::get(
            '/sub-municipalities',
            [AddressApiController::class, 'subMunicipalities']
        )->name('subMunicipalities');

        Route::get(
            '/sub-municipalities/{subMunicipalityCode}',
            [AddressApiController::class, 'subMunicipality']
        )->name('subMunicipality');
    });

// ============================================================
// PROTECTED GENERAL MOBILE ROUTES
// ============================================================

Route::middleware('auth:sanctum')->group(function () {

    // ========================================================
    // AUTH
    // ========================================================

    Route::post('/auth/logout', [
        AuthController::class,
        'logout',
    ]);

    Route::post('/auth/logout-all', [
        AuthController::class,
        'logoutAll',
    ]);

    Route::post('/auth/refresh', [
        AuthController::class,
        'refresh',
    ]);

    Route::get('/auth/user', [
        AuthController::class,
        'user',
    ]);

    Route::put('/auth/profile', [
        AuthController::class,
        'updateProfile',
    ]);

    Route::put('/auth/password', [
        AuthController::class,
        'updatePassword',
    ]);

    Route::post('/auth/device', [
        AuthController::class,
        'registerDevice',
    ]);

    // ========================================================
    // ADDRESSES
    // User saved addresses
    // ========================================================

    Route::apiResource(
        'addresses',
        AddressController::class
    )->only([
        'index',
        'store',
        'show',
        'update',
        'destroy',
    ]);

    Route::post(
        '/addresses/{address}/set-default',
        [AddressController::class, 'setDefault']
    );

    // ========================================================
    // CART
    // ========================================================

    Route::get('/cart', [
        CartController::class,
        'index',
    ]);

    Route::post('/cart', [
        CartController::class,
        'store',
    ]);

    Route::put('/cart/{cartItem}', [
        CartController::class,
        'update',
    ]);

    Route::delete('/cart/{cartItem}', [
        CartController::class,
        'destroy',
    ]);

    Route::delete('/cart', [
        CartController::class,
        'clear',
    ]);

    Route::post('/buy-now', [
        CartController::class,
        'buyNow',
    ]);

    // ========================================================
    // ORDERS
    // General buyer orders
    // ========================================================

    Route::get('/orders', [
        OrderController::class,
        'index',
    ]);

    Route::post('/orders', [
        OrderController::class,
        'store',
    ]);

    Route::get('/orders/{order}', [
        OrderController::class,
        'show',
    ]);

    Route::post('/orders/{order}/cancel', [
        OrderController::class,
        'cancel',
    ]);

    Route::post('/orders/{order}/rate', [
        OrderController::class,
        'rate',
    ]);

    // ========================================================
    // NOTIFICATIONS
    // ========================================================

    Route::get('/notifications', [
        NotificationController::class,
        'index',
    ]);

    Route::post(
        '/notifications/{notification}/read',
        [NotificationController::class, 'markAsRead']
    );

    Route::post(
        '/notifications/read-all',
        [NotificationController::class, 'markAllAsRead']
    );

    // ========================================================
    // SHIPMENTS / TRACKING
    // ========================================================

    Route::get(
        '/shipments/{shipment}/track',
        [ShipmentController::class, 'track']
    );

    Route::get(
        '/orders/{order}/shipment',
        [ShipmentController::class, 'showForOrder']
    );
});

// ============================================================
// MOBILE RIDER API
// ============================================================
//
// IMPORTANT:
//
// Mobile rider controllers:
//
// app/Http/Controllers/Api/Rider/
//
// Existing web rider controllers:
//
// app/Http/Controllers/Rider/
//
// The mobile API does NOT replace the web Rider controllers.
// Both use the same database/business data.
//
// ============================================================

Route::middleware([
    'auth:sanctum',
    'role:rider',
])
    ->prefix('rider')
    ->name('rider.api.')
    ->group(function () {

        // ====================================================
        // RIDER DASHBOARD
        // ====================================================
        //
        // GET /api/rider/dashboard
        //

        Route::get(
            '/dashboard',
            [RiderDashboardController::class, 'index']
        )->name('dashboard');

        // ====================================================
        // RIDER DELIVERIES
        // ====================================================
        //
        // GET /api/rider/orders
        //
        // Shipment-based.
        //
        // Returns all shipments assigned to the authenticated
        // rider.
        //

        Route::get(
            '/orders',
            [RiderOrderController::class, 'index']
        )->name('orders.index');

        // ====================================================
        // SINGLE RIDER DELIVERY
        // ====================================================
        //
        // GET /api/rider/orders/{shipment}
        //
        // IMPORTANT:
        //
        // {shipment} is the Shipment ID.
        //

        Route::get(
            '/orders/{shipment}',
            [RiderOrderController::class, 'show']
        )->name('orders.show');

        // ====================================================
        // RIDER WAYBILL QR SCANNER
        // ====================================================
        //
        // POST /api/rider/scan
        //
        // The Rider mobile application scans the exact QR code
        // generated by the BiliHub website waybill.
        //
        // Shipment QR payload format:
        //
        // {
        //     "t": "tracking_number",
        //     "q": "qr_token",
        //     "so": seller_order_id
        // }
        //
        // Expected mobile request:
        //
        // {
        //     "qr_payload":
        //     "{\"t\":\"TRACKING\",\"q\":\"TOKEN\",\"so\":123}"
        // }
        //
        // The ScannerController verifies:
        //
        // - authenticated account is a rider
        // - QR belongs to a real shipment
        // - QR token matches
        // - shipment is assigned to the rider
        // - pickup/hub workflow is valid
        //
        // Successful scans are saved to the same Shipment
        // record used by the BiliHub website.
        //

        Route::post(
            '/scan',
            [RiderScannerController::class, 'scan']
        )->name('scan');

        /*
        |--------------------------------------------------------------------------
        | NEXT RIDER MOBILE ENDPOINTS
        |--------------------------------------------------------------------------
        |
        | Do not enable these until their corresponding mobile API
        | implementations are finished.
        |
        | PUT  /api/rider/orders/{shipment}/status
        |
        | POST /api/rider/orders/{shipment}/proof
        |
        | GET  /api/rider/earnings
        |
        | GET  /api/rider/notifications
        |
        */
    });

// ============================================================
// SELLER MOBILE API
// TODO
// ============================================================

// Route::middleware([
//     'auth:sanctum',
//     'role:seller',
// ])
//     ->prefix('seller')
//     ->name('seller.api.')
//     ->group(function () {
//
//         Route::get(
//             '/dashboard',
//             [
//                 App\Http\Controllers\Api\Seller\DashboardController::class,
//                 'index',
//             ]
//         );
//
//         Route::apiResource(
//             'products',
//             App\Http\Controllers\Api\Seller\ProductController::class
//         );
//
//         Route::get(
//             '/orders',
//             [
//                 App\Http\Controllers\Api\Seller\OrderController::class,
//                 'index',
//             ]
//         );
//
//         Route::get(
//             '/orders/{order}',
//             [
//                 App\Http\Controllers\Api\Seller\OrderController::class,
//                 'show',
//             ]
//         );
//
//         Route::put(
//             '/orders/{order}/status',
//             [
//                 App\Http\Controllers\Api\Seller\OrderController::class,
//                 'updateStatus',
//             ]
//         );
//
//         Route::get(
//             '/reports',
//             [
//                 App\Http\Controllers\Api\Seller\ReportController::class,
//                 'index',
//             ]
//         );
//     });

// ============================================================
// LOGISTICS MOBILE API
// TODO
// ============================================================

// Route::middleware([
//     'auth:sanctum',
//     'role:logistic_owner',
// ])
//     ->prefix('logistic')
//     ->name('logistic.api.')
//     ->group(function () {
//
//         Route::get(
//             '/dashboard',
//             [
//                 App\Http\Controllers\Api\Logistic\DashboardController::class,
//                 'index',
//             ]
//         );
//
//         Route::get(
//             '/shipments',
//             [
//                 App\Http\Controllers\Api\Logistic\ShipmentController::class,
//                 'index',
//             ]
//         );
//
//         Route::get(
//             '/shipments/{shipment}',
//             [
//                 App\Http\Controllers\Api\Logistic\ShipmentController::class,
//                 'show',
//             ]
//         );
//
//         Route::post(
//             '/shipments/{shipment}/receive',
//             [
//                 App\Http\Controllers\Api\Logistic\ShipmentController::class,
//                 'receive',
//             ]
//         );
//
//         Route::post(
//             '/shipments/{shipment}/scan',
//             [
//                 App\Http\Controllers\Api\Logistic\ShipmentController::class,
//                 'scan',
//             ]
//         );
//
//         Route::post(
//             '/shipments/{shipment}/sort',
//             [
//                 App\Http\Controllers\Api\Logistic\ShipmentController::class,
//                 'sort',
//             ]
//         );
//
//         Route::post(
//             '/shipments/{shipment}/stage',
//             [
//                 App\Http\Controllers\Api\Logistic\ShipmentController::class,
//                 'stage',
//             ]
//         );
//
//         Route::post(
//             '/shipments/{shipment}/assign',
//             [
//                 App\Http\Controllers\Api\Logistic\ShipmentController::class,
//                 'assignRider',
//             ]
//         );
//
//         Route::get(
//             '/riders',
//             [
//                 App\Http\Controllers\Api\Logistic\RiderController::class,
//                 'index',
//             ]
//         );
//
//         Route::get(
//             '/hubs',
//             [
//                 App\Http\Controllers\Api\Logistic\HubController::class,
//                 'index',
//             ]
//         );
//
//         Route::post(
//             '/hubs',
//             [
//                 App\Http\Controllers\Api\Logistic\HubController::class,
//                 'store',
//             ]
//         );
//
//         Route::put(
//             '/hubs/{hub}',
//             [
//                 App\Http\Controllers\Api\Logistic\HubController::class,
//                 'update',
//             ]
//         );
//
//         Route::delete(
//             '/hubs/{hub}',
//             [
//                 App\Http\Controllers\Api\Logistic\HubController::class,
//                 'destroy',
//             ]
//         );
//
//         Route::get(
//             '/notifications',
//             [
//                 App\Http\Controllers\Api\Logistic\NotificationController::class,
//                 'index',
//             ]
//         );
//     });

// ============================================================
// ADMIN MOBILE API
// TODO
// ============================================================

// Route::middleware([
//     'auth:sanctum',
//     'role:admin',
// ])
//     ->prefix('admin')
//     ->name('admin.api.')
//     ->group(function () {
//
//         Route::get(
//             '/dashboard',
//             [
//                 App\Http\Controllers\Api\Admin\DashboardController::class,
//                 'index',
//             ]
//         );
//
//         Route::get(
//             '/users',
//             [
//                 App\Http\Controllers\Api\Admin\UserController::class,
//                 'index',
//             ]
//         );
//
//         Route::get(
//             '/users/{user}',
//             [
//                 App\Http\Controllers\Api\Admin\UserController::class,
//                 'show',
//             ]
//         );
//
//         Route::put(
//             '/users/{user}/status',
//             [
//                 App\Http\Controllers\Api\Admin\UserController::class,
//                 'updateStatus',
//             ]
//         );
//
//         Route::get(
//             '/products',
//             [
//                 App\Http\Controllers\Api\Admin\ProductController::class,
//                 'index',
//             ]
//         );
//
//         Route::get(
//             '/orders',
//             [
//                 App\Http\Controllers\Api\Admin\OrderController::class,
//                 'index',
//             ]
//         );
//
//         Route::get(
//             '/logistics',
//             [
//                 App\Http\Controllers\Api\Admin\LogisticController::class,
//                 'index',
//             ]
//         );
//
//         Route::get(
//             '/reports',
//             [
//                 App\Http\Controllers\Api\Admin\ReportController::class,
//                 'index',
//             ]
//         );
//     });

require __DIR__ . '/free_cron_routes.php';

Route::post('/internal/cleanup-old-categories', function (Request $request) {
    $expectedToken = env('BILIHUB_CATEGORY_CLEANUP_TOKEN');

    if (!$expectedToken || !hash_equals($expectedToken, (string) $request->header('X-BiliHub-Cleanup-Token'))) {
        abort(403, 'Invalid cleanup token.');
    }

    $oldSlugs = [
        'pet-supplies-pet-food',
        'pet-supplies-pet-accessories',
        'pet-supplies-pet-grooming',
        'pet-supplies-pet-beds',
        'pet-supplies-pet-toys',

        'kids-and-baby-baby-clothing',
        'kids-and-baby-kids-clothing',
        'kids-and-baby-baby-care',
        'kids-and-baby-toys',
        'kids-and-baby-school-supplies',

        'electronics-and-gadgets-mobile-phones',
        'electronics-and-gadgets-laptops-and-computers',
        'electronics-and-gadgets-audio-and-headphones',
        'electronics-and-gadgets-smart-watches',
        'electronics-and-gadgets-accessories',

        'home-and-garden-home-decor',
        'home-and-garden-kitchen-and-dining',
        'home-and-garden-furniture',
        'home-and-garden-bedding',
        'home-and-garden-garden-supplies',

        'womens-apparel-tops',
        'womens-apparel-dresses',
        'womens-apparel-pants-and-jeans',
        'womens-apparel-skirts',
        'womens-apparel-jackets-and-outerwear',

        
        'sports-and-outdoors-outdoor-gear',
        'sports-and-outdoors-sports-accessories',
        'sports-and-outdoors-footwear',
        'sports-and-outdoors-sportswear',

        'mens-apparel-t-shirts',
        'mens-apparel-shirts',
        'mens-apparel-pants-and-jeans',
        'mens-apparel-shorts',
        'mens-apparel-jackets-and-outerwear',

        'health-and-beauty-skin-care',
        'health-and-beauty-hair-care',
        'health-and-beauty-makeup',
        'health-and-beauty-personal-care',
        'health-and-beauty-beauty-tools',
    ];

    $deleted = Category::whereIn('slug', $oldSlugs)
        ->whereDoesntHave('products')
        ->delete();

    $sports = Category::where('slug', 'sports-and-outdoors')->first();

    if ($sports) {
        Category::updateOrCreate(
            ['slug' => 'sports-and-outdoors-fitness-equipment'],
            [
                'name' => 'Fitness Equipment',
                'description' => 'Fitness Equipment',
                'parent_id' => $sports->id,
            ]
        );
    }

    $summary = Category::whereNull('parent_id')
        ->orderBy('name')
        ->get()
        ->map(function ($parent) {
            return [
                'parent' => $parent->name,
                'count' => Category::where('parent_id', $parent->id)->count(),
                'subcategories' => Category::where('parent_id', $parent->id)
                    ->orderBy('name')
                    ->pluck('name')
                    ->values(),
            ];
        });

    return response()->json([
        'success' => true,
        'deleted_old_categories' => $deleted,
        'categories' => $summary,
    ]);
});
