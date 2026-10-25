<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Mobile Rider Dashboard API
     *
     * GET /api/rider/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $rider = $request->user();

            if (!$rider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            if (!$rider->isRider()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account is not a rider account.',
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | ACTIVE RIDER SHIPMENTS
            |--------------------------------------------------------------------------
            |
            | These are shipments currently assigned to the authenticated rider.
            | Finalized / closed shipments are excluded.
            |
            */

            $activeShipmentsQuery = Shipment::query()
                ->where('rider_id', $rider->id)
                ->whereHas('sellerOrder.order')
                ->whereNotIn('status', [
                    'delivered',
                    'failed',
                    'delivery_failed',
                    'cancelled',
                    'failed_return_at_sorting_center',
                ]);

            /*
            |--------------------------------------------------------------------------
            | DASHBOARD COUNTS
            |--------------------------------------------------------------------------
            */

            $assignedCount = (clone $activeShipmentsQuery)->count();

            $completedToday = Shipment::query()
                ->where('rider_id', $rider->id)
                ->whereHas('sellerOrder.order')
                ->where('status', 'delivered')
                ->whereDate('delivered_at', today())
                ->count();

            $pendingCount = Shipment::query()
                ->where('rider_id', $rider->id)
                ->whereHas('sellerOrder.order')
                ->whereIn('status', [
                    'assigned',
                    'staged',
                    'at_sorting_center',
                    'ready_for_pickup',
                ])
                ->count();

            $totalDelivered = Shipment::query()
                ->where('rider_id', $rider->id)
                ->whereHas('sellerOrder.order')
                ->where('status', 'delivered')
                ->count();

            /*
            |--------------------------------------------------------------------------
            | ASSIGNED TASKS
            |--------------------------------------------------------------------------
            |
            | Only a limited number is returned for the Rider dashboard.
            | The complete list is available through:
            |
            | GET /api/rider/orders
            |
            */

            $shipments = (clone $activeShipmentsQuery)
                ->with([
                    'sellerOrder.order.user',
                    'sellerOrder.seller',
                ])
                ->latest()
                ->take(10)
                ->get();

            $tasks = $shipments
                ->map(function (Shipment $shipment) use ($rider) {
                    $sellerOrder = $shipment->sellerOrder;
                    $order = $sellerOrder?->order;
                    $customer = $order?->user;

                    return [
                        /*
                        |--------------------------------------------------------------------------
                        | SHIPMENT IDENTIFICATION
                        |--------------------------------------------------------------------------
                        |
                        | Rider mobile workflow is shipment-based.
                        | Therefore "id" is the Shipment ID.
                        |
                        */

                        'id' => $shipment->id,

                        'shipment_id' => $shipment->id,

                        'seller_order_id' => $shipment->seller_order_id,

                        'tracking_number' =>
                            $shipment->tracking_number ?? '',

                        /*
                        |--------------------------------------------------------------------------
                        | CUSTOMER
                        |--------------------------------------------------------------------------
                        */

                        'customer_name' =>
                            $customer?->name ?? 'Customer',

                        'customer_phone' =>
                            $customer?->mobile_number
                            ?? $customer?->phone
                            ?? '',

                        /*
                        |--------------------------------------------------------------------------
                        | DELIVERY
                        |--------------------------------------------------------------------------
                        */

                        'delivery_address' =>
                            $shipment->delivery_address ?? '',

                        'status' =>
                            $shipment->status ?? '',

                        /*
                        |--------------------------------------------------------------------------
                        | RIDER
                        |--------------------------------------------------------------------------
                        */

                        'rider_id' =>
                            (string) $rider->id,

                        'rider_name' =>
                            $rider->name ?? 'Rider',

                        /*
                        |--------------------------------------------------------------------------
                        | LOCATION
                        |--------------------------------------------------------------------------
                        */

                        'latitude' =>
                            $shipment->latitude !== null
                                ? (float) $shipment->latitude
                                : null,

                        'longitude' =>
                            $shipment->longitude !== null
                                ? (float) $shipment->longitude
                                : null,

                        /*
                        |--------------------------------------------------------------------------
                        | DATES
                        |--------------------------------------------------------------------------
                        */

                        'order_date' =>
                            $order?->created_at?->toISOString(),

                        'picked_up_at' =>
                            $shipment->picked_up_at?->toISOString(),

                        'delivered_at' =>
                            $shipment->delivered_at?->toISOString(),

                        'created_at' =>
                            $shipment->created_at?->toISOString(),

                        'updated_at' =>
                            $shipment->updated_at?->toISOString(),

                        /*
                        |--------------------------------------------------------------------------
                        | EARNINGS
                        |--------------------------------------------------------------------------
                        |
                        | Real earnings are intentionally not calculated here.
                        | They will come from the Rider earnings API.
                        |
                        */

                        'delivery_earnings' => null,
                    ];
                })
                ->values();

            /*
            |--------------------------------------------------------------------------
            | RESPONSE
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'rider' => [
                    'id' => $rider->id,

                    /*
                    |--------------------------------------------------------------------------
                    | Rider is currently stored as a User record
                    |--------------------------------------------------------------------------
                    */

                    'user_id' => $rider->id,

                    'name' => $rider->name,

                    'first_name' => $rider->first_name,

                    'middle_name' => $rider->middle_name,

                    'last_name' => $rider->last_name,

                    'email' => $rider->email,

                    'mobile_number' =>
                        $rider->mobile_number
                        ?? $rider->phone
                        ?? '',

                    'vehicle_type' =>
                        $rider->vehicle_type,

                    'availability_status' =>
                        $rider->availability_status,

                    'logistic_status' =>
                        $rider->logistic_status,

                    'current_load' =>
                        $rider->current_load !== null
                            ? (int) $rider->current_load
                            : 0,

                    'max_capacity' =>
                        $rider->max_capacity !== null
                            ? (int) $rider->max_capacity
                            : null,

                    'latitude' =>
                        $rider->latitude !== null
                            ? (float) $rider->latitude
                            : null,

                    'longitude' =>
                        $rider->longitude !== null
                            ? (float) $rider->longitude
                            : null,

                    'location_updated_at' =>
                        $rider->location_updated_at,
                ],

                'overview' => [
                    /*
                    |--------------------------------------------------------------------------
                    | Flutter Rider Home keys
                    |--------------------------------------------------------------------------
                    |
                    | Keep these keys unchanged because rider_home.dart
                    | already consumes them.
                    |
                    */

                    'today_delivery' => $assignedCount,

                    'completed' => $completedToday,

                    'pending' => $pendingCount,

                    /*
                    |--------------------------------------------------------------------------
                    | Additional real server-side values
                    |--------------------------------------------------------------------------
                    */

                    'active_assigned' => $assignedCount,

                    'total_delivered' => $totalDelivered,
                ],

                'tasks' => $tasks,
            ], 200);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load rider dashboard.',
            ], 500);
        }
    }
}