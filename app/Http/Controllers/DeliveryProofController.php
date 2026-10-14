<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DeliveryProofController extends Controller
{
    public function show(Request $request, Shipment $shipment)
    {
        $shipment->load(['sellerOrder.order', 'sellerOrder.seller', 'provider', 'rider']);
        $user = $request->user();
        $allowed = ($user->hasRole('buyer') && $shipment->sellerOrder->order->buyer_id === $user->id)
            || ($user->hasRole('seller') && $shipment->sellerOrder->seller->user_id === $user->id)
            || ($user->hasRole('logistics') && $shipment->provider?->user_id === $user->id)
            || ($user->hasRole('rider') && ($shipment->rider?->user_id === $user->id || $shipment->delivered_by === $user->id))
            || $user->hasRole('admin') || $user->hasRole('superadmin');
        abort_unless($allowed, 403);
        abort_unless($shipment->proof_path && Storage::disk('delivery_proofs')->exists($shipment->proof_path), 404);
        return Storage::disk('delivery_proofs')->response($shipment->proof_path, 'delivery-proof', [
            'Content-Type' => $shipment->proof_mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
