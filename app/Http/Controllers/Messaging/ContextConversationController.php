<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Services\MessagingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContextConversationController extends Controller
{
    public function productInquiry(
        Request $request,
        Product $product,
        MessagingService $messaging,
    ): RedirectResponse {
        $conversation = $messaging->createProductInquiry($request->user(), $product);

        return redirect()->route('buyer.messages.show', $conversation);
    }

    public function buyerSellerOrder(
        Request $request,
        SellerOrder $sellerOrder,
        MessagingService $messaging,
    ): RedirectResponse {
        $conversation = $messaging->createSellerOrderConversation($request->user(), $sellerOrder);

        return redirect()->route('buyer.messages.show', $conversation);
    }

    public function sellerOrder(
        Request $request,
        SellerOrder $sellerOrder,
        MessagingService $messaging,
    ): RedirectResponse {
        $conversation = $messaging->createSellerOrderConversation($request->user(), $sellerOrder);

        return redirect()->route('seller.messages.show', $conversation);
    }

    public function riderShipment(
        Request $request,
        SellerOrder $sellerOrder,
        MessagingService $messaging,
    ): RedirectResponse {
        $conversation = $messaging->createRiderShipmentConversation(
            $request->user(),
            $sellerOrder->shipment()->firstOrFail(),
        );

        return redirect()->route('rider.messages.show', $conversation);
    }

    public function logisticsShipment(
        Request $request,
        SellerOrder $sellerOrder,
        MessagingService $messaging,
    ): RedirectResponse {
        $conversation = $messaging->createLogisticsShipmentConversation(
            $request->user(),
            $sellerOrder->shipment()->firstOrFail(),
        );

        return redirect()->route('logistics.messages.show', $conversation);
    }

    public function complaint(
        Request $request,
        Complaint $complaint,
        MessagingService $messaging,
    ): RedirectResponse {
        $conversation = $messaging->createComplaintConversation($request->user(), $complaint);

        $route = $request->user()->isAdmin()
            ? 'superadmin.messages.show'
            : match (strtoupper((string) $complaint->complainant_role)) {
                'BUYER' => 'buyer.messages.show',
                'SELLER' => 'seller.messages.show',
                'RIDER' => 'rider.messages.show',
                'LOGISTICS' => 'logistics.messages.show',
                default => abort(403),
            };

        return redirect()->route($route, $conversation);
    }
}
