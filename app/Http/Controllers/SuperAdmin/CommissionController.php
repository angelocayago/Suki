<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\SellerOrder;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    /**
     * Display commission records and seller commission rates.
     */
    public function index(Request $request)
    {
        $statuses = [
            'pending',
            'accepted',
            'packed',
            'ready_to_ship',
            'shipped',
            'delivered',
            'completed',
            'cancelled',
        ];


        /*
        |--------------------------------------------------------------------------
        | COMMISSION RECORDS
        |--------------------------------------------------------------------------
        */

        $recordsQuery = SellerOrder::query()
            ->with([
                'order',
                'seller.owner',
            ]);


        if ($request->filled('search')) {
            $search = trim($request->search);

            $recordsQuery->where(function ($query) use ($search) {

                $query
                    ->whereHas('order', function ($orderQuery) use ($search) {

                        $orderQuery->where(
                            'order_number',
                            'like',
                            "%{$search}%"
                        );

                    })
                    ->orWhereHas('seller', function ($sellerQuery) use ($search) {

                        $sellerQuery
                            ->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhereHas(
                                'owner',
                                function ($ownerQuery) use ($search) {

                                    $ownerQuery
                                        ->where(
                                            'name',
                                            'like',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'email',
                                            'like',
                                            "%{$search}%"
                                        );

                                }
                            );

                    });

            });
        }


        if (
            $request->filled('status')
            && in_array($request->status, $statuses, true)
        ) {

            $recordsQuery->where(
                'status',
                $request->status
            );
        }


        $commissionRecords = $recordsQuery
            ->latest()
            ->paginate(
                10,
                ['*'],
                'records_page'
            )
            ->withQueryString();


        /*
        |--------------------------------------------------------------------------
        | ALL-TIME FINANCIAL TOTALS
        |--------------------------------------------------------------------------
        |
        | Cancelled seller orders do not contribute to sales,
        | platform commission, or seller earnings.
        |
        */

        $financialOrders = SellerOrder::query()
            ->where('status', '!=', 'cancelled');


        $totalSalesMinor = (int) (
            clone $financialOrders
        )->sum('subtotal_minor');


        $platformCommissionMinor = (int) (
            clone $financialOrders
        )->sum('commission_minor');


        $sellerNetMinor =
            $totalSalesMinor
            - $platformCommissionMinor;


        /*
        |--------------------------------------------------------------------------
        | MONTHLY TREND DATA
        |--------------------------------------------------------------------------
        |
        | These values come from actual seller_orders.created_at records.
        | Nothing here is hardcoded.
        |
        */

        $currentMonthStart = now()
            ->copy()
            ->startOfMonth();

        $currentMonthEnd = now()
            ->copy()
            ->endOfMonth();


        $previousMonthStart = now()
            ->copy()
            ->subMonthNoOverflow()
            ->startOfMonth();

        $previousMonthEnd = now()
            ->copy()
            ->subMonthNoOverflow()
            ->endOfMonth();


        $currentFinancialOrders = SellerOrder::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween(
                'created_at',
                [
                    $currentMonthStart,
                    $currentMonthEnd,
                ]
            );


        $previousFinancialOrders = SellerOrder::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween(
                'created_at',
                [
                    $previousMonthStart,
                    $previousMonthEnd,
                ]
            );


        $currentSalesMinor = (int) (
            clone $currentFinancialOrders
        )->sum('subtotal_minor');

        $previousSalesMinor = (int) (
            clone $previousFinancialOrders
        )->sum('subtotal_minor');


        $currentCommissionMinor = (int) (
            clone $currentFinancialOrders
        )->sum('commission_minor');

        $previousCommissionMinor = (int) (
            clone $previousFinancialOrders
        )->sum('commission_minor');


        $currentNetMinor =
            $currentSalesMinor
            - $currentCommissionMinor;

        $previousNetMinor =
            $previousSalesMinor
            - $previousCommissionMinor;


        $currentRecords = SellerOrder::query()
            ->whereBetween(
                'created_at',
                [
                    $currentMonthStart,
                    $currentMonthEnd,
                ]
            )
            ->count();


        $previousRecords = SellerOrder::query()
            ->whereBetween(
                'created_at',
                [
                    $previousMonthStart,
                    $previousMonthEnd,
                ]
            )
            ->count();


        $stats = [

            'total_sales_minor' =>
                $totalSalesMinor,

            'platform_commission_minor' =>
                $platformCommissionMinor,

            'seller_net_minor' =>
                $sellerNetMinor,

            'records' =>
                SellerOrder::count(),


            'sales_trend' =>
                $this->calculateTrend(
                    $currentSalesMinor,
                    $previousSalesMinor
                ),

            'commission_trend' =>
                $this->calculateTrend(
                    $currentCommissionMinor,
                    $previousCommissionMinor
                ),

            'net_trend' =>
                $this->calculateTrend(
                    $currentNetMinor,
                    $previousNetMinor
                ),

            'records_trend' =>
                $this->calculateTrend(
                    $currentRecords,
                    $previousRecords
                ),
        ];


        /*
        |--------------------------------------------------------------------------
        | SELLER COMMISSION RATES
        |--------------------------------------------------------------------------
        */

        $sellers = Seller::query()
            ->with('owner')
            ->withCount('orders')
            ->orderBy('name')
            ->paginate(
                8,
                ['*'],
                'sellers_page'
            )
            ->withQueryString();


        return view(
            'superadmin.commission.index',
            compact(
                'commissionRecords',
                'stats',
                'sellers',
                'statuses'
            )
        );
    }


    /**
     * Update a seller's current commission rate.
     */
    public function updateRate(
        Request $request,
        Seller $seller
    ) {
        $validated = $request->validate([
            'commission_rate' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],
        ]);


        $seller->update([

            'commission_bps' =>
                (int) round(
                    ((float) $validated['commission_rate'])
                    * 100
                ),

        ]);


        return redirect()
            ->route('superadmin.commission')
            ->with(
                'success',
                'Commission rate updated successfully.'
            );
    }


    /**
     * Calculate the percentage change from the previous month.
     */
    private function calculateTrend(
        int $current,
        int $previous
    ): ?float {
        if ($previous === 0) {
            return null;
        }

        return round(
            (
                ($current - $previous)
                / $previous
            ) * 100,
            1
        );
    }
}