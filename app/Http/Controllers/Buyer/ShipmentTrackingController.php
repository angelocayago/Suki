<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\ShipmentSmsNotification;
use App\Support\BuyerOrderTracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;

class ShipmentTrackingController extends Controller
{
    public function show(Request $request, Order $order, SellerOrder $sellerOrder): View
    {
        abort_unless($order->buyer_id === $request->user()->id && $sellerOrder->order_id === $order->id, 404);
        $order->load(['sellerOrders.seller', 'sellerOrders.shipment.events' => fn ($query) => $query->orderBy('id')]);
        $sellerOrder = $order->sellerOrders->firstWhere('id', $sellerOrder->id);

        $smsNotice = Schema::hasTable('shipment_sms_notifications') && $sellerOrder?->shipment
            ? ShipmentSmsNotification::where('shipment_id', $sellerOrder->shipment->id)->whereIn('status', ['PENDING', 'PROCESSING', 'SENT'])->latest()->first()
            : null;
        return view('buyer.tracking', ['order' => $order, 'sellerOrder' => $sellerOrder,
            'tracking' => app(BuyerOrderTracking::class)->present($order), 'smsNotice' => $smsNotice]);
    }

    public function latest(Request $request, Order $order, SellerOrder $sellerOrder): JsonResponse
    {
        abort_unless($order->buyer_id === $request->user()->id && $sellerOrder->order_id === $order->id, 404);
        $shipment = $sellerOrder->shipment;
        abort_unless($shipment, 404);
        $order->load(['sellerOrders.seller', 'sellerOrders.shipment.events' => fn ($query) => $query->orderBy('id')]);
        $status = BuyerOrderTracking::status($shipment);
        $cancelled = BuyerOrderTracking::status($order) === 'CANCELLED'
            || BuyerOrderTracking::status($sellerOrder) === 'CANCELLED' || $status === 'CANCELLED';

        return response()->json([
            'tracking_code' => $shipment->tracking_code,
            'status' => $status,
            'latitude' => $shipment->current_latitude === null ? null : (float) $shipment->current_latitude,
            'longitude' => $shipment->current_longitude === null ? null : (float) $shipment->current_longitude,
            'updated_at' => $shipment->location_updated_at?->toIso8601String(),
            'trackable' => !$cancelled && in_array($status, [OrderStatus::AssignedToRider->value, OrderStatus::OutForDelivery->value], true),
            'status_label' => BuyerOrderTracking::label($cancelled ? 'CANCELLED' : $status),
            'terminal' => $cancelled || in_array($status, ['DELIVERED', 'COMPLETED', 'RTS_COMPLETED'], true),
            'tracking_html' => view('buyer.partials.order-tracking', ['tracking' => app(BuyerOrderTracking::class)->present($order)])->render(),
        ]);
    }
}
