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
            if (! $rider->approved_at || ! $rider->isOperationallyAvailable() || $rider->logistics_provider_id !== $provider->id) {
                throw ValidationException::withMessages(['pickup_rider_id' => 'Select an active rider from your approved logistics provider.']);
            }
            app(RiderServiceAreaEligibilityService::class)->pickup($lockedOrder, $provider, $rider);

            if ($shipment->pickup_accepted_at) {
                throw ValidationException::withMessages(['order' => 'The pickup rider cannot be changed after accepting this parcel.']);
            }

            $previousRider = $shipment->pickupRider;
            $providerWasUnassigned = ! $shipment->logistics_provider_id && ! $lockedOrder->logistics_provider_id;
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

            app(DomainNotificationService::class)->afterCommit($rider->user_id, 'Pickup assignment', 'A pickup has been assigned to you.', ['shipment_id' => $shipment->id]);
            if ($providerWasUnassigned && $provider->user_id) {
                app(DomainNotificationService::class)->afterCommit($provider->user_id, 'Pickup ready', 'A shipment requires pickup attention.', ['seller_order_id' => $lockedOrder->id, 'shipment_id' => $shipment->id]);
            }
            if ($previousRider?->user_id) {
                app(DomainNotificationService::class)->afterCommit($previousRider->user_id, 'Pickup assignment changed', 'Your pickup assignment has changed.', ['shipment_id' => $shipment->id]);
            }

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

            if ($rider->logisticsProvider?->user_id) {
                app(DomainNotificationService::class)->afterCommit($rider->logisticsProvider->user_id, 'Pickup declined', 'A pickup requires reassignment.', ['shipment_id' => $shipment->id]);
            }

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

                if (! $rider instanceof Rider || ! $rider->isOperationallyAvailable()) {
                    throw ValidationException::withMessages([
                        'rider_id' => 'An active delivery rider is required.',
                    ]);
                }

                if ($rider->logistics_provider_id !== $lockedOrder->shipment->logistics_provider_id) {
                    throw new AuthorizationException('The rider must belong to this logistics provider.');
                }

                $provider = LogisticsProvider::query()->findOrFail($lockedOrder->shipment->logistics_provider_id);
                app(RiderServiceAreaEligibilityService::class)->delivery($lockedOrder, $provider, $rider);

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

            if ($nextStatus === OrderStatus::Completed) {
                app(SellerWalletService::class)->creditCompleted($lockedOrder);
            }

            $notifications = app(DomainNotificationService::class);
            $message = 'Order status: '.str_replace('_', ' ', strtolower($nextStatus->value)).'.';
            $notificationContext = ['seller_order_id' => $lockedOrder->id, 'status' => $nextStatus->value];
            if ($lockedOrder->order->buyer_id) {
                $notifications->afterCommit($lockedOrder->order->buyer_id, 'Order update', $message, $notificationContext);
            }
            if ($lockedOrder->seller?->user_id) {
                $notifications->afterCommit($lockedOrder->seller->user_id, 'Order update', $message, $notificationContext);
            }
            if ($nextStatus === OrderStatus::AssignedToRider && $lockedOrder->shipment?->rider?->user_id) {
                $notifications->afterCommit($lockedOrder->shipment->rider->user_id, 'Delivery assignment', 'A delivery has been assigned to you.', $notificationContext);
            }
            if ($nextStatus === OrderStatus::ReadyForPickup) {
                $providerId = $lockedOrder->shipment?->logistics_provider_id ?? $lockedOrder->logistics_provider_id;
                $providerUserId = $providerId ? LogisticsProvider::whereKey($providerId)->value('user_id') : null;
                if ($providerUserId) {
                    $notifications->afterCommit($providerUserId, 'Pickup ready', 'A shipment requires pickup attention.', $notificationContext);
                }
            }

            return $lockedOrder->fresh(['order', 'seller', 'shipment']);
        });
    }

    /** Acknowledge final-mile work without advancing the ERP delivery state. */
    public function acceptDelivery(SellerOrder $sellerOrder, Rider $rider): Shipment
    {
        return DB::transaction(function () use ($sellerOrder, $rider) {
            $locked = SellerOrder::query()->with('order')->lockForUpdate()->findOrFail($sellerOrder->id);
            $shipment = Shipment::query()->with('rider.user')->where('seller_order_id', $locked->id)->lockForUpdate()->first();
            if (! $shipment || $locked->order->status === 'CANCELLED' || $locked->workflow_status !== OrderStatus::AssignedToRider || $shipment->rider_id !== $rider->id) {
                throw new AuthorizationException('Only the assigned rider may accept this delivery before dispatch.');
            }
            $rider->loadMissing(['user', 'logisticsProvider']);
            if (! $rider->isOperationallyAvailable()) {
                throw new AuthorizationException('Only an online active rider may accept a delivery.');
            }
            if (! $shipment->delivery_accepted_at) {
                $shipment->update(['delivery_accepted_at' => now()]);
                $this->recordEvent($shipment, 'DELIVERY_ACCEPTED', $rider->user);
                if ($rider->logisticsProvider?->user_id) {
                    app(DomainNotificationService::class)->afterCommit($rider->logisticsProvider->user_id, 'Delivery accepted', 'A rider accepted an assigned delivery.', ['shipment_id' => $shipment->id]);
                }
            }
            return $shipment->fresh();
        });
    }

    public function reassignDeliveryRider(SellerOrder $sellerOrder, LogisticsProvider $provider, User $actor, Rider $rider): Shipment
    {
        return DB::transaction(function () use ($sellerOrder, $provider, $actor, $rider) {
            $locked = SellerOrder::query()->with('order')->lockForUpdate()->findOrFail($sellerOrder->id);
            $shipment = Shipment::query()->where('seller_order_id', $locked->id)->lockForUpdate()->first();
            if (! $shipment || $locked->order->status === 'CANCELLED' || $locked->workflow_status !== OrderStatus::AssignedToRider || $shipment->rider_id) {
                throw ValidationException::withMessages(['order' => 'Only an unaccepted, requeued delivery may be reassigned.']);
            }
            if (! $actor->hasRole('logistics') || $provider->user_id !== $actor->id || $provider->status !== 'approved' || $shipment->logistics_provider_id !== $provider->id) {
                throw new AuthorizationException('Only the owning logistics provider may reassign this delivery.');
            }
            $rider->loadMissing(['user', 'logisticsProvider']);
            if (! $rider->isOperationallyAvailable() || $rider->logistics_provider_id !== $provider->id) {
                throw ValidationException::withMessages(['rider_id' => 'Select an online active rider from your provider.']);
            }
            app(RiderServiceAreaEligibilityService::class)->delivery($locked, $provider, $rider);
            $shipment->update(['rider_id' => $rider->id, 'delivery_accepted_at' => null]);
            $this->recordEvent($shipment, 'DELIVERY_RIDER_REASSIGNED', $actor, 'Delivery rider assigned to '.$rider->user->full_name.'.');
            app(DomainNotificationService::class)->afterCommit($rider->user_id, 'Delivery assignment', 'A delivery has been assigned to you.', ['shipment_id' => $shipment->id]);
            return $shipment->fresh();
        });
    }

    /** Requeue final-mile work before acceptance; preserve provider and ERP state. */
    public function declineDelivery(SellerOrder $sellerOrder, Rider $rider): Shipment
    {
        return DB::transaction(function () use ($sellerOrder, $rider) {
            Order::query()->whereKey($sellerOrder->order_id)->lockForUpdate()->firstOrFail();
            $locked = SellerOrder::query()->with('order')->lockForUpdate()->findOrFail($sellerOrder->id);
            $shipment = Shipment::query()->with('rider.user')->where('seller_order_id', $locked->id)->lockForUpdate()->first();
            if (! $shipment || $locked->order->status === 'CANCELLED' || $locked->workflow_status !== OrderStatus::AssignedToRider || $shipment->rider_id !== $rider->id || $shipment->delivery_accepted_at) {
                throw new AuthorizationException('Only the assigned rider may decline before accepting a delivery.');
            }
            $rider->loadMissing(['user', 'logisticsProvider']);
            if (! $rider->isOperationallyEligible() || $shipment->logistics_provider_id !== $rider->logistics_provider_id) {
                throw new AuthorizationException('An inactive rider cannot decline operational work.');
            }
            $shipment->update(['rider_id' => null]);
            $this->recordEvent($shipment, 'DELIVERY_DECLINED', $rider->user, 'Delivery declined; parcel requeued for logistics reassignment.');
            if ($rider->logisticsProvider?->user_id) {
                app(DomainNotificationService::class)->afterCommit($rider->logisticsProvider->user_id, 'Delivery declined', 'A delivery requires reassignment.', ['shipment_id' => $shipment->id]);
            }
            return $shipment->fresh();
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

            $firstAcceptance = ! $shipment->pickup_accepted_at;
            $shipment->update(['pickup_accepted_at' => $shipment->pickup_accepted_at ?: now()]);

            $this->recordEvent($shipment, 'PICKUP_ACCEPTED', $rider->user);
            if ($firstAcceptance && $shipment->provider?->user_id) {
                app(DomainNotificationService::class)->afterCommit($shipment->provider->user_id, 'Pickup accepted', 'A rider accepted an assigned pickup.', ['shipment_id' => $shipment->id]);
            }

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
        string $reason,
        ?string $notes = null
    ): DeliveryEvent {
        $reasons = [
            'buyer_unavailable' => 'Buyer unavailable', 'buyer_unreachable' => 'Buyer unreachable',
            'incorrect_address' => 'Incorrect or incomplete address', 'access_issue' => 'Delivery access issue',
            'buyer_requested_reschedule' => 'Buyer requested reschedule', 'buyer_refused' => 'Buyer refused delivery',
            'other' => 'Other',
        ];
        if (! isset($reasons[$reason]) || ($reason === 'other' && ! trim((string) $notes))) {
            throw ValidationException::withMessages(['failure_reason' => 'Select a valid reason; other requires details.']);
        }
        return DB::transaction(function () use ($sellerOrder, $actor, $reason, $notes, $reasons) {
            Order::whereKey($sellerOrder->order_id)->lockForUpdate()->firstOrFail();
            $lockedOrder = SellerOrder::query()
                ->with(['order', 'seller', 'shipment.rider.user', 'shipment.provider'])
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

            $shipment = Shipment::whereKey($lockedOrder->shipment->id)->lockForUpdate()->firstOrFail();
            if ($shipment->attempts >= 3 || $shipment->workflow_status !== OrderStatus::OutForDelivery) {
                throw ValidationException::withMessages(['order' => 'This delivery cannot record another failed attempt.']);
            }
            $attempt = $shipment->attempts + 1;
            $next = $reason === 'buyer_refused' || $attempt >= 3 ? OrderStatus::RtsInitiated : OrderStatus::DeliveryFailed;
            $shipment->update(['attempts' => $attempt, 'workflow_status' => $next, 'status' => $next->legacyShipmentStatus(), 'status_changed_at' => now()]);
            $lockedOrder->update(['workflow_status' => $next, 'status' => $next->legacySellerStatus(), 'status_changed_at' => now()]);
            $this->synchronizeParentOrder($lockedOrder->order);

            $event = DeliveryEvent::create([
                'shipment_id' => $shipment->id,
                'status' => 'DELIVERY_ATTEMPT_FAILED',
                'attempt' => $attempt,
                'user_id' => $actor->id,
                'note' => $reasons[$reason].(trim((string) $notes) !== '' ? ': '.trim($notes) : ''),
                'occurred_at' => now(),
            ]);
            $notifications = app(DomainNotificationService::class);
            $context = ['seller_order_id' => $lockedOrder->id, 'shipment_id' => $shipment->id, 'attempt' => $attempt];
            $notifications->afterCommit($lockedOrder->order->buyer_id, 'Delivery attempt failed', 'Your delivery attempt was unsuccessful.', $context);
            if ($lockedOrder->shipment->provider?->user_id) {
                $notifications->afterCommit($lockedOrder->shipment->provider->user_id, 'Delivery requires attention', $next === OrderStatus::RtsInitiated ? 'The parcel requires return to sender.' : 'A failed delivery requires rescheduling.', $context);
            }
            if ($next === OrderStatus::RtsInitiated && $lockedOrder->seller?->user_id) {
                $notifications->afterCommit($lockedOrder->seller->user_id, 'Return initiated', 'A shipment is being returned to your store.', $context);
            }
            return $event;
        });
    }

    public function rescheduleDelivery(SellerOrder $sellerOrder, LogisticsProvider $provider, User $actor, Rider $rider, string $scheduledAt): Shipment
    {
        return DB::transaction(function () use ($sellerOrder, $provider, $actor, $rider, $scheduledAt) {
            Order::whereKey($sellerOrder->order_id)->lockForUpdate()->firstOrFail();
            $order = SellerOrder::with('order')->lockForUpdate()->findOrFail($sellerOrder->id);
            $shipment = Shipment::with('rider')->where('seller_order_id', $order->id)->lockForUpdate()->firstOrFail();
            if (! $actor->hasRole('logistics') || $actor->status !== 'active' || $actor->is_suspended || $provider->status !== 'approved' || $provider->user_id !== $actor->id || $shipment->logistics_provider_id !== $provider->id) {
                throw new AuthorizationException('Only the owning logistics provider may reschedule this shipment.');
            }
            if ($order->order->status === 'CANCELLED' || $order->workflow_status !== OrderStatus::DeliveryFailed || $shipment->workflow_status !== OrderStatus::DeliveryFailed || $shipment->attempts >= 3) {
                throw ValidationException::withMessages(['order' => 'This delivery cannot be rescheduled.']);
            }
            $rider->loadMissing(['user', 'logisticsProvider']);
            if ($rider->logistics_provider_id !== $provider->id || ! $rider->isOperationallyAvailable()) {
                throw ValidationException::withMessages(['rider_id' => 'Select an available rider from your provider.']);
            }
            app(RiderServiceAreaEligibilityService::class)->delivery($order, $provider, $rider);
            $oldRider = $shipment->rider;
            $shipment->update(['rider_id' => $rider->id, 'delivery_accepted_at' => null, 'rescheduled_at' => $scheduledAt, 'workflow_status' => OrderStatus::AssignedToRider, 'status' => 'assigned', 'status_changed_at' => now()]);
            $order->update(['workflow_status' => OrderStatus::AssignedToRider, 'status' => 'shipped', 'status_changed_at' => now()]);
            DeliveryEvent::create(['shipment_id' => $shipment->id, 'status' => 'DELIVERY_RESCHEDULED', 'attempt' => $shipment->attempts, 'user_id' => $actor->id, 'note' => 'Scheduled for '.$scheduledAt, 'occurred_at' => now()]);
            $this->synchronizeParentOrder($order->order);
            $notifications = app(DomainNotificationService::class);
            $context = ['shipment_id' => $shipment->id, 'seller_order_id' => $order->id];
            $notifications->afterCommit($order->order->buyer_id, 'Delivery rescheduled', 'Your delivery has been rescheduled.', $context);
            $notifications->afterCommit($rider->user_id, 'Delivery assignment', 'A rescheduled delivery has been assigned to you.', $context);
            if ($oldRider && $oldRider->id !== $rider->id) {
                $notifications->afterCommit($oldRider->user_id, 'Assignment changed', 'Your delivery assignment has changed.', $context);
            }
            return $shipment->fresh();
        });
    }

    public function advanceReturn(SellerOrder $sellerOrder, LogisticsProvider $provider, User $actor, OrderStatus $next): Shipment
    {
        return DB::transaction(function () use ($sellerOrder, $provider, $actor, $next) {
            Order::whereKey($sellerOrder->order_id)->lockForUpdate()->firstOrFail();
            $order = SellerOrder::with(['order', 'seller'])->lockForUpdate()->findOrFail($sellerOrder->id);
            $shipment = Shipment::where('seller_order_id', $order->id)->lockForUpdate()->firstOrFail();
            if (! $actor->hasRole('logistics') || $actor->status !== 'active' || $actor->is_suspended || $provider->status !== 'approved' || $provider->user_id !== $actor->id || $shipment->logistics_provider_id !== $provider->id) {
                throw new AuthorizationException('Only the owning logistics provider may manage this return.');
            }
            $valid = ($order->workflow_status === OrderStatus::RtsInitiated && $next === OrderStatus::RtsReturning)
                || ($order->workflow_status === OrderStatus::RtsReturning && $next === OrderStatus::RtsCompleted);
            if (! $valid || $shipment->workflow_status !== $order->workflow_status || $order->order->status === 'CANCELLED') {
                throw ValidationException::withMessages(['order' => 'Invalid return transition.']);
            }
            $shipment->update(['workflow_status' => $next, 'status' => $next->legacyShipmentStatus(), 'status_changed_at' => now()]);
            $order->update(['workflow_status' => $next, 'status' => 'shipped', 'status_changed_at' => now()]);
            DeliveryEvent::create(['shipment_id' => $shipment->id, 'status' => $next->value, 'attempt' => $shipment->attempts, 'user_id' => $actor->id, 'occurred_at' => now()]);
            $this->synchronizeParentOrder($order->order);
            if ($order->seller?->user_id) {
                app(DomainNotificationService::class)->afterCommit($order->seller->user_id, 'Return update', $next === OrderStatus::RtsCompleted ? 'The parcel has been returned to your store.' : 'The parcel is returning to your store.', ['shipment_id' => $shipment->id, 'seller_order_id' => $order->id]);
            }
            return $shipment->fresh();
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
