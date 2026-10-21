<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display product listing for the mobile application.
     *
     * GET /api/products
     */
    public function index(Request $request)
    {
        $query = Product::with([
            'category',
            'images',
            'variations',
            'seller',
        ])
            ->where('status', 'published')
            ->where('compliance_status', 'approved');

        // ============================================================
        // SEARCH
        // ============================================================

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // ============================================================
        // CATEGORY FILTER
        // ============================================================

        if ($request->filled('category_id')) {
            $query->where(
                'category_id',
                $request->category_id
            );
        }

        // ============================================================
        // MIN PRICE
        // ============================================================

        if ($request->filled('min_price')) {
            $query->where(
                'price_minor',
                '>=',
                (int) round(
                    (float) $request->min_price * 100
                )
            );
        }

        // ============================================================
        // MAX PRICE
        // ============================================================

        if ($request->filled('max_price')) {
            $query->where(
                'price_minor',
                '<=',
                (int) round(
                    (float) $request->max_price * 100
                )
            );
        }

        // ============================================================
        // SIZE FILTER
        // ============================================================

        if ($request->filled('size_id')) {
            $query->whereHas(
                'sizes',
                function ($q) use ($request) {
                    $q->where(
                        'sizes.id',
                        $request->size_id
                    );
                }
            );
        }

        // ============================================================
        // SORTING
        // ============================================================

        $sortBy = $request->get(
            'sort_by',
            'created_at'
        );

        $sortOrder = strtolower(
            $request->get(
                'sort_order',
                'desc'
            )
        );

        $allowedSorts = [
            'price',
            'created_at',
            'name',
            'rating',
        ];

        if (!in_array(
            $sortOrder,
            ['asc', 'desc'],
            true
        )) {
            $sortOrder = 'desc';
        }

        if (in_array(
            $sortBy,
            $allowedSorts,
            true
        )) {
            $sortColumn =
                $sortBy === 'price'
                    ? 'price_minor'
                    : $sortBy;

            $query->orderBy(
                $sortColumn,
                $sortOrder
            );
        }

        // ============================================================
        // PAGINATION
        // ============================================================

        $perPage = (int) $request->get(
            'per_page',
            20
        );

        $perPage = max(
            1,
            min($perPage, 100)
        );

        $products = $query->paginate(
            $perPage
        );

        // ============================================================
        // NORMALIZE PRODUCT IMAGE URLS
        // ============================================================
        //
        // ProductImage model exposes:
        //
        // $image->url
        //
        // We also add "image_url" as an alias for compatibility
        // with Flutter screens that may still read image_url.
        //

        $productItems = collect(
            $products->items()
        )->map(function (Product $product) {
            if ($product->relationLoaded('images')) {
                $product->images->each(
                    function ($image) {
                        $image->setAttribute(
                            'image_url',
                            $image->url
                        );
                    }
                );
            }

            return $product;
        })->values();

        return response()->json([
            'data' => $productItems,

            'meta' => [
                'current_page' =>
                    $products->currentPage(),

                'last_page' =>
                    $products->lastPage(),

                'per_page' =>
                    $products->perPage(),

                'total' =>
                    $products->total(),
            ],
        ]);
    }

    /**
     * Display a single product.
     *
     * GET /api/products/{product}
     */
    public function show(Product $product)
    {
        // ============================================================
        // PRODUCT AVAILABILITY CHECK
        // ============================================================

        if (
            $product->status !== 'published' ||
            $product->compliance_status !== 'approved'
        ) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }

        // ============================================================
        // LOAD PRODUCT RELATIONSHIPS
        // ============================================================

        $product->load([
            'category',
            'images',
            'variations',
            'seller:id,user_id,name,slug,logo_path,status',
        ]);

        // ============================================================
        // AVAILABLE SIZES
        // ============================================================

        $sizes = $product
            ->sizes()
            ->wherePivot(
                'stock',
                '>',
                0
            )
            ->select(
                'sizes.id',
                'sizes.name'
            )
            ->get();

        // ============================================================
        // AVAILABLE VARIATIONS
        // ============================================================

        $variations = $product
            ->variations()
            ->where(
                'stock',
                '>',
                0
            )
            ->get()
            ->map(function ($variant) {
                return [
                    'id' =>
                        $variant->id,

                    'name' =>
                        $variant->name,

                    'price' =>
                        $variant->effective_price_minor / 100,

                    'stock' =>
                        $variant->stock,

                    'image_url' =>
                        $variant->image_url,

                    'size' =>
                        $variant->size,
                ];
            })
            ->values();

        // ============================================================
        // PRODUCT IMAGES
        // ============================================================

        $images = $product
            ->images
            ->sortBy(function ($image) {
                return [
                    $image->is_primary ? 0 : 1,
                    $image->sort_order ?? 0,
                    $image->id,
                ];
            })
            ->map(function ($image) {
                $url = $image->url;

                return [
                    'id' =>
                        $image->id,

                    'path' =>
                        $image->path,

                    // Main field used by the mobile API.
                    'url' =>
                        $url,

                    // Compatibility alias for Flutter screens
                    // that still read "image_url".
                    'image_url' =>
                        $url,

                    'alt_text' =>
                        $image->alt_text,

                    'is_primary' =>
                        (bool) $image->is_primary,

                    'sort_order' =>
                        (int) ($image->sort_order ?? 0),
                ];
            })
            ->values();

        // ============================================================
        // PRIMARY PRODUCT IMAGE
        // ============================================================

        $primaryImage = $product
            ->images
            ->firstWhere(
                'is_primary',
                true
            );

        if (!$primaryImage) {
            $primaryImage =
                $product->images->first();
        }

        $primaryImageUrl =
            $primaryImage?->url ?? '';

        // ============================================================
        // RESPONSE
        // ============================================================

        return response()->json([
            'data' => [
                'id' =>
                    $product->id,

                'name' =>
                    $product->name,

                'description' =>
                    $product->description,

                'price' =>
                    $product->price_minor / 100,

                'effective_price' =>
                    $product->effective_price_minor / 100,

                'discount_percent' =>
                    $product->discount_percent,

                'sku' =>
                    $product->sku,

                'stock' =>
                    $product->stock,

                'video_url' =>
                    $product->video_url,

                'secondary_image_url' =>
                    $product->secondary_image_url,

                // ====================================================
                // MAIN IMAGE
                // ====================================================
                //
                // These aliases make the API easier to consume
                // from the existing Flutter buyer screens.
                //

                'image_url' =>
                    $primaryImageUrl,

                'primary_image_url' =>
                    $primaryImageUrl,

                // ====================================================
                // RELATIONSHIPS
                // ====================================================

                'category' =>
                    $product->category,

                'images' =>
                    $images,

                'variations' =>
                    $variations,

                'sizes' =>
                    $sizes,

                'seller' =>
                    $product->seller,

                // ====================================================
                // REVIEWS
                // ====================================================

                'rating' =>
                    $product->rating ?? 0,

                'reviews_count' =>
                    $product->reviews_count ?? 0,

                'is_favorite' =>
                    false,
            ],
        ]);
    }

    /**
     * Product categories.
     *
     * GET /api/products/categories
     */
    public function categories()
    {
        $categories = Category::orderBy(
            'name'
        )->get([
            'id',
            'name',
            'slug',
            'description',
            'image',
            'parent_id',
        ]);

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Product sizes.
     *
     * GET /api/products/sizes
     */
    public function sizes()
    {
        $sizes = Size::orderBy(
            'name'
        )->get([
            'id',
            'name',
            'slug',
        ]);

        return response()->json([
            'data' => $sizes,
        ]);
    }
}