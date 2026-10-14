<?php

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Enums\ConversationType;
use App\Enums\MessageType;
use App\Enums\ProductModerationStatus;
use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\LogisticsProvider;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MessagingService
{
    /**
     * Create the single inquiry thread for a visible database product.
     *
     * Seller and participant context is always derived from the persisted
     * product. Callers cannot select or inject conversation participants.
     */
    public function createProductInquiry(
        User $buyer,
        Product $product,
        ?string $subject = null,
    ): Conversation {
        $product = $this->persistedProduct($product);

        return $this->createConversation(
            $buyer,
            ConversationType::StoreInquiry,
            [
                'seller' => $product->seller,
                'product' => $product,
            ],
            subject: $subject,
        );
    }

    /**
     * Create the buyer/seller thread for one persisted seller order.
     *
     * A parent order is deliberately not accepted as the conversation
     * identity so multi-seller orders remain isolated by seller order.
     */
    public function createSellerOrderConversation(
        User $actor,
        SellerOrder $sellerOrder,
        ?string $subject = null,
    ): Conversation {
        $sellerOrder = $this->persistedSellerOrder($sellerOrder);

        return $this->createConversation(
            $actor,
            ConversationType::Order,
            [
                'order' => $sellerOrder->order,
                'seller_order' => $sellerOrder,
            ],
            subject: $subject,
        );
    }

    /**
     * Create the support thread for the authenticated rider's current
     * pickup or delivery assignment. The rider and role are server-derived.
     */
    public function createRiderShipmentConversation(
        User $actor,
        Shipment $shipment,
        ?string $subject = null,
    ): Conversation {
        $this->ensureEligibleRole($actor, 'rider');
        $shipment = $this->persistedShipment($shipment);
        $rider = $actor->rider()->with('user')->first();
        abort_unless($rider, 403, 'An active rider profile is required.');

        return $this->createConversation(
            $actor,
            ConversationType::RiderSupport,
            [
                'shipment' => $shipment,
                'rider' => $rider,
            ],
            subject: $subject,
        );
    }

    /**
     * Create the support thread for the logistics provider currently
     * handling a persisted shipment. Provider context is server-derived.
     */
    public function createLogisticsShipmentConversation(
        User $actor,
        Shipment $shipment,
        ?string $subject = null,
    ): Conversation {
        $this->ensureEligibleRole($actor, 'logistics');
        $shipment = $this->persistedShipment($shipment);

        return $this->createConversation(
            $actor,
            ConversationType::LogisticsSupport,
            ['shipment' => $shipment],
            subject: $subject,
        );
    }

    /**
     * Create the single thread for a complaint filer and its current
     * assigned administrator. No evidence or internal event notes are read.
     */
    public function createComplaintConversation(
        User $actor,
        Complaint $complaint,
        ?string $subject = null,
    ): Conversation {
        $complaint = $this->persistedComplaint($complaint);

        return $this->createConversation(
            $actor,
            ConversationType::Complaint,
            ['complaint' => $complaint],
            subject: $subject,
        );
    }

    /**
     * Create or return the single conversation for an authorized ERP context.
     *
     * Supported context keys are order, seller_order, shipment, complaint,
     * seller, product, and rider. Additional participants are permitted only
     * where the conversation type explicitly supports administrator contact.
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, User>  $participants
     */
    public function createConversation(
        User $actor,
        ConversationType $type,
        array $context = [],
        array $participants = [],
        ?string $subject = null,
    ): Conversation {
        $this->ensureEligibleUser($actor);
        $subject = $this->validatedSubject($subject);
        $resolved = $this->resolveConversation($actor, $type, $context, $participants);

        return DB::transaction(function () use ($actor, $type, $subject, $resolved) {
            $conversation = Conversation::query()->firstOrCreate(
                ['conversation_key' => $resolved['key']],
                [
                    'reference' => (string) Str::uuid(),
                    'type' => $type,
                    'status' => ConversationStatus::Active,
                    'subject' => $subject,
                    'created_by' => $actor->id,
                    'order_id' => $resolved['order']?->id,
                    'seller_order_id' => $resolved['seller_order']?->id,
                    'shipment_id' => $resolved['shipment']?->id,
                    'complaint_id' => $resolved['complaint']?->id,
                    'seller_id' => $resolved['seller']?->id,
                    'product_id' => $resolved['product']?->id,
                    'context_snapshot' => $this->contextSnapshot($resolved),
                ]
            );

            $locked = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);

            foreach ($resolved['participants'] as $participant) {
                ConversationParticipant::query()->firstOrCreate(
                    [
                        'conversation_id' => $locked->id,
                        'user_id' => $participant['user']->id,
                        'role_context' => $participant['role_context'],
                    ],
                    [
                        'seller_id' => $participant['seller']?->id,
                        'rider_id' => $participant['rider']?->id,
                        'logistics_provider_id' => $participant['provider']?->id,
                        'joined_at' => now(),
                    ]
                );
            }

            return $locked->fresh(['participants.user']);
        });
    }

    public function sendMessage(
        Conversation $conversation,
        User $sender,
        string $body,
        MessageType $type = MessageType::Text,
        ?Message $replyTo = null,
    ): Message {
        $body = trim($body);
        if ($body === '' || Str::length($body) > 10000) {
            throw ValidationException::withMessages([
                'body' => 'A message between 1 and 10,000 characters is required.',
            ]);
        }

        return DB::transaction(function () use ($conversation, $sender, $body, $type, $replyTo) {
            $locked = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $this->ensureCanAccess($locked, $sender);

            if ($locked->status !== ConversationStatus::Active) {
                throw ValidationException::withMessages([
                    'conversation' => 'Messages can only be sent to an active conversation.',
                ]);
            }

            if ($type === MessageType::System && ! $this->isActiveAdmin($sender)) {
                abort(403, 'Only an active administrator can send a system message.');
            }

            if ($replyTo && (int) $replyTo->conversation_id !== (int) $locked->id) {
                throw ValidationException::withMessages([
                    'reply_to' => 'The reply target does not belong to this conversation.',
                ]);
            }

            $sentAt = now();
            $message = Message::create([
                'conversation_id' => $locked->id,
                'sender_id' => $sender->id,
                'type' => $type,
                'body' => $body,
                'reply_to_id' => $replyTo?->id,
                'sent_at' => $sentAt,
            ]);

            $locked->update(['last_message_at' => $sentAt]);
            $this->updateReadCursor($locked, $sender, $message, $sentAt);

            return $message->fresh(['sender', 'replyTo']);
        });
    }

    public function markRead(Conversation $conversation, User $actor, ?Message $through = null): void
    {
        DB::transaction(function () use ($conversation, $actor, $through) {
            $locked = Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $this->ensureCanAccess($locked, $actor);

            $target = $through ?: $locked->messages()->latest('id')->first();
            if (! $target) {
                ConversationParticipant::query()
                    ->where('conversation_id', $locked->id)
                    ->where('user_id', $actor->id)
                    ->whereNull('left_at')
                    ->update(['last_read_at' => now(), 'updated_at' => now()]);

                return;
            }

            if ((int) $target->conversation_id !== (int) $locked->id) {
                throw ValidationException::withMessages([
                    'message' => 'The read position does not belong to this conversation.',
                ]);
            }

            $current = ConversationParticipant::query()
                ->where('conversation_id', $locked->id)
                ->where('user_id', $actor->id)
                ->whereNull('left_at')
                ->max('last_read_message_id');

            if (! $current || (int) $target->id > (int) $current) {
                $this->updateReadCursor($locked, $actor, $target, now());
            }
        });
    }

    public function unreadCount(Conversation $conversation, User $actor): int
    {
        $this->ensureCanAccess($conversation, $actor);

        $lastRead = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $actor->id)
            ->whereNull('left_at')
            ->max('last_read_message_id');

        return $conversation->messages()
            ->when($lastRead, fn ($query) => $query->where('id', '>', $lastRead))
            ->where(function ($query) use ($actor) {
                $query->whereNull('sender_id')->orWhere('sender_id', '!=', $actor->id);
            })
            ->count();
    }

    public function ensureCanAccess(Conversation $conversation, User $actor): void
    {
        abort_unless($this->canAccess($conversation, $actor), 403);
    }

    /** @param array<int, string> $roleContexts */
    public function ensureCanAccessAs(Conversation $conversation, User $actor, array $roleContexts): void
    {
        abort_unless($this->canAccessAs($conversation, $actor, $roleContexts), 403);
    }

    /** @param array<int, string> $roleContexts */
    public function canAccessAs(Conversation $conversation, User $actor, array $roleContexts): bool
    {
        if (! $this->isEligibleUser($actor)) {
            return false;
        }

        return $conversation->participants()
            ->where('user_id', $actor->id)
            ->whereIn('role_context', $roleContexts)
            ->whereNull('left_at')
            ->get()
            ->contains(fn (ConversationParticipant $participant) => $this->participantStillOwnsContext($conversation, $participant, $actor)
            );
    }

    public function canAccess(Conversation $conversation, User $actor): bool
    {
        if (! $this->isEligibleUser($actor)) {
            return false;
        }

        return $conversation->participants()
            ->where('user_id', $actor->id)
            ->whereNull('left_at')
            ->get()
            ->contains(fn (ConversationParticipant $participant) => $this->participantStillOwnsContext($conversation, $participant, $actor)
            );
    }

    /** @param array<string, mixed> $context @param array<int, User> $extra */
    private function resolveConversation(User $actor, ConversationType $type, array $context, array $extra): array
    {
        foreach ($extra as $participant) {
            if (! $participant instanceof User) {
                throw ValidationException::withMessages(['participants' => 'Every participant must be a user.']);
            }
            $this->ensureEligibleUser($participant);
        }

        return match ($type) {
            ConversationType::StoreInquiry => $this->resolveStoreInquiry($actor, $context, $extra),
            ConversationType::Order => $this->resolveOrderConversation($actor, $context, $extra),
            ConversationType::Complaint => $this->resolveComplaintConversation($actor, $context, $extra),
            ConversationType::RiderSupport => $this->resolveRiderSupport($actor, $context, $extra),
            ConversationType::LogisticsSupport => $this->resolveLogisticsSupport($actor, $context, $extra),
            ConversationType::AdminSupport => $this->resolveAdminSupport($actor, $context, $extra),
        };
    }

    /** @param array<string, mixed> $context @param array<int, User> $extra */
    private function resolveStoreInquiry(User $actor, array $context, array $extra): array
    {
        $this->rejectExtraParticipants($extra);
        $seller = $this->requiredModel($context, 'seller', Seller::class);
        $product = $this->optionalModel($context, 'product', Product::class);
        $this->ensureEligibleRole($actor, 'buyer');
        $this->ensureApprovedSeller($seller);

        if ($product) {
            $product = $this->persistedProduct($product);
            $this->ensureMarketplaceVisibleProduct($product);
        }

        if ($product && (int) $product->seller_id !== (int) $seller->id) {
            throw ValidationException::withMessages(['product' => 'The product does not belong to the selected seller.']);
        }

        $owner = $seller->owner;
        $this->ensureEligibleRole($owner, 'seller');
        $resolved = $this->baseResolved();
        $resolved['seller'] = $seller;
        $resolved['product'] = $product;
        $resolved['participants'] = [
            $this->participant($actor, 'BUYER'),
            $this->participant($owner, 'SELLER', $seller),
        ];
        $resolved['key'] = "STORE_INQUIRY:SELLER:{$seller->id}:BUYER:{$actor->id}:PRODUCT:".($product?->id ?? 0);

        return $resolved;
    }

    /** @param array<string, mixed> $context @param array<int, User> $extra */
    private function resolveOrderConversation(User $actor, array $context, array $extra): array
    {
        $this->rejectExtraParticipants($extra);
        $providedOrder = $this->requiredModel($context, 'order', Order::class);
        $sellerOrder = $this->persistedSellerOrder(
            $this->requiredModel($context, 'seller_order', SellerOrder::class)
        );
        $order = $sellerOrder->order;

        if (! $providedOrder->exists || (int) $providedOrder->id !== (int) $order->id) {
            throw ValidationException::withMessages(['seller_order' => 'The seller order does not belong to the selected order.']);
        }

        $seller = $sellerOrder->seller;
        $buyer = $order->buyer;
        $owner = $seller->owner;
        $this->ensureEligibleRole($buyer, 'buyer');
        $this->ensureEligibleRole($owner, 'seller');
        $this->ensureApprovedSeller($seller);

        abort_unless(
            (int) $actor->id === (int) $buyer->id || (int) $actor->id === (int) $owner->id,
            403
        );

        $resolved = $this->baseResolved();
        $resolved['order'] = $order;
        $resolved['seller_order'] = $sellerOrder;
        $resolved['shipment'] = $sellerOrder->shipment;
        $resolved['seller'] = $seller;
        $resolved['participants'] = [
            $this->participant($buyer, 'BUYER'),
            $this->participant($owner, 'SELLER', $seller),
        ];
        $resolved['key'] = "ORDER:SELLER_ORDER:{$sellerOrder->id}";

        return $resolved;
    }

    /** @param array<string, mixed> $context @param array<int, User> $extra */
    private function resolveComplaintConversation(User $actor, array $context, array $extra): array
    {
        $complaint = $this->persistedComplaint(
            $this->requiredModel($context, 'complaint', Complaint::class)
        );
        $isFiler = (int) $complaint->complainant_id === (int) $actor->id;
        $isAssignedAdmin = $this->isActiveAdmin($actor)
            && (int) $complaint->assigned_to === (int) $actor->id;
        abort_unless(
            $isFiler || $isAssignedAdmin,
            403
        );

        foreach ($extra as $user) {
            $this->ensureActiveAdmin($user);
            abort_unless((int) $complaint->assigned_to === (int) $user->id, 403);
        }

        $resolved = $this->baseResolved();
        $resolved['complaint'] = $complaint;
        $resolved['order'] = $complaint->order;
        $resolved['seller_order'] = $complaint->sellerOrder;
        $resolved['shipment'] = $complaint->shipment;
        $resolved['seller'] = $complaint->seller;
        $resolved['product'] = $complaint->product;

        $participants = [];
        if ($complaint->complainant) {
            $this->ensureEligibleUser($complaint->complainant);
            $participants[] = $this->participantForComplaint($complaint);
        }
        if ($complaint->assignee && $this->isActiveAdmin($complaint->assignee)) {
            $participants[] = $this->participant($complaint->assignee, 'ADMIN');
        }
        if ($isAssignedAdmin) {
            $participants[] = $this->participant($actor, 'ADMIN');
        }
        foreach ($extra as $user) {
            $participants[] = $this->participant($user, 'ADMIN');
        }

        $resolved['participants'] = $this->uniqueParticipants($participants);
        $resolved['key'] = "COMPLAINT:{$complaint->id}";

        return $resolved;
    }

    /** @param array<string, mixed> $context @param array<int, User> $extra */
    private function resolveRiderSupport(User $actor, array $context, array $extra): array
    {
        $shipment = $this->persistedShipment(
            $this->requiredModel($context, 'shipment', Shipment::class)
        );
        $rider = $this->persistedRider(
            $this->requiredModel($context, 'rider', Rider::class)
        );
        $role = (int) $shipment->pickup_rider_id === (int) $rider->id
            ? 'PICKUP_RIDER'
            : ((int) $shipment->rider_id === (int) $rider->id ? 'DELIVERY_RIDER' : null);
        abort_unless($role !== null, 403);
        $this->ensureActiveRider($rider);
        abort_unless((int) $rider->user_id === (int) $actor->id || $this->isActiveAdmin($actor), 403);

        $admins = $this->activeAdminParticipants($extra);
        if ($this->isActiveAdmin($actor)) {
            $admins[] = $this->participant($actor, 'ADMIN');
        }

        $resolved = $this->baseResolved();
        $resolved['order'] = $shipment->sellerOrder?->order;
        $resolved['seller_order'] = $shipment->sellerOrder;
        $resolved['shipment'] = $shipment;
        $resolved['seller'] = $shipment->sellerOrder?->seller;
        $resolved['participants'] = $this->uniqueParticipants([
            $this->participant($rider->user, $role, rider: $rider),
            ...$admins,
        ]);
        $resolved['key'] = "RIDER_SUPPORT:SHIPMENT:{$shipment->id}:RIDER:{$rider->id}:{$role}";

        return $resolved;
    }

    /** @param array<string, mixed> $context @param array<int, User> $extra */
    private function resolveLogisticsSupport(User $actor, array $context, array $extra): array
    {
        $shipment = $this->persistedShipment(
            $this->requiredModel($context, 'shipment', Shipment::class)
        );
        $provider = $shipment->provider;
        abort_unless($provider !== null, 403);
        $this->ensureApprovedProvider($provider);
        abort_unless((int) $provider->user_id === (int) $actor->id || $this->isActiveAdmin($actor), 403);

        $admins = $this->activeAdminParticipants($extra);
        if ($this->isActiveAdmin($actor)) {
            $admins[] = $this->participant($actor, 'ADMIN');
        }

        $resolved = $this->baseResolved();
        $resolved['order'] = $shipment->sellerOrder?->order;
        $resolved['seller_order'] = $shipment->sellerOrder;
        $resolved['shipment'] = $shipment;
        $resolved['seller'] = $shipment->sellerOrder?->seller;
        $resolved['participants'] = $this->uniqueParticipants([
            $this->participant($provider->owner, 'LOGISTICS', provider: $provider),
            ...$admins,
        ]);
        $resolved['key'] = "LOGISTICS_SUPPORT:SHIPMENT:{$shipment->id}:PROVIDER:{$provider->id}";

        return $resolved;
    }

    /** @param array<string, mixed> $context @param array<int, User> $extra */
    private function resolveAdminSupport(User $actor, array $context, array $extra): array
    {
        $users = collect([$actor, ...$extra])->unique('id')->values();
        abort_unless($users->contains(fn (User $user) => $this->isActiveAdmin($user)), 403);
        abort_unless($users->filter(fn (User $user) => ! $user->isAdmin())->count() === 1, 403);

        /** @var User $requester */
        $requester = $users->first(fn (User $user) => ! $user->isAdmin());

        foreach ($users->reject(fn (User $user) => $user->isAdmin()) as $participant) {
            abort_unless($participant->hasAnyRole(['buyer', 'seller']), 403);
        }

        $resolved = $this->baseResolved();
        $resolved['seller'] = $this->optionalModel($context, 'seller', Seller::class);
        if ($resolved['seller']) {
            abort_unless(
                $requester->hasRole('seller')
                && (int) $resolved['seller']->user_id === (int) $requester->id,
                403
            );
            $this->ensureApprovedSeller($resolved['seller']);
        }
        $resolved['participants'] = $users
            ->map(fn (User $user) => $this->participantForUser($user, $resolved))
            ->all();
        $resolved['key'] = "ADMIN_SUPPORT:USER:{$requester->id}:SELLER:".($resolved['seller']?->id ?? 0);

        return $resolved;
    }

    private function participantStillOwnsContext(
        Conversation $conversation,
        ConversationParticipant $participant,
        User $user,
    ): bool {
        return match ($participant->role_context) {
            'ADMIN' => $this->isActiveAdmin($user)
                && (! $conversation->complaint_id
                    || (int) $conversation->complaint?->assigned_to === (int) $user->id),
            'BUYER' => $user->hasRole('buyer')
                && (! $conversation->order_id || (int) $conversation->order?->buyer_id === (int) $user->id)
                && (! $conversation->complaint_id || (int) $conversation->complaint?->complainant_id === (int) $user->id),
            'SELLER' => $user->hasRole('seller')
                && $participant->seller
                && (int) $participant->seller->user_id === (int) $user->id
                && (! $conversation->seller_id || (int) $conversation->seller_id === (int) $participant->seller_id)
                && (! $conversation->seller_order_id || (int) $conversation->sellerOrder?->seller_id === (int) $participant->seller_id)
                && (! $conversation->complaint_id || (int) $conversation->complaint?->complainant_id === (int) $user->id),
            'PICKUP_RIDER' => $user->hasRole('rider')
                && $participant->rider
                && (int) $participant->rider->user_id === (int) $user->id
                && ($conversation->complaint_id
                    ? (int) $conversation->complaint?->complainant_id === (int) $user->id
                    : (int) $conversation->shipment?->pickup_rider_id === (int) $participant->rider_id),
            'DELIVERY_RIDER' => $user->hasRole('rider')
                && $participant->rider
                && (int) $participant->rider->user_id === (int) $user->id
                && ($conversation->complaint_id
                    ? (int) $conversation->complaint?->complainant_id === (int) $user->id
                    : (int) $conversation->shipment?->rider_id === (int) $participant->rider_id),
            'LOGISTICS' => $user->hasRole('logistics')
                && $participant->logisticsProvider
                && (int) $participant->logisticsProvider->user_id === (int) $user->id
                && ($conversation->complaint_id
                    ? (int) $conversation->complaint?->complainant_id === (int) $user->id
                    : (! $conversation->shipment_id
                        || (int) $conversation->shipment?->logistics_provider_id === (int) $participant->logistics_provider_id)),
            default => false,
        };
    }

    private function participantForComplaint(Complaint $complaint): array
    {
        $user = $complaint->complainant;

        return match (strtoupper((string) $complaint->complainant_role)) {
            'BUYER' => tap($this->participant($user, 'BUYER'), function () use ($user) {
                $this->ensureEligibleRole($user, 'buyer');
            }),
            'SELLER' => tap($this->participant($user, 'SELLER', $complaint->seller), function () use ($complaint, $user) {
                $this->ensureEligibleRole($user, 'seller');
                abort_unless($complaint->seller && (int) $complaint->seller->user_id === (int) $user->id, 403);
            }),
            'RIDER' => tap($this->participant(
                $user,
                strtoupper((string) $complaint->rider_role) === 'PICKUP' ? 'PICKUP_RIDER' : 'DELIVERY_RIDER',
                rider: $complaint->rider,
            ), function () use ($complaint, $user) {
                $this->ensureEligibleRole($user, 'rider');
                abort_unless($complaint->rider && (int) $complaint->rider->user_id === (int) $user->id, 403);
                abort_unless(in_array(strtoupper((string) $complaint->rider_role), ['PICKUP', 'DELIVERY'], true), 403);
            }),
            'LOGISTICS' => tap($this->participant(
                $user,
                'LOGISTICS',
                provider: $complaint->logisticsProvider,
            ), function () use ($complaint, $user) {
                $this->ensureEligibleRole($user, 'logistics');
                abort_unless(
                    $complaint->logisticsProvider
                    && (int) $complaint->logisticsProvider->user_id === (int) $user->id,
                    403
                );
            }),
            default => abort(403, 'The complaint filer role is not eligible for messaging.'),
        };
    }

    private function participantForUser(User $user, array $resolved): array
    {
        if ($this->isActiveAdmin($user)) {
            return $this->participant($user, 'ADMIN');
        }
        if ($user->hasRole('buyer')) {
            return $this->participant($user, 'BUYER');
        }
        if ($user->hasRole('seller')) {
            $seller = $resolved['seller'];
            if (! $seller && $user->sellers()->count() === 1) {
                $seller = $user->sellers()->first();
            }
            if (! $seller) {
                throw ValidationException::withMessages([
                    'seller' => 'An explicit seller context is required for a multi-store account.',
                ]);
            }
            abort_unless($seller && (int) $seller->user_id === (int) $user->id, 403);

            return $this->participant($user, 'SELLER', $seller);
        }
        if ($user->hasRole('rider')) {
            $rider = $user->rider;
            abort_unless($rider, 403);
            $shipment = $resolved['shipment'];
            $role = $shipment && (int) $shipment->pickup_rider_id === (int) $rider->id
                ? 'PICKUP_RIDER'
                : 'DELIVERY_RIDER';

            return $this->participant($user, $role, rider: $rider);
        }
        if ($user->hasRole('logistics')) {
            abort_unless($user->logisticsProvider, 403);

            return $this->participant($user, 'LOGISTICS', provider: $user->logisticsProvider);
        }

        abort(403, 'The user does not have a messaging role.');
    }

    private function participant(
        User $user,
        string $roleContext,
        ?Seller $seller = null,
        ?Rider $rider = null,
        ?LogisticsProvider $provider = null,
    ): array {
        return compact('user', 'seller', 'rider', 'provider') + ['role_context' => $roleContext];
    }

    private function uniqueParticipants(array $participants): array
    {
        return collect($participants)
            ->unique(fn (array $participant) => $participant['user']->id.':'.$participant['role_context'])
            ->values()
            ->all();
    }

    private function activeAdminParticipants(array $users): array
    {
        return collect($users)->map(function (User $user) {
            $this->ensureActiveAdmin($user);

            return $this->participant($user, 'ADMIN');
        })->all();
    }

    private function updateReadCursor(Conversation $conversation, User $user, Message $message, mixed $readAt): void
    {
        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->update([
                'last_read_message_id' => $message->id,
                'last_read_at' => $readAt,
                'updated_at' => now(),
            ]);
    }

    private function validatedSubject(?string $subject): ?string
    {
        $subject = $subject === null ? null : trim($subject);
        if ($subject !== null && Str::length($subject) > 200) {
            throw ValidationException::withMessages(['subject' => 'The subject may not exceed 200 characters.']);
        }

        return $subject === '' ? null : $subject;
    }

    private function rejectExtraParticipants(array $participants): void
    {
        if ($participants !== []) {
            throw ValidationException::withMessages([
                'participants' => 'Participants for this conversation are derived from its marketplace context.',
            ]);
        }
    }

    private function ensureEligibleRole(User $user, string $role): void
    {
        $this->ensureEligibleUser($user);
        abort_unless($user->hasRole($role), 403);
    }

    private function ensureEligibleUser(User $user): void
    {
        abort_unless($this->isEligibleUser($user), 403, 'The account is not active for messaging.');
    }

    private function isEligibleUser(User $user): bool
    {
        return $user->status === 'active' && ! $user->is_suspended;
    }

    private function ensureActiveAdmin(User $user): void
    {
        abort_unless($this->isActiveAdmin($user), 403, 'Only an active administrator can participate.');
    }

    private function isActiveAdmin(User $user): bool
    {
        return $this->isEligibleUser($user) && $user->isAdmin();
    }

    private function ensureApprovedSeller(Seller $seller): void
    {
        abort_unless($seller->status === 'approved', 403, 'The seller is not approved.');
    }

    private function ensureMarketplaceVisibleProduct(Product $product): void
    {
        abort_unless(
            $product->is_active
            && $product->moderation_status === ProductModerationStatus::Approved
            && $product->category?->is_active
            && $product->variants->contains(fn ($variant) => $variant->is_active),
            403,
            'The product is not available for marketplace messaging.'
        );

        $this->ensureApprovedSeller($product->seller);
        $this->ensureEligibleRole($product->seller->owner, 'seller');
    }

    private function persistedProduct(Product $product): Product
    {
        if (! $product->exists || ! $product->getKey()) {
            throw ValidationException::withMessages([
                'product' => 'A database-backed product is required.',
            ]);
        }

        $persisted = Product::query()
            ->with(['category', 'variants', 'seller.owner'])
            ->find($product->getKey());

        if (! $persisted) {
            throw ValidationException::withMessages([
                'product' => 'A database-backed product is required.',
            ]);
        }

        return $persisted;
    }

    private function persistedSellerOrder(SellerOrder $sellerOrder): SellerOrder
    {
        if (! $sellerOrder->exists || ! $sellerOrder->getKey()) {
            throw ValidationException::withMessages([
                'seller_order' => 'A database-backed seller order is required.',
            ]);
        }

        $persisted = SellerOrder::query()
            ->with(['order.buyer', 'seller.owner', 'shipment'])
            ->find($sellerOrder->getKey());

        if (! $persisted) {
            throw ValidationException::withMessages([
                'seller_order' => 'A database-backed seller order is required.',
            ]);
        }

        return $persisted;
    }

    private function persistedShipment(Shipment $shipment): Shipment
    {
        if (! $shipment->exists || ! $shipment->getKey()) {
            throw ValidationException::withMessages([
                'shipment' => 'A database-backed shipment is required.',
            ]);
        }

        $persisted = Shipment::query()
            ->with([
                'sellerOrder.order',
                'sellerOrder.seller',
                'pickupRider.user',
                'rider.user',
                'provider.owner',
            ])
            ->find($shipment->getKey());

        if (! $persisted) {
            throw ValidationException::withMessages([
                'shipment' => 'A database-backed shipment is required.',
            ]);
        }

        return $persisted;
    }

    private function persistedRider(Rider $rider): Rider
    {
        if (! $rider->exists || ! $rider->getKey()) {
            throw ValidationException::withMessages([
                'rider' => 'A database-backed rider is required.',
            ]);
        }

        $persisted = Rider::query()->with('user')->find($rider->getKey());
        if (! $persisted) {
            throw ValidationException::withMessages([
                'rider' => 'A database-backed rider is required.',
            ]);
        }

        return $persisted;
    }

    private function persistedComplaint(Complaint $complaint): Complaint
    {
        if (! $complaint->exists || ! $complaint->getKey()) {
            throw ValidationException::withMessages([
                'complaint' => 'A database-backed complaint is required.',
            ]);
        }

        $persisted = Complaint::query()
            ->with([
                'complainant',
                'order',
                'sellerOrder',
                'shipment',
                'seller',
                'product',
                'rider',
                'logisticsProvider',
                'assignee',
            ])
            ->find($complaint->getKey());

        if (! $persisted) {
            throw ValidationException::withMessages([
                'complaint' => 'A database-backed complaint is required.',
            ]);
        }

        return $persisted;
    }

    private function ensureApprovedProvider(LogisticsProvider $provider): void
    {
        abort_unless($provider->status === 'approved', 403, 'The logistics provider is not approved.');
        $this->ensureEligibleRole($provider->owner, 'logistics');
    }

    private function ensureActiveRider(Rider $rider): void
    {
        abort_unless($rider->is_active && $rider->approved_at, 403, 'The rider is not active and approved.');
        $this->ensureEligibleRole($rider->user, 'rider');
    }

    private function requiredModel(array $context, string $key, string $class): mixed
    {
        $model = $this->optionalModel($context, $key, $class);
        if (! $model) {
            throw ValidationException::withMessages([$key => "The {$key} context is required."]);
        }

        return $model;
    }

    private function optionalModel(array $context, string $key, string $class): mixed
    {
        $model = $context[$key] ?? null;
        if ($model !== null && ! $model instanceof $class) {
            throw ValidationException::withMessages([$key => "The {$key} context is invalid."]);
        }

        return $model;
    }

    private function baseResolved(): array
    {
        return [
            'key' => null,
            'order' => null,
            'seller_order' => null,
            'shipment' => null,
            'complaint' => null,
            'seller' => null,
            'product' => null,
            'participants' => [],
        ];
    }

    private function contextSnapshot(array $resolved): array
    {
        return array_filter([
            'order' => $resolved['order'] ? [
                'id' => $resolved['order']->id,
                'order_number' => $resolved['order']->order_number,
            ] : null,
            'seller_order' => $resolved['seller_order'] ? ['id' => $resolved['seller_order']->id] : null,
            'shipment' => $resolved['shipment'] ? [
                'id' => $resolved['shipment']->id,
                'tracking_code' => $resolved['shipment']->tracking_code,
            ] : null,
            'complaint' => $resolved['complaint'] ? [
                'id' => $resolved['complaint']->id,
                'reference' => $resolved['complaint']->reference,
            ] : null,
            'seller' => $resolved['seller'] ? [
                'id' => $resolved['seller']->id,
                'name' => $resolved['seller']->name,
            ] : null,
            'product' => $resolved['product'] ? [
                'id' => $resolved['product']->id,
                'name' => $resolved['product']->name,
            ] : null,
        ], fn ($value) => $value !== null);
    }
}
