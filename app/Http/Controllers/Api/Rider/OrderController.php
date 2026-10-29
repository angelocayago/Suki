<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

            $data = $shipments
                ->map(function (Shipment $shipment) use ($rider) {
                    return $this->formatShipment(
                        shipment: $shipment,
                        rider: $rider,
                    );
                })
                ->values();

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

            if (!$shipment->sellerOrder?->order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order information for this shipment was not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatShipment(
                    shipment: $shipment,
                    rider: $rider,
                ),
            ], 200);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load rider delivery.',
            ], 500);
        }
    }

    /**
     * Upload proof of delivery for a shipment assigned
     * to the authenticated rider.
     *
     * POST /api/rider/orders/{shipment}/proof
     */
    public function uploadProof(
        Request $request,
        Shipment $shipment
    ): JsonResponse {
        try {
            $rider = $request->user();

            /*
            |--------------------------------------------------------------------------
            | AUTHENTICATION
            |--------------------------------------------------------------------------
            */

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
            | SHIPMENT OWNERSHIP
            |--------------------------------------------------------------------------
            */

            if ((int) $shipment->rider_id !== (int) $rider->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This shipment is not assigned to you.',
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDATION
            |--------------------------------------------------------------------------
            |
            | This follows the existing BiliHub web Rider proof contract.
            |
            | proof_of_delivery:
            |   Actual image upload.
            |
            | delivery_signature:
            |   String value used by the existing web implementation.
            |
            */

            $validated = $request->validate([
                'proof_of_delivery' => [
                    'required',
                    'image',
                    'mimes:jpg,jpeg,png',
                    'max:5120',
                ],

                'delivery_signature' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'delivered_to' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'delivery_notes' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | UPLOAD PROOF
            |--------------------------------------------------------------------------
            |
            | Keep the same storage disk and folder used by the
            | existing BiliHub Rider web workflow.
            |
            */

            $proofPath = $request
                ->file('proof_of_delivery')
                ->store(
                    'proof-of-delivery',
                    'supabase_private'
                );

            if (!$proofPath) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proof of delivery upload failed.',
                ], 500);
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE SHIPMENT PROOF
            |--------------------------------------------------------------------------
            */

            $shipment->proof_of_delivery = $proofPath;

            if (array_key_exists('delivery_signature', $validated)) {
                $shipment->delivery_signature =
                    $validated['delivery_signature'];
            }

            if (array_key_exists('delivered_to', $validated)) {
                $shipment->delivered_to =
                    $validated['delivered_to'];
            }

            if (array_key_exists('delivery_notes', $validated)) {
                $shipment->notes =
                    $validated['delivery_notes'];
            }

            $shipment->save();

            $shipment->refresh();

            $shipment->load([
                'sellerOrder.order.user',
                'sellerOrder.seller',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Delivery proof uploaded successfully.',
                'data' => $this->formatShipment(
                    shipment: $shipment,
                    rider: $rider,
                ),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to upload delivery proof.',
            ], 500);
        }
    }

    /**
     * Update the delivery status of a shipment assigned
     * to the authenticated rider.
     *
     * PUT /api/rider/orders/{shipment}/status
     */
    public function updateStatus(
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

            $validated = $request->validate([
                'status' => [
                    'required',
                    'string',
                    'in:in_transit,out_for_delivery,delivered,failed',
                ],
            ]);

            $newStatus = $validated['status'];

            /*
            |--------------------------------------------------------------------------
            | ALREADY DELIVERED
            |--------------------------------------------------------------------------
            */

            if ($shipment->status === 'delivered') {
                return response()->json([
                    'success' => false,
                    'message' => 'This shipment has already been delivered.',
                ], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | DELIVERY PROOF REQUIRED
            |--------------------------------------------------------------------------
            |
            | Rider cannot mark the shipment Delivered without
            | uploading proof of delivery first.
            |
            */

            if (
                $newStatus === 'delivered'
                && empty($shipment->proof_of_delivery)
            ) {
                return response()->json([
                    'success' => false,
                    'message' =>
                        'Upload proof of delivery before marking this shipment as delivered.',
                    'requires_proof' => true,
                ], 422);
            }

            DB::beginTransaction();

            try {
                /*
                |--------------------------------------------------------------------------
                | UPDATE SHIPMENT
                |--------------------------------------------------------------------------
                */

                $shipment->status = $newStatus;

                if ($newStatus === 'delivered') {
                    $shipment->delivered_at = now();
                }

                if ($newStatus !== 'delivered') {
                    $shipment->delivered_at = null;
                }

                $shipment->save();

                /*
                |--------------------------------------------------------------------------
                | SYNC SELLER ORDER / PARENT ORDER
                |--------------------------------------------------------------------------
                |
                | One buyer Order may contain multiple SellerOrders.
                | Each SellerOrder can have its own Shipment.
                |
                | Delivering one shipment must NOT automatically
                | mark the entire buyer Order as delivered.
                |
                */

                if ($newStatus === 'delivered') {
                    $sellerOrder = $shipment->sellerOrder;

                    if ($sellerOrder) {
                        $sellerOrder->status = 'delivered';
                        $sellerOrder->save();

                        $order = $sellerOrder->order;

                        if ($order) {
                            $order->load([
                                'sellerOrders.shipment',
                            ]);

                            $sellerOrders = $order->sellerOrders;

                            $allDelivered =
                                $sellerOrders->isNotEmpty()
                                && $sellerOrders->every(
                                    function ($item) {
                                        return $item->shipment
                                            && $item->shipment->status === 'delivered';
                                    }
                                );

                            /*
                            |--------------------------------------------------------------------------
                            | ALL SELLER SHIPMENTS DELIVERED
                            |--------------------------------------------------------------------------
                            */

                            if ($allDelivered) {
                                $order->status = 'delivered';
                                $order->delivery_status = 'delivered';
                                $order->delivered_at = now();
                                $order->save();
                            } else {
                                /*
                                |--------------------------------------------------------------------------
                                | PARTIAL DELIVERY
                                |--------------------------------------------------------------------------
                                |
                                | At least one seller shipment has been delivered
                                | but other seller shipments are still pending.
                                |
                                */

                                $hasDelivered =
                                    $sellerOrders->contains(
                                        function ($item) {
                                            return $item->shipment
                                                && $item->shipment->status === 'delivered';
                                        }
                                    );

                                if ($hasDelivered) {
                                    $order->delivery_status =
                                        'partially_delivered';

                                    $order->save();
                                }
                            }
                        }
                    }
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();

                throw $e;
            }

            $shipment->refresh();

            $shipment->load([
                'sellerOrder.order.user',
                'sellerOrder.seller',
            ]);

            return response()->json([
                'success' => true,

                'message' => match ($newStatus) {
                    'in_transit' =>
                        'Shipment marked as in transit.',

                    'out_for_delivery' =>
                        'Shipment marked as out for delivery.',

                    'delivered' =>
                        'Shipment delivered successfully.',

                    'failed' =>
                        'Delivery marked as failed.',

                    default =>
                        'Shipment status updated successfully.',
                },

                'data' => $this->formatShipment(
                    shipment: $shipment,
                    rider: $rider,
                ),
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update shipment status.',
            ], 500);
        }
    }

    /**
     * Format shipment data for the Rider mobile application.
     *
     * IMPORTANT:
     * "id" is the Shipment ID.
     */
    private function formatShipment(
        Shipment $shipment,
        $rider
    ): array {
        $sellerOrder = $shipment->sellerOrder;

        $order = $sellerOrder?->order;

        $customer = $order?->user;

        return [
            /*
            |--------------------------------------------------------------------------
            | SHIPMENT IDENTIFICATION
            |--------------------------------------------------------------------------
            */

            'id' => $shipment->id,

            'shipment_id' => $shipment->id,

            'seller_order_id' =>
                $shipment->seller_order_id,

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
            | PROOF OF DELIVERY
            |--------------------------------------------------------------------------
            */

            'has_proof_of_delivery' =>
                !empty($shipment->proof_of_delivery),

            'proof_of_delivery' =>
                $shipment->proof_of_delivery,

            'delivery_signature' =>
                $shipment->delivery_signature,

            'delivered_to' =>
                $shipment->delivered_to,

            'delivery_notes' =>
                $shipment->notes,

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

            'created_at' =>
                $shipment->created_at?->toISOString(),

            'updated_at' =>
                $shipment->updated_at?->toISOString(),

            'picked_up_at' =>
                $shipment->picked_up_at?->toISOString(),

            'delivered_at' =>
                $shipment->delivered_at?->toISOString(),

            /*
            |--------------------------------------------------------------------------
            | EARNINGS
            |--------------------------------------------------------------------------
            |
            | Rider earnings are not calculated here.
            | They come from the dedicated earnings API.
            |
            */

            'delivery_earnings' => null,
        ];
    }
}