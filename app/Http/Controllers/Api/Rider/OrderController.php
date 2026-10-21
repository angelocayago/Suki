<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Get all shipments assigned to the authenticated rider.
     *
     * GET /api/rider/orders
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

            $shipments = Shipment::query()
                ->where('rider_id', $rider->id)
                ->whereHas('sellerOrder.order')
                ->with([
                    'sellerOrder.order.user',
                    'sellerOrder.seller',
                ])
                ->latest()
                ->get();

            $data = $shipments->map(function (Shipment $shipment) use ($rider) {
                $sellerOrder = $shipment->sellerOrder;
                $order = $sellerOrder?->order;
                $customer = $order?->user;

                return [
                    /*
                    |--------------------------------------------------------------------------
                    | SHIPMENT IDENTIFICATION
                    |--------------------------------------------------------------------------
                    |
                    | IMPORTANT:
                    | The mobile rider delivery workflow is shipment-based.
                    | Therefore "id" is the Shipment ID.
                    |
                    */

                    'id' => $shipment->id,

                    'shipment_id' => $shipment->id,

                    'seller_order_id' => $shipment->seller_order_id,

                    'tracking_number' => $shipment->tracking_number ?? '',

                    /*
                    |--------------------------------------------------------------------------
                    | CUSTOMER
                    |--------------------------------------------------------------------------
                    */

                    'customer_name' => $customer?->name ?? 'Customer',

                    'customer_phone' => $customer?->mobile_number
                        ?? $customer?->phone
                        ?? '',

                    /*
                    |--------------------------------------------------------------------------
                    | DELIVERY
                    |--------------------------------------------------------------------------
                    */

                    'delivery_address' => $shipment->delivery_address ?? '',

                    'status' => $shipment->status ?? '',

                    /*
                    |--------------------------------------------------------------------------
                    | RIDER
                    |--------------------------------------------------------------------------
                    */

                    'rider_id' => (string) $rider->id,

                    'rider_name' => $rider->name ?? 'Rider',

                    /*
                    |--------------------------------------------------------------------------
                    | LOCATION
                    |--------------------------------------------------------------------------
                    */

                    'latitude' => $shipment->latitude !== null
                        ? (float) $shipment->latitude
                        : null,

                    'longitude' => $shipment->longitude !== null
                        ? (float) $shipment->longitude
                        : null,

                    /*
                    |--------------------------------------------------------------------------
                    | DATES
                    |--------------------------------------------------------------------------
                    */

                    'order_date' => $order?->created_at?->toISOString(),

                    'created_at' => $shipment->created_at?->toISOString(),

                    'updated_at' => $shipment->updated_at?->toISOString(),

                    'picked_up_at' => $shipment->picked_up_at?->toISOString(),

                    'delivered_at' => $shipment->delivered_at?->toISOString(),

                    /*
                    |--------------------------------------------------------------------------
                    | EARNINGS
                    |--------------------------------------------------------------------------
                    |
                    | We are NOT inventing rider earnings here.
                    | Earnings will come from the real earnings/payment API.
                    |
                    */

                    'delivery_earnings' => null,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load rider deliveries.',
            ], 500);
        }
    }

    /**
     * Get one shipment assigned to the authenticated rider.
     *
     * GET /api/rider/orders/{shipment}
     */
    public function show(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
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

            if ((int) $shipment->rider_id !== (int) $rider->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This shipment is not assigned to you.',
                ], 403);
            }

            $shipment->load([
                'sellerOrder.order.user',
                'sellerOrder.seller',
            ]);

            $sellerOrder = $shipment->sellerOrder;
            $order = $sellerOrder?->order;
            $customer = $order?->user;

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order information for this shipment was not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,

                'data' => [
                    'id' => $shipment->id,

                    'shipment_id' => $shipment->id,

                    'seller_order_id' => $shipment->seller_order_id,

                    'tracking_number' => $shipment->tracking_number ?? '',

                    'customer_name' => $customer?->name ?? 'Customer',

                    'customer_phone' => $customer?->mobile_number
                        ?? $customer?->phone
                        ?? '',

                    'delivery_address' => $shipment->delivery_address ?? '',

                    'status' => $shipment->status ?? '',

                    'rider_id' => (string) $rider->id,

                    'rider_name' => $rider->name ?? 'Rider',

                    'latitude' => $shipment->latitude !== null
                        ? (float) $shipment->latitude
                        : null,

                    'longitude' => $shipment->longitude !== null
                        ? (float) $shipment->longitude
                        : null,

                    'order_date' => $order->created_at?->toISOString(),

                    'created_at' => $shipment->created_at?->toISOString(),

                    'updated_at' => $shipment->updated_at?->toISOString(),

                    'picked_up_at' => $shipment->picked_up_at?->toISOString(),

                    'delivered_at' => $shipment->delivered_at?->toISOString(),

                    'delivery_earnings' => null,
                ],
            ], 200);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load rider delivery.',
            ], 500);
        }
    }
}