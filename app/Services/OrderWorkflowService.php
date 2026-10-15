<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\DeliveryEvent;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderWorkflowService
{
    /**
     * Assign (or safely reassign) the rider responsible for collecting a
     * ready parcel.  This deliberately does not advance the ERP status: the
     * assigned rider must still accept and collect it before PICKED_UP.
     */
    public function assignPickupRider(SellerOrder $sellerOrder, LogisticsProvider $provider, User $actor, Rider $rider): Shipment
    {
        return DB::transaction(function () use ($sellerOrder, $provider, $actor, $rider) {
            Order::query()->whereKey($sellerOrder->order_id)->lockForUpdate()->firstOrFail();
            $lockedOrder = SellerOrder::query()
                ->with('order')
                ->lockForUpdate()
                ->findOrFail($sellerOrder->id);

            $shipment = Shipment::query()
                ->with(['pickupRider.user', 'provider'])
                ->where('seller_order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();
            $status = $lockedOrder->workflow_status instanceof OrderStatus
                ? $lockedOrder->workflow_status
                : OrderStatus::tryFrom(strtoupper((string) $lockedOrder->workflow_status));

            if ($lockedOrder->order->status === 'CANCELLED' || ! $shipment || $status !== OrderStatus::ReadyForPickup) {
                throw ValidationException::withMessages(['order' => 'Only an active ready-for-pickup parcel may be assigned.']);
            }

            if (! $actor->hasRole('logistics') || $provider->user_id !== $actor->id || $provider->status !== 'approved') {
                throw new AuthorizationException('Only the approved logistics provider may assign pickup riders.');
            }

            if (
                ($shipment->logistics_provider_id && $shipment->logistics_provider_id !== $provider->id)
                || ($lockedOrder->logistics_provider_id && $lockedOrder->logistics_provider_id !== $provider->id)
            ) {
                throw new AuthorizationException('This parcel belongs to another logistics provider.');
            }

            $rider->loadMissing(['user', 'logisticsProvider']);
            if (
                ! $rider->is_active || ! $rider->approved_at || ! $rider->user || ! $rider->user->hasRole('rider')
                || $rider->user->status !== 'active' || $rider->user->is_suspended
                || $rider->logistics_provider_id !== $provider->id || $rider->logisticsProvider?->status !== 'approved'
            ) {
                throw ValidationException::withMessages(['pickup_rider_id' => 'Select an active rider from your approved logistics provider.']);
            }

            if ($shipment->pickup_accepted_at) {
                throw ValidationException::withMessages(['order' => 'The pickup rider cannot be changed after accepting this parcel.']);
            }

            $previousRider = $shipment->pickupRider;
            if ($previousRider?->id === $rider->id) {
                return $shipment->fresh(['pickupRider.user']);
            }

            $shipment->update([
                'logistics_provider_id' => $provider->id,
                'pickup_rider_id' => $rider->id,
            ]);
            $lockedOrder->update(['logistics_provider_id' => $provider->id]);
            DeliveryEvent::create([
                'shipment_id' => $shipment->id,
                'status' => $previousRider ? 'PICKUP_RIDER_REASSIGNED' : 'PICKUP_RIDER_ASSIGNED',
                'attempt' => 0,
                'user_id' => $actor->id,
                'note' => $previousRider
                    ? 'Pickup rider changed from '.$previousRider->user?->full_name.' to '.$rider->user->full_name.'.'
                    : 'Pickup rider assigned to '.$rider->user->full_name.'.',
                'occurred_at' => now(),
            ]);

            return $shipment->fresh(['pickupRider.user', 'events']);
        });
    }

    /** The existing pickup acceptance action is the lightweight rider acknowledgement. */
    public function declinePickup(SellerOrder $sellerOrder, Rider $rider): Shipment
    {
        return DB::transaction(function () use ($sellerOrder, $rider) {
            Order::query()->whereKey($sellerOrder->order_id)->lockForUpdate()->firstOrFail();
            $lockedOrder = SellerOrder::query()
                ->with('order')
                ->lockForUpdate()
                ->findOrFail($sellerOrder->id);
            $shipment = Shipment::query()
                ->with('pickupRider.user')
                ->where('seller_order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            if (
                ! $shipment || $lockedOrder->order->status === 'CANCELLED'
                || $lockedOrder->workflow_status !== OrderStatus::ReadyForPickup
                || $shipment->pickup_rider_id !== $rider->id || $shipment->pickup_accepted_at
            ) {
                throw new AuthorizationException('Only the currently assigned pickup rider may decline before accepting.');
            }

            $rider->loadMissing('user');
            if (! $rider->is_active || ! $rider->user || $rider->user->status !== 'active' || $rider->user->is_suspended) {
                throw new AuthorizationException('Only an active rider may decline a pickup.');
            }

            $shipment->update(['pickup_rider_id' => null]);
            DeliveryEvent::create([
                'shipment_id' => $shipment->id,
                'status' => 'PICKUP_DECLINED',
                'attempt' => 0,
                'user_id' => $rider->user_id,
                'note' => 'Pickup declined by '.$rider->user->full_name.'.',
                'occurred_at' => now(),
            ]);

            return $shipment->fresh();
        });
    }

    public function transition(
        SellerOrder $sellerOrder,
        ?User $actor,
        OrderStatus|string $requestedStatus,
        array $context = []
    ): SellerOrder {
        $nextStatus = is_string($requestedStatus)
            ? OrderStatus::tryFrom(strtoupper($requestedStatus))
            : $requestedStatus;

        if (! $nextStatus) {
            throw ValidationException::withMessages([
                'status' => 'The requested ERP order status is invalid.',
            ]);
        }

        return DB::transaction(function () use ($sellerOrder, $actor, $nextStatus, $context) {
            Order::whereKey($sellerOrder->order_id)->lockForUpdate()->firstOrFail();
            $lockedOrder = SellerOrder::query()
                ->with(['order', 'seller', 'shipment'])
                ->lockForUpdate()
                ->findOrFail($sellerOrder->id);

            if (strtoupper((string) $lockedOrder->order->status) === 'CANCELLED') {
                throw ValidationException::withMessages([
                    'order' => 'A cancelled order cannot continue through fulfillment.',
                ]);
            }

            $currentStatus = $lockedOrder->workflow_status instanceof OrderStatus
                ? $lockedOrder->workflow_status
                : OrderStatus::tryFrom(strtoupper($lockedOrder->workflow_status));

            if (! $currentStatus || $currentStatus->next() !== $nextStatus) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'Invalid ERP transition from %s to %s.',
                        $currentStatus?->value ?? (string) $lockedOrder->getRawOriginal('workflow_status'),
                        $nextStatus->value
                    ),
                ]);
            }

            $this->authorizeTransition($lockedOrder, $actor, $currentStatus, $nextStatus);

            if ($nextStatus === OrderStatus::Delivered && (! $lockedOrder->shipment?->proof_path || ($lockedOrder->order->payment_method === 'cod' && ! $lockedOrder->shipment->cod_collected))) {
                throw ValidationException::withMessages(['proof_photo' => 'Delivery proof and COD collection confirmation are required.']);
            }

            if ($currentStatus->rank() >= OrderStatus::ReadyForPickup->rank()) {
                $shipmentStatus = $lockedOrder->shipment?->workflow_status;
                $shipmentStatus = $shipmentStatus instanceof OrderStatus
                    ? $shipmentStatus
                    : ($shipmentStatus ? OrderStatus::tryFrom(strtoupper($shipmentStatus)) : null);

                if ($shipmentStatus !== $currentStatus) {
                    throw ValidationException::withMessages([
                        'order' => 'The parcel and seller order states are inconsistent.',
                    ]);
                }
            }

            if ($nextStatus === OrderStatus::Sorted) {
                $area = trim((string) ($context['sorting_area'] ?? ''));

                if ($area === '') {
                    throw ValidationException::withMessages([
                        'sorting_area' => 'A sorting area is required.',
                    ]);
                }

                $lockedOrder->shipment->update(['sorting_area' => $area]);
            }

            if ($nextStatus === OrderStatus::AssignedToRider) {
                $rider = $context['rider'] ?? null;

                if ($rider instanceof Rider) {
                    $rider->loadMissing(['user', 'logisticsProvider']);
                }

                if (
                    ! $rider instanceof Rider
                    || ! $rider->is_active
                    || ! $rider->user
                    || ! $rider->user->hasRole('rider')
                    || $rider->user->status !== 'active'
                    || $rider->user->is_suspended
                    || $rider->logisticsProvider?->status !== 'approved'
                ) {
                    throw ValidationException::withMessages([
                        'rider_id' => 'An active delivery rider is required.',
                    ]);
                }

                if ($rider->logistics_provider_id !== $lockedOrder->shipment->logistics_provider_id) {
                    throw new AuthorizationException('The rider must belong to this logistics provider.');
                }

                $lockedOrder->shipment->update(['rider_id' => $rider->id]);
            }

            $now = now();
            $updates = [
                'workflow_status' => $nextStatus->value,
                'status' => $nextStatus->legacySellerStatus(),
                'status_changed_at' => $now,
            ];

            if ($timestampColumn = $nextStatus->timestampColumn()) {
                $updates[$timestampColumn] = $now;
            }

            $lockedOrder->update($updates);

            if ($lockedOrder->shipment) {
                $lockedOrder->shipment->update([
                    'workflow_status' => $nextStatus->value,
                    'status' => $nextStatus->legacyShipmentStatus(),
                    'status_changed_at' => $now,
                ]);

                $this->recordEvent(
                    $lockedOrder->shipment,
                    $nextStatus->value,
                    $actor,
                    $nextStatus === OrderStatus::AssignedToRider
                        ? 'Assigned to delivery rider '.$lockedOrder->shipment->rider?->user?->full_name.'.'
                        : null
                );
            }

            $this->synchronizeParentOrder($lockedOrder->order);

            return $lockedOrder->fresh(['order', 'seller', 'shipment']);
        });
    }

    public function acceptPickup(SellerOrder $sellerOrder, Rider $rider): Shipment
    {
        return DB::transaction(function () use ($sellerOrder, $rider) {
            $lockedOrder = SellerOrder::query()
                ->with(['order', 'shipment', 'shipment.provider', 'shipment.pickupRider.user'])
                ->lockForUpdate()
                ->findOrFail($sellerOrder->id);

            if (strtoupper((string) $lockedOrder->order?->status) === 'CANCELLED') {
                throw ValidationException::withMessages([
                    'order' => 'A cancelled order cannot be accepted for pickup.',
                ]);
            }

            $workflowStatus = $lockedOrder->workflow_status instanceof OrderStatus
                ? $lockedOrder->workflow_status
                : OrderStatus::tryFrom(strtoupper($lockedOrder->workflow_status));

            if ($workflowStatus !== OrderStatus::ReadyForPickup) {
                throw ValidationException::withMessages([
                    'order' => 'This parcel is not ready for pickup.',
                ]);
            }

            $rider->loadMissing(['user', 'logisticsProvider']);

            if (
                ! $rider->is_active
                || ! $rider->user
                || ! $rider->user->hasRole('rider')
                || $rider->user->status !== 'active'
                || $rider->user->is_suspended
                || $rider->logisticsProvider?->status !== 'approved'
            ) {
                throw new AuthorizationException('Only an active rider can accept a pickup.');
            }

            if (
                $lockedOrder->logistics_provider_id
                && $lockedOrder->logistics_provider_id !== $rider->logistics_provider_id
            ) {
                throw new AuthorizationException('This parcel belongs to another logistics provider.');
            }

            if (
                $lockedOrder->shipment
                && $lockedOrder->shipment->logistics_provider_id
                && $lockedOrder->shipment->logistics_provider_id !== $rider->logistics_provider_id
            ) {
                throw new AuthorizationException('This shipment belongs to another logistics provider.');
            }

            if (! $lockedOrder->shipment?->pickup_rider_id || $lockedOrder->shipment->pickup_rider_id !== $rider->id) {
                throw ValidationException::withMessages([
                    'order' => 'Logistics must assign this pickup before it can be accepted.',
                ]);
            }

            $shipment = $lockedOrder->shipment;

            $shipment->update(['pickup_accepted_at' => $shipment->pickup_accepted_at ?: now()]);

            $this->recordEvent($shipment, 'PICKUP_ACCEPTED', $rider->user);

            return $shipment->fresh();
        });
    }

    public function receiveAtSortingCenter(
        SellerOrder $sellerOrder,
        LogisticsProvider $provider,
        User $actor
    ): Shipment {
        return DB::transaction(function () use ($sellerOrder, $provider, $actor) {
            $lockedOrder = SellerOrder::query()
                ->with(['order', 'shipment.pickupRider', 'shipment.provider'])
                ->lockForUpdate()
                ->findOrFail($sellerOrder->id);

            if (strtoupper((string) $lockedOrder->order?->status) === 'CANCELLED') {
                throw ValidationException::withMessages([
                    'order' => 'A cancelled order cannot be received for logistics processing.',
                ]);
            }

            $shipment = $lockedOrder->shipment;
            $status = $lockedOrder->workflow_status instanceof OrderStatus
                ? $lockedOrder->workflow_status
                : OrderStatus::tryFrom(strtoupper((string) $lockedOrder->workflow_status));

            if (! $shipment || $status !== OrderStatus::AtSortingCenter) {
                throw ValidationException::withMessages([
                    'order' => 'Only parcels at the sorting center can be received.',
                ]);
            }

            if (
                ! $actor->hasRole('logistics')
                || $provider->user_id !== $actor->id
                || $provider->status !== 'approved'
            ) {
                throw new AuthorizationException('Only the approved logistics provider can receive this parcel.');
            }

            if ($shipment->pickupRider?->logistics_provider_id !== $provider->id) {
                throw new AuthorizationException('This parcel was not collected by a rider from your provider.');
            }

            if ($shipment->logistics_provider_id && $shipment->logistics_provider_id !== $provider->id) {
                throw new AuthorizationException('Another logistics provider has already received this parcel.');
            }

            $shipment->update(['logistics_provider_id' => $provider->id]);
            $lockedOrder->update(['logistics_provider_id' => $provider->id]);
            $this->recordEvent($shipment, 'RECEIVED_AT_SORTING_CENTER', $actor);

            return $shipment->fresh(['provider', 'pickupRider']);
        });
    }

    public function recordFailedDelivery(
        SellerOrder $sellerOrder,
        User $actor,
        string $reason
    ): DeliveryEvent {
        return DB::transaction(function () use ($sellerOrder, $actor, $reason) {
            $lockedOrder = SellerOrder::query()
                ->with(['order', 'shipment.rider.user'])
                ->lockForUpdate()
                ->findOrFail($sellerOrder->id);

            $status = $lockedOrder->workflow_status instanceof OrderStatus
                ? $lockedOrder->workflow_status
                : OrderStatus::tryFrom(strtoupper((string) $lockedOrder->workflow_status));

            if (
                $status !== OrderStatus::OutForDelivery
                || $lockedOrder->order->status === 'CANCELLED'
                || $actor->status !== 'active'
                || $actor->is_suspended
                || ! $lockedOrder->shipment?->rider?->is_active
                || ! $actor->hasRole('rider')
                || $lockedOrder->shipment?->rider?->user_id !== $actor->id
            ) {
                throw new AuthorizationException('Only the assigned delivery rider can record a failed attempt.');
            }

            $reason = trim($reason);
            if ($reason === '') {
                throw ValidationException::withMessages([
                    'failure_reason' => 'A failed delivery reason is required.',
                ]);
            }

            $shipment = $lockedOrder->shipment;
            $attempt = $shipment->attempts + 1;
            $shipment->update(['attempts' => $attempt]);

            return DeliveryEvent::create([
                'shipment_id' => $shipment->id,
                'status' => 'DELIVERY_ATTEMPT_FAILED',
                'attempt' => $attempt,
                'user_id' => $actor->id,
                'note' => $reason,
                'occurred_at' => now(),
            ]);
        });
    }

    public function recordDeliveryIssue(SellerOrder $sellerOrder, User $actor, string $category, ?string $details = null): DeliveryEvent
    {
        $labels = [
            'buyer_unreachable' => 'Buyer unreachable',
            'wrong_or_incomplete_address' => 'Wrong or incomplete address',
            'damaged_parcel' => 'Damaged parcel',
            'other' => 'Other delivery issue',
        ];

        if (! array_key_exists($category, $labels)) {
            throw ValidationException::withMessages(['issue_category' => 'Select a valid delivery issue category.']);
        }

        return DB::transaction(function () use ($sellerOrder, $actor, $category, $details, $labels) {
            $lockedOrder = SellerOrder::query()
                ->with(['order', 'shipment.rider.user'])
                ->lockForUpdate()
                ->findOrFail($sellerOrder->id);
            $shipment = $lockedOrder->shipment;

            if (
                $lockedOrder->workflow_status !== OrderStatus::OutForDelivery || $lockedOrder->order->status === 'CANCELLED'
                || ! $actor->hasRole('rider') || $actor->status !== 'active' || $actor->is_suspended
                || ! $shipment?->rider?->is_active || $shipment->rider->user_id !== $actor->id
            ) {
                throw new AuthorizationException('Only the assigned delivery rider may report a delivery issue.');
            }

            return DeliveryEvent::create([
                'shipment_id' => $shipment->id,
                'status' => 'DELIVERY_ISSUE_REPORTED',
                'attempt' => $shipment->attempts,
                'user_id' => $actor->id,
                'note' => $labels[$category].($details ? ': '.trim($details) : ''),
                'occurred_at' => now(),
            ]);
        });
    }

    private function authorizeTransition(
        SellerOrder $sellerOrder,
        ?User $actor,
        OrderStatus $currentStatus,
        OrderStatus $nextStatus
    ): void {
        if ($actor === null) {
            if ($currentStatus === OrderStatus::Delivered && $nextStatus === OrderStatus::Completed && $sellerOrder->delivered_at && $sellerOrder->delivered_at->lte(now()->subDays(3))) {
                return;
            }
            throw new AuthorizationException('Automatic completion requires three days in delivered status.');
        }
        if ($actor->status !== 'active' || $actor->is_suspended) {
            throw new AuthorizationException('Only active accounts may change fulfillment status.');
        }
        $allowed = match ([$currentStatus, $nextStatus]) {
            [OrderStatus::Placed, OrderStatus::Confirmed],
            [OrderStatus::Confirmed, OrderStatus::Preparing],
            [OrderStatus::Preparing, OrderStatus::ReadyForPickup] =>
                $actor->hasRole('seller') && $sellerOrder->seller->user_id === $actor->id,

            [OrderStatus::ReadyForPickup, OrderStatus::PickedUp],
            [OrderStatus::PickedUp, OrderStatus::AtSortingCenter] =>
                $actor->hasRole('rider')
                && $sellerOrder->shipment?->pickupRider?->user_id === $actor->id,

            [OrderStatus::AtSortingCenter, OrderStatus::Sorted],
            [OrderStatus::Sorted, OrderStatus::AssignedToRider] =>
                $actor->hasRole('logistics')
                && $sellerOrder->shipment?->provider?->user_id === $actor->id,

            [OrderStatus::AssignedToRider, OrderStatus::OutForDelivery],
            [OrderStatus::OutForDelivery, OrderStatus::Delivered] =>
                $actor->hasRole('rider')
                && $sellerOrder->shipment?->rider?->user_id === $actor->id,

            [OrderStatus::Delivered, OrderStatus::Completed] =>
                $actor->hasRole('buyer') && $sellerOrder->order->buyer_id === $actor->id,

            default => false,
        };

        if (! $allowed) {
            throw new AuthorizationException('You are not authorized for this ERP status transition.');
        }
    }

    private function synchronizeParentOrder(Order $order): void
    {
        $statuses = $order->sellerOrders()
            ->get(['workflow_status'])
            ->map(function (SellerOrder $sellerOrder) {
                return $sellerOrder->workflow_status instanceof OrderStatus
                    ? $sellerOrder->workflow_status
                    : OrderStatus::tryFrom(strtoupper($sellerOrder->workflow_status));
            })
            ->filter();

        if ($statuses->isEmpty()) {
            return;
        }

        $aggregate = $statuses->sortBy(fn (OrderStatus $status) => $status->rank())->first();
        $updates = ['status' => $aggregate->value];

        if ($timestampColumn = $aggregate->timestampColumn()) {
            $updates[$timestampColumn] = $order->{$timestampColumn} ?: now();
        }

        $order->update($updates);
    }

    private function recordEvent(Shipment $shipment, string $status, ?User $actor, ?string $note = null): void
    {
        DeliveryEvent::firstOrCreate(
            [
                'shipment_id' => $shipment->id,
                'status' => $status,
                'attempt' => 0,
            ],
            [
                'user_id' => $actor?->id,
                'note' => $actor === null ? 'Automatically completed three days after delivery.' : $note,
                'occurred_at' => now(),
            ]
        );
    }

}
