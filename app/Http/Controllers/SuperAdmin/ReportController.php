<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Seller;
use App\Models\SellerOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {

        /*
        |--------------------------------------------------------------------------
        | DATE FILTER
        |--------------------------------------------------------------------------
        */

        $from = $request->from
            ? now()->parse($request->from)->startOfDay()
            : null;


        $to = $request->to
            ? now()->parse($request->to)->endOfDay()
            : null;



        /*
        |--------------------------------------------------------------------------
        | ORDERS QUERY
        |--------------------------------------------------------------------------
        */

        $ordersQuery = Order::query()
            ->whereNotIn(
                'status',
                [
                    'CANCELLED',
                    'RETURNED'
                ]
            );


        if ($from && $to) {

            $ordersQuery->whereBetween(
                'created_at',
                [
                    $from,
                    $to
                ]
            );

        }



        $totalSales =
            (float)
            (clone $ordersQuery)
            ->sum('total_amount');


        $totalOrders =
            (clone $ordersQuery)
            ->count();



        $completedOrders =
            (clone $ordersQuery)
            ->whereIn(
                'status',
                [
                    'DELIVERED',
                    'COMPLETED'
                ]
            )
            ->count();



        $averageOrderValue =
            $totalOrders > 0
            ? $totalSales / $totalOrders
            : 0;





        /*
        |--------------------------------------------------------------------------
        | DAILY SALES PERFORMANCE
        |--------------------------------------------------------------------------
        */

        $salesPerformance =
            (clone $ordersQuery)
            ->select(
                DB::raw(
                    'DATE(created_at) as date'
                ),
                DB::raw(
                    'COUNT(*) as orders'
                ),
                DB::raw(
                    'SUM(total_amount) as sales'
                )
            )
            ->groupBy(
                DB::raw(
                    'DATE(created_at)'
                )
            )
            ->orderBy(
                'date',
                'desc'
            )
            ->get();







        /*
        |--------------------------------------------------------------------------
        | COMMISSION SUMMARY
        |--------------------------------------------------------------------------
        */

        $sellerOrdersQuery =
            SellerOrder::query()
            ->where(
                'status',
                '!=',
                'cancelled'
            );


        if ($from && $to) {

            $sellerOrdersQuery->whereBetween(
                'created_at',
                [
                    $from,
                    $to
                ]
            );

        }



        $sellerSalesMinor =
            (int)
            (clone $sellerOrdersQuery)
            ->sum(
                'subtotal_minor'
            );


        $commissionMinor =
            (int)
            (clone $sellerOrdersQuery)
            ->sum(
                'commission_minor'
            );


        $sellerNetMinor =
            $sellerSalesMinor
            -
            $commissionMinor;







        /*
|--------------------------------------------------------------------------
| SELLER PERFORMANCE
|--------------------------------------------------------------------------
*/

$sellerPerformance =
    Seller::query()
    ->with([
        'owner'
    ])
    ->withCount('orders')
    ->withSum(
        [
            'orders as sales_total_minor' => function ($query) {

                $query->where(
                    'status',
                    '!=',
                    'cancelled'
                );

            }
        ],
        'subtotal_minor'
    )
    ->withSum(
        [
            'orders as commission_total_minor' => function ($query) {

                $query->where(
                    'status',
                    '!=',
                    'cancelled'
                );

            }
        ],
        'commission_minor'
    )
    ->orderBy('name')
    ->get();






        return view(
            'superadmin.reports.index',
            compact(
                'totalSales',
                'totalOrders',
                'completedOrders',
                'averageOrderValue',
                'sellerSalesMinor',
                'commissionMinor',
                'sellerNetMinor',
                'salesPerformance',
                'sellerPerformance',
                'from',
                'to'
            )
        );

    }
}