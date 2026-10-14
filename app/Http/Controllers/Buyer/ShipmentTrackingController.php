<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SellerOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShipmentTrackingController extends Controller
{
    public function show(Request $request, Order $order, SellerOrder $sellerOrder): View
    {
        abort_unless($order->buyer_id === $request->user()->id && $sellerOrder->order_id === $order->id, 404);
        $sellerOrder->load(['shipment.rider.user', 'seller']);

        return view('buyer.tracking', ['order' => $order, 'sellerOrder' => $sellerOrder]);
    }

    public function latest(Request $request, Order $order, SellerOrder $sellerOrder): JsonResponse
    {
        abort_unless($order->buyer_id === $request->user()->id && $sellerOrder->order_id === $order->id, 404);
        $shipment = $sellerOrder->shipment;
        abort_unless($shipment, 404);

        return response()->json([
            'tracking_code' => $shipment->tracking_code,
            'status' => $shipment->workflow_status?->value,
            'latitude' => $shipment->current_latitude === null ? null : (float) $shipment->current_latitude,
            'longitude' => $shipment->current_longitude === null ? null : (float) $shipment->current_longitude,
            'updated_at' => $shipment->location_updated_at?->toIso8601String(),
            'trackable' => in_array($shipment->workflow_status, [OrderStatus::AssignedToRider, OrderStatus::OutForDelivery], true),
        ]);
    }
}
