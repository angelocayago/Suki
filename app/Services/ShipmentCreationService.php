<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipmentCreationService
{
    public function createForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->load('sellerOrders');

            foreach ($order->sellerOrders as $sellerOrder) {
                $this->createForSellerOrder($sellerOrder);
            }
        });
    }

    private function createForSellerOrder(SellerOrder $sellerOrder): void
    {
        $lockedSellerOrder = SellerOrder::query()
            ->whereKey($sellerOrder->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (Shipment::query()
            ->where('seller_order_id', $lockedSellerOrder->id)
            ->exists()
        ) {
            return;
        }

        $lockedSellerOrder->load([
            'order',
            'seller.owner',
            'seller.pickupAddress',
        ]);

        $order = $lockedSellerOrder->order;
        $seller = $lockedSellerOrder->seller;
        $owner = $seller?->owner;
        $pickupAddress = $seller?->pickupAddress;

        $hub = HubAssignmentService::findBestHubForSellerOrder(
            $lockedSellerOrder
        );

        $pickup = $pickupAddress
            ? collect([
                $pickupAddress->address_line1,
                $pickupAddress->address_line2,
                $pickupAddress->city,
                $pickupAddress->province,
                $pickupAddress->postal_code,
                $pickupAddress->country,
            ])->filter()->implode(', ')
            : collect([
                $owner?->business_name,
                $owner?->street_address,
                $owner?->barangay,
                $owner?->municipality,
                $owner?->province,
            ])->filter()->implode(', ');

        $address = $order->shipping_address;

        $delivery = is_array($address)
            ? (
                $address['address'] ??
                collect($address)
                ->only([
                    'address_line1',
                    'address_line2',
                    'city',
                    'province',
                    'postal_code',
                    'country',
                ])
                ->filter()
                ->implode(', ')
            )
            : (string) $address;

        Shipment::create([
            'seller_order_id' => $lockedSellerOrder->id,
            'logistic_id' => $hub?->logistic_id,
            'hub_id' => $hub?->id,
            'tracking_number' => 'SPE-' . Str::upper(Str::random(10)),
            'tracking_code' => 'SPE-' . Str::upper(Str::random(12)),
            'status' => 'pending',
            'pickup_address' => $pickup ?: 'Seller address',
            'delivery_address' => $delivery ?: 'Delivery address unavailable',
            'notes' => $hub
                ? 'Auto-assigned hub based on seller pickup location: '
                . $hub->name
                : 'Awaiting logistic assignment',
        ]);
    }
}
