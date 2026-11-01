<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Support\SellerSalesFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(Request $request, SellerSalesFilters $sales): View
    {
        return view('seller.sales', $sales->listing($this->seller($request), $request));
    }

    public function products(Request $request, SellerSalesFilters $sales): JsonResponse
    {
        $criteria = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return response()->json($sales->products($this->seller($request), trim($criteria['q'] ?? '')));
    }

    private function seller(Request $request): Seller
    {
        $seller = $request->user()->sellers()->where('status', 'approved')->first();
        abort_unless($seller, 403, 'No approved seller profile is available.');

        return $seller;
    }
}
