<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\ProductCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Versioned entry point for the established Buyer catalogue and checkout API.
 *
 * This deliberately delegates to the canonical controller and checkout
 * service. In particular, checkout continues to accept COD only.
 */
class BuyerController extends \App\Http\Controllers\Api\BuyerController
{
    /** V1 permits bounded catalog pages without changing legacy API pagination. */
    public function products(Request $request, ProductCatalogService $catalog): JsonResponse
    {
        $filters = $this->productFilters($request, 100);

        return response()->json(['success' => true, 'data' => $catalog->marketplacePage(
            $filters,
            (int) ($filters['per_page'] ?? 24),
            100,
        )]);
    }
}
