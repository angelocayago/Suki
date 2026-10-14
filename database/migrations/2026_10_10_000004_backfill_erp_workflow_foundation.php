<?php

use App\Models\Seller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sellerStatusMap = [
            'pending' => 'PLACED',
            'accepted' => 'CONFIRMED',
            'packed' => 'PREPARING',
            'ready_to_ship' => 'READY_FOR_PICKUP',
            'shipped' => 'OUT_FOR_DELIVERY',
            'delivered' => 'DELIVERED',
            'completed' => 'COMPLETED',
            'cancelled' => 'CANCELLED',
        ];

        DB::table('seller_orders')
            ->orderBy('id')
            ->chunkById(100, function ($sellerOrders) use ($sellerStatusMap) {
                foreach ($sellerOrders as $sellerOrder) {
                    $workflowStatus = $sellerStatusMap[strtolower($sellerOrder->status)]
                        ?? strtoupper($sellerOrder->status);

                    DB::table('seller_orders')
                        ->where('id', $sellerOrder->id)
                        ->update([
                            'workflow_status' => $workflowStatus,
                            'status_changed_at' => $sellerOrder->updated_at,
                        ]);
                }
            });

        $shipmentStatusMap = [
            'unassigned' => 'READY_FOR_PICKUP',
            'assigned' => 'ASSIGNED_TO_RIDER',
            'picked_up' => 'PICKED_UP',
            'in_transit' => 'AT_SORTING_CENTER',
            'out_for_delivery' => 'OUT_FOR_DELIVERY',
            'delivered' => 'DELIVERED',
            'failed' => 'DELIVERY_FAILED',
            'returned' => 'RETURNED',
        ];

        DB::table('shipments')
            ->orderBy('id')
            ->chunkById(100, function ($shipments) use ($shipmentStatusMap) {
                foreach ($shipments as $shipment) {
                    DB::table('shipments')
                        ->where('id', $shipment->id)
                        ->update([
                            'workflow_status' => $shipmentStatusMap[strtolower($shipment->status)]
                                ?? strtoupper($shipment->status),
                            'status_changed_at' => $shipment->updated_at,
                        ]);
                }
            });

        if (! Schema::hasTable('sellers')) {
            return;
        }

        DB::table('orders')
            ->whereNotNull('seller_id')
            ->orderBy('id')
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    $seller = Seller::query()
                        ->where('user_id', $order->seller_id)
                        ->first();

                    if (! $seller) {
                        continue;
                    }

                    $existingSellerOrderId = DB::table('seller_orders')
                        ->where('order_id', $order->id)
                        ->where('seller_id', $seller->id)
                        ->value('id');

                    $sellerOrderId = $existingSellerOrderId ?: DB::table('seller_orders')->insertGetId([
                        'order_id' => $order->id,
                        'seller_id' => $seller->id,
                        'subtotal_minor' => (int) round(((float) $order->subtotal) * 100),
                        'shipping_fee_minor' => (int) round(((float) $order->shipping_fee) * 100),
                        'commission_minor' => 0,
                        'status' => 'pending',
                        'workflow_status' => strtoupper($order->status),
                        'status_changed_at' => $order->updated_at,
                        'delivered_at' => $order->delivered_at,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ]);

                    DB::table('order_items')
                        ->where('order_id', $order->id)
                        ->where('seller_id', $order->seller_id)
                        ->whereNull('seller_order_id')
                        ->update(['seller_order_id' => $sellerOrderId]);
                }
            });
    }

    public function down(): void
    {
        // Data backfills are intentionally not reversed.
    }
};
