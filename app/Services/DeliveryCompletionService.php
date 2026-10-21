<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeliveryCompletionService
{
    public function deliver(SellerOrder $sellerOrder, User $actor, array $data): SellerOrder
    {
        $path = null;
        try {
            return DB::transaction(function () use ($sellerOrder, $actor, $data, &$path) {
                Order::whereKey($sellerOrder->order_id)->lockForUpdate()->firstOrFail();
                $locked = SellerOrder::with('order')->lockForUpdate()->findOrFail($sellerOrder->id);
                $shipment = Shipment::with('rider.logisticsProvider')->where('seller_order_id', $locked->id)->lockForUpdate()->firstOrFail();
                abort_unless($actor->status === 'active' && ! $actor->is_suspended && $actor->hasRole('rider') && $shipment->rider?->is_active && $shipment->rider->user_id === $actor->id, 403);
                abort_unless($shipment->rider->logisticsProvider?->status === 'approved', 403);
                if (in_array($locked->workflow_status, [OrderStatus::Delivered, OrderStatus::Completed], true) && $shipment->proof_path) {
                    return $locked;
                }
                if ($locked->order->status === 'CANCELLED' || $locked->workflow_status !== OrderStatus::OutForDelivery || $shipment->workflow_status !== OrderStatus::OutForDelivery) {
                    throw ValidationException::withMessages(['order' => 'Only an out-for-delivery parcel can be delivered.']);
                }
                $validated = Validator::make($data, [
                    'proof_photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:5120'],
                    'received_by' => ['required', 'string', 'max:150'],
                    'delivery_note' => ['nullable', 'string', 'max:1000'],
                    'cod_collected' => $locked->order->payment_method === 'cod' ? ['required', 'accepted'] : ['nullable'],
                ])->validate();
                /** @var UploadedFile $photo */
                $photo = $validated['proof_photo'];
                $path = $photo->store((string) $shipment->id, 'delivery_proofs');
                $shipment->update([
                    'proof_path' => $path, 'proof_mime' => $photo->getMimeType(),
                    'proof_size' => $photo->getSize(), 'proof_checksum' => hash_file('sha256', $photo->getRealPath()),
                    'delivered_by' => $actor->id, 'proof_delivered_at' => now(),
                    'received_by' => trim($validated['received_by']), 'delivery_note' => $validated['delivery_note'] ?? null,
                    'cod_collected' => $locked->order->payment_method === 'cod',
                    'cod_collected_minor' => $locked->order->payment_method === 'cod' ? $shipment->cod_amount_minor : null,
                    'cod_collected_at' => $locked->order->payment_method === 'cod' ? now() : null,
                ]);
                if ($shipment->cod_collected) {
                    $shipment->events()->create(['status' => 'COD_COLLECTED', 'attempt' => 0, 'user_id' => $actor->id, 'note' => 'Collected '.$shipment->cod_collected_minor.' minor units.', 'occurred_at' => now()]);
                    app(CodRemittanceService::class)->createLiability($shipment->fresh(['rider']));
                }
                $result = app(OrderWorkflowService::class)->transition($locked, $actor, OrderStatus::Delivered);
                app(RiderShippingFeeService::class)->creditDelivered($shipment->fresh());
                $shipment->events()->where('status', 'DELIVERED')->where('attempt', 0)->update([
                    'photo_path' => $path,
                    'note' => 'Received by '.$shipment->received_by.($shipment->delivery_note ? '. '.$shipment->delivery_note : ''),
                ]);
                if ($locked->order->payment_method === 'cod' && ! Shipment::whereHas('sellerOrder', fn ($q) => $q->where('order_id', $locked->order_id))->where('cod_collected', false)->exists()) {
                    $locked->order->update(['payment_status' => 'PAID']);
                }
                return $result;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('delivery_proofs')->delete($path);
            }
            throw $e;
        }
    }
}
