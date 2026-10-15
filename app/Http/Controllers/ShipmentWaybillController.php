<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShipmentWaybillController extends Controller
{
    public function show(Request $request, Shipment $shipment): View
    {
        $shipment->load(['sellerOrder.order.buyer', 'sellerOrder.seller', 'sellerOrder.items', 'provider', 'rider.user', 'pickupRider.user']);
        $user = $request->user();
        $sellerOrder = $shipment->sellerOrder;
        $allowed = $sellerOrder->order->buyer_id === $user->id
            || $sellerOrder->seller->user_id === $user->id
            || $shipment->provider?->user_id === $user->id
            || $shipment->rider?->user_id === $user->id
            || $shipment->pickupRider?->user_id === $user->id
            || $user->hasRole('admin')
            || $user->hasRole('superadmin');
        abort_unless($allowed, 403);

        // The tracking code is intentionally the only encoded payload.  It
        // contains no address, buyer, payment, or account information.
        $trackingCode = $shipment->tracking_code;
        $qrDataUri = (new SvgWriter())->write(new QrCode(data: $trackingCode, size: 150))->getDataUri();
        $barcodeSvg = (new BarcodeGeneratorSVG())->getBarcode($trackingCode, BarcodeGeneratorSVG::TYPE_CODE_128, 2, 60);

        return view('shipments.waybill', compact('shipment', 'qrDataUri', 'barcodeSvg'));
    }
}
