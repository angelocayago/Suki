<?php

namespace App\Services\Admin;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Enums\ComplaintWaitingOn;
use App\Models\Complaint;
use App\Models\ComplaintEvidence;
use App\Models\ComplaintEvent;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\SellerWalletService;
use App\Services\DomainNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Throwable;

class ComplaintService
{
    public function createComplaint(
        User $actor,
        ComplaintCategory $category,
        string $subject,
        string $description,
        ?Order $order = null,
        ?SellerOrder $sellerOrder = null,
        ?Shipment $shipment = null,
        ?Seller $seller = null,
        ?Product $product = null,
        ?Rider $rider = null,
        ?LogisticsProvider $logisticsProvider = null
    ): Complaint {
        return DB::transaction(function () use (
            $actor,
            $category,
            $subject,
            $description,
            $order,
            $sellerOrder,
            $shipment,
            $seller,
            $product,
            $rider,
            $logisticsProvider
        ) {
            $this->ensureActiveActor($actor);
            $this->ensureRequiredText($subject, 'subject');
            $this->ensureRequiredText($description, 'description');

            $context = $this->resolveContext(
                $order,
                $sellerOrder,
                $shipment,
                $seller,
                $product,
                $rider,
                $logisticsProvider
            );
            $complainantRole = $this->authorizeComplainant($actor, $context);

            $complaint = Complaint::create([
                'reference' => $this->reference(),
                'complainant_id' => $actor->id,
                'complainant_role' => $complainantRole,
                'order_id' => $context['order']?->id,
                'seller_order_id' => $context['seller_order']?->id,
                'shipment_id' => $context['shipment']?->id,
                'buyer_id' => $context['order']?->buyer_id,
                'seller_id' => $context['seller']?->id,
                'product_id' => $context['product']?->id,
                'rider_id' => $context['rider']?->id,
                'rider_role' => $context['rider_role'],
                'logistics_provider_id' => $context['logistics_provider']?->id,
                'category' => $category->value,
                'status' => ComplaintStatus::Open->value,
                'subject' => trim($subject),
                'description' => trim($description),
                'context_snapshot' => $this->snapshot($context),
            ]);

            $this->record($complaint, $actor, 'COMPLAINT_CREATED', [
                'new_status' => ComplaintStatus::Open,
                'metadata' => [
                    'category' => $category->value,
                    'complainant_role' => $complainantRole,
                    'order_id' => $complaint->order_id,
                    'seller_order_id' => $complaint->seller_order_id,
                    'shipment_id' => $complaint->shipment_id,
                ],
            ]);

            app(DomainNotificationService::class)->adminsAfterCommit('Complaint requires attention', 'A complaint was submitted.', ['complaint_id' => $complaint->id]);

            return $complaint->fresh(['complainant', 'order', 'sellerOrder', 'shipment', 'events']);
        }, 3);
    }

    public function assignAdmin(Complaint $complaint, User $actor, User $assignee): Complaint
    {
        return DB::transaction(function () use ($complaint, $actor, $assignee) {
            $this->ensureAdmin($actor);
            $this->ensureAdmin($assignee);
            $locked = $this->lockedActiveComplaint($complaint);
            $previousAssignee = $locked->assigned_to;

            if ((int) $previousAssignee === (int) $assignee->id) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'This administrator is already assigned to the complaint.',
                ]);
            }

            $locked->update([
                'assigned_to' => $assignee->id,
                'assigned_at' => now(),
            ]);
            $this->record($locked, $actor, 'COMPLAINT_ASSIGNED', [
                'previous_assignee_id' => $previousAssignee,
                'new_assignee_id' => $assignee->id,
            ]);

            return $locked->fresh(['assignee']);
        }, 3);
    }

    public function changeStatus(
        Complaint $complaint,
        User $actor,
        ComplaintStatus $next,
        ?ComplaintWaitingOn $waitingOn = null,
        ?string $notes = null
    ): Complaint {
        return DB::transaction(function () use ($complaint, $actor, $next, $waitingOn, $notes) {
            $this->ensureAdmin($actor);
            $locked = $this->lockedActiveComplaint($complaint);
            $previous = $locked->status;

            if ($next->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => 'Use the resolve or dismiss action to close a complaint.',
                ]);
            }

            $this->ensureTransition($previous, $next);

            if ($next === ComplaintStatus::WaitingResponse && ! $waitingOn) {
                throw ValidationException::withMessages([
                    'waiting_on' => 'Select the party whose response is required.',
                ]);
            }

            if ($next !== ComplaintStatus::WaitingResponse && $waitingOn) {
                throw ValidationException::withMessages([
                    'waiting_on' => 'A waiting party is only valid for WAITING_RESPONSE.',
                ]);
            }

            $locked->update([
                'status' => $next->value,
                'waiting_on' => $waitingOn?->value,
            ]);
            $this->record(
                $locked,
                $actor,
                $next === ComplaintStatus::WaitingResponse ? 'WAITING_RESPONSE' : 'STATUS_CHANGED',
                [
                    'previous_status' => $previous,
                    'new_status' => $next,
                    'waiting_on' => $waitingOn,
                    'notes' => filled($notes) ? trim($notes) : null,
                ]
            );

            app(DomainNotificationService::class)->afterCommit($locked->complainant_id, 'Complaint update', 'Your complaint status was updated.', ['complaint_id' => $locked->id, 'status' => $next->value]);

            return $locked->fresh();
        }, 3);
    }

    public function resolve(Complaint $complaint, User $actor, string $notes): Complaint
    {
        return $this->close($complaint, $actor, ComplaintStatus::Resolved, $notes, 'COMPLAINT_RESOLVED');
    }

    public function dismiss(Complaint $complaint, User $actor, string $notes): Complaint
    {
        return $this->close($complaint, $actor, ComplaintStatus::Dismissed, $notes, 'COMPLAINT_DISMISSED');
    }

    public function ensureCanAccess(Complaint $complaint, User $actor): void
    {
        $this->ensureActiveActor($actor);

        if ($actor->isAdmin()) {
            return;
        }

        abort_unless((int) $complaint->complainant_id === (int) $actor->id, 403);
    }

    public function ensureCanAccessEvidence(
        Complaint $complaint,
        ComplaintEvidence $evidence,
        User $actor
    ): void {
        $this->ensureCanAccess($complaint, $actor);
        abort_unless((int) $evidence->complaint_id === (int) $complaint->id, 404);

        if (! $actor->isAdmin()) {
            abort_unless((int) $evidence->uploaded_by === (int) $actor->id, 403);
        }
    }

    public function addEvidence(
        Complaint $complaint,
        User $actor,
        UploadedFile $file,
        ?string $description = null
    ): ComplaintEvidence {
        $this->ensureCanAccess($complaint, $actor);
        $disk = (string) config('complaints.evidence_disk', 'local');

        if ($disk === 'public' || config("filesystems.disks.{$disk}.visibility") === 'public') {
            throw ValidationException::withMessages([
                'evidence' => 'Complaint evidence must use private storage.',
            ]);
        }

        $path = null;

        try {
            return DB::transaction(function () use ($complaint, $actor, $file, $description, $disk, &$path) {
                $locked = $this->lockedActiveComplaint($complaint);
                $checksum = hash_file('sha256', $file->getRealPath());
                $path = $file->store('complaint-evidence/'.$locked->id, $disk);

                if (! $path) {
                    throw ValidationException::withMessages([
                        'evidence' => 'The evidence file could not be stored.',
                    ]);
                }

                $evidence = ComplaintEvidence::create([
                    'complaint_id' => $locked->id,
                    'uploaded_by' => $actor->id,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                    'mime_type' => (string) $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                    'checksum' => $checksum,
                    'description' => filled($description) ? trim($description) : null,
                ]);

                $this->record($locked, $actor, 'EVIDENCE_ATTACHED', [
                    'metadata' => [
                        'evidence_id' => $evidence->id,
                        'original_name' => $evidence->original_name,
                        'mime_type' => $evidence->mime_type,
                        'size' => $evidence->size,
                        'checksum' => $evidence->checksum,
                    ],
                ]);

                return $evidence->fresh(['complaint', 'uploader']);
            }, 3);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }
    }

    private function close(
        Complaint $complaint,
        User $actor,
        ComplaintStatus $next,
        string $notes,
        string $action
    ): Complaint {
        return DB::transaction(function () use ($complaint, $actor, $next, $notes, $action) {
            $this->ensureAdmin($actor);
            $this->ensureRequiredText($notes, 'resolution_notes');
            $locked = $this->lockedActiveComplaint($complaint);
            $previous = $locked->status;
            $this->ensureTransition($previous, $next);

            $locked->update([
                'status' => $next->value,
                'waiting_on' => null,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'resolution_notes' => trim($notes),
            ]);
            $this->record($locked, $actor, $action, [
                'previous_status' => $previous,
                'new_status' => $next,
                'notes' => $locked->resolution_notes,
            ]);

            // Releasing an earning is an explicit consequence of resolving/dismissing
            // a complaint, never a side effect of a wallet page view.
            if ($locked->seller_order_id) {
                app(SellerWalletService::class)->reconcileSellerOrder(
                    SellerOrder::query()->findOrFail($locked->seller_order_id)
                );
            }

            app(DomainNotificationService::class)->afterCommit($locked->complainant_id, 'Complaint update', 'Your complaint has been closed.', ['complaint_id' => $locked->id, 'status' => $next->value]);

            return $locked->fresh(['resolver']);
        }, 3);
    }

    private function resolveContext(
        ?Order $order,
        ?SellerOrder $sellerOrder,
        ?Shipment $shipment,
        ?Seller $seller,
        ?Product $product,
        ?Rider $rider,
        ?LogisticsProvider $logisticsProvider
    ): array {
        $shipmentId = $shipment?->id;
        $sellerOrderId = $sellerOrder?->id ?? $shipment?->seller_order_id;

        if ($sellerOrderId && ! $order) {
            $order = SellerOrder::query()->findOrFail($sellerOrderId)->order;
        }

        $lockedOrder = $order
            ? Order::query()->lockForUpdate()->findOrFail($order->id)
            : null;
        $lockedSellerOrder = $sellerOrderId
            ? SellerOrder::query()->lockForUpdate()->findOrFail($sellerOrderId)
            : null;
        $lockedShipment = $shipmentId
            ? Shipment::query()->lockForUpdate()->findOrFail($shipmentId)
            : null;

        if ($lockedSellerOrder && $lockedOrder && (int) $lockedSellerOrder->order_id !== (int) $lockedOrder->id) {
            throw ValidationException::withMessages(['seller_order' => 'The seller order does not belong to the selected order.']);
        }
        if ($lockedShipment && $lockedSellerOrder && (int) $lockedShipment->seller_order_id !== (int) $lockedSellerOrder->id) {
            throw ValidationException::withMessages(['shipment' => 'The shipment does not belong to the selected seller order.']);
        }

        $specificContext = $seller || $product || $rider || $logisticsProvider || $lockedShipment;
        if ($lockedOrder && ! $lockedSellerOrder && $specificContext) {
            $sellerOrders = $lockedOrder->sellerOrders()->orderBy('id')->get();
            if ($sellerOrders->count() !== 1) {
                throw ValidationException::withMessages([
                    'seller_order' => 'Select a seller order before linking seller, product, rider, or logistics details.',
                ]);
            }
            $lockedSellerOrder = SellerOrder::query()->lockForUpdate()->findOrFail($sellerOrders->first()->id);
        }

        if ($lockedSellerOrder && ! $lockedOrder) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($lockedSellerOrder->order_id);
        }

        $resolvedSeller = $lockedSellerOrder?->seller;
        if ($seller && $resolvedSeller && (int) $seller->id !== (int) $resolvedSeller->id) {
            throw ValidationException::withMessages(['seller' => 'The seller does not belong to the selected seller order.']);
        }
        $resolvedSeller ??= $seller ? Seller::query()->findOrFail($seller->id) : null;

        $resolvedProduct = $product ? Product::query()->findOrFail($product->id) : null;
        if ($resolvedProduct && $lockedOrder) {
            $itemQuery = $lockedOrder->items()->where('product_id', $resolvedProduct->id);
            if ($lockedSellerOrder) {
                $itemQuery->where('seller_order_id', $lockedSellerOrder->id);
            }
            if (! $itemQuery->exists()) {
                throw ValidationException::withMessages(['product' => 'The product is not part of the selected order context.']);
            }
        }
        if ($resolvedProduct && $resolvedSeller && (int) $resolvedProduct->seller_id !== (int) $resolvedSeller->id) {
            throw ValidationException::withMessages(['product' => 'The product does not belong to the selected seller.']);
        }

        if ($lockedSellerOrder && ! $lockedShipment) {
            $candidate = $lockedSellerOrder->shipment;
            if (($rider || $logisticsProvider) && ! $candidate) {
                throw ValidationException::withMessages(['shipment' => 'A shipment is required for rider or logistics context.']);
            }
            $lockedShipment = $candidate;
        }

        $resolvedRider = $rider ? Rider::query()->findOrFail($rider->id) : null;
        $riderRole = null;
        if ($resolvedRider) {
            if (! $lockedShipment) {
                throw ValidationException::withMessages(['shipment' => 'A shipment is required for rider context.']);
            }
            if ((int) $lockedShipment->pickup_rider_id === (int) $resolvedRider->id) {
                $riderRole = 'PICKUP';
            } elseif ((int) $lockedShipment->rider_id === (int) $resolvedRider->id) {
                $riderRole = 'DELIVERY';
            } else {
                throw ValidationException::withMessages(['rider' => 'The rider is not assigned to the selected shipment.']);
            }
        }

        $resolvedProvider = $logisticsProvider
            ? LogisticsProvider::query()->findOrFail($logisticsProvider->id)
            : $lockedShipment?->provider;
        if ($resolvedProvider && $lockedShipment && (int) $lockedShipment->logistics_provider_id !== (int) $resolvedProvider->id) {
            throw ValidationException::withMessages(['logistics_provider' => 'The logistics provider does not handle the selected shipment.']);
        }

        return [
            'order' => $lockedOrder,
            'seller_order' => $lockedSellerOrder,
            'shipment' => $lockedShipment,
            'seller' => $resolvedSeller,
            'product' => $resolvedProduct,
            'rider' => $resolvedRider,
            'rider_role' => $riderRole,
            'logistics_provider' => $resolvedProvider,
        ];
    }

    private function authorizeComplainant(User $actor, array &$context): string
    {
        if ($actor->isAdmin()) {
            return 'ADMIN';
        }
        if ($actor->hasRole('buyer') && $context['order'] && (int) $context['order']->buyer_id === (int) $actor->id) {
            return 'BUYER';
        }
        if ($actor->hasRole('seller') && $context['seller_order'] && (int) $context['seller_order']->seller->user_id === (int) $actor->id) {
            $context['seller'] ??= $context['seller_order']->seller;

            return 'SELLER';
        }
        if ($actor->hasRole('rider') && $context['shipment']) {
            $assigned = collect([$context['shipment']->pickupRider, $context['shipment']->rider])
                ->filter()
                ->first(fn (Rider $assignedRider) => (int) $assignedRider->user_id === (int) $actor->id);
            if ($assigned) {
                $context['rider'] ??= $assigned;
                $context['rider_role'] ??= (int) $context['shipment']->pickup_rider_id === (int) $assigned->id ? 'PICKUP' : 'DELIVERY';

                return 'RIDER';
            }
        }
        if ($actor->hasRole('logistics') && $context['shipment']?->provider
            && (int) $context['shipment']->provider->user_id === (int) $actor->id) {
            $context['logistics_provider'] ??= $context['shipment']->provider;

            return 'LOGISTICS';
        }

        abort(403, 'The user is not authorized for the selected complaint context.');
    }

    private function snapshot(array $context): array
    {
        return [
            'order' => $context['order'] ? [
                'id' => $context['order']->id,
                'number' => $context['order']->order_number,
                'status' => $context['order']->status,
                'buyer_id' => $context['order']->buyer_id,
                'buyer_name' => $context['order']->buyer?->full_name,
            ] : null,
            'seller_order' => $context['seller_order'] ? [
                'id' => $context['seller_order']->id,
                'status' => $context['seller_order']->workflow_status?->value,
            ] : null,
            'seller' => $context['seller'] ? ['id' => $context['seller']->id, 'name' => $context['seller']->name] : null,
            'product' => $context['product'] ? ['id' => $context['product']->id, 'name' => $context['product']->name] : null,
            'shipment' => $context['shipment'] ? [
                'id' => $context['shipment']->id,
                'tracking_code' => $context['shipment']->tracking_code,
                'status' => $context['shipment']->workflow_status?->value,
            ] : null,
            'rider' => $context['rider'] ? [
                'id' => $context['rider']->id,
                'name' => $context['rider']->user?->full_name,
                'role' => $context['rider_role'],
            ] : null,
            'logistics_provider' => $context['logistics_provider'] ? [
                'id' => $context['logistics_provider']->id,
                'name' => $context['logistics_provider']->name,
            ] : null,
        ];
    }

    private function lockedActiveComplaint(Complaint $complaint): Complaint
    {
        $locked = Complaint::query()->lockForUpdate()->findOrFail($complaint->id);
        if ($locked->status->isTerminal()) {
            throw ValidationException::withMessages(['status' => 'Closed complaints cannot be changed.']);
        }

        return $locked;
    }

    private function ensureTransition(ComplaintStatus $previous, ComplaintStatus $next): void
    {
        if (! $previous->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => "The complaint cannot move from {$previous->value} to {$next->value}.",
            ]);
        }
    }

    private function ensureActiveActor(User $actor): void
    {
        abort_unless($actor->status === 'active' && ! $actor->is_suspended, 403);
    }

    private function ensureAdmin(User $actor): void
    {
        abort_unless($actor->isAdmin() && $actor->status === 'active' && ! $actor->is_suspended, 403);
    }

    private function ensureRequiredText(string $value, string $field): void
    {
        if (blank($value)) {
            throw ValidationException::withMessages([$field => 'This field is required.']);
        }
    }

    private function record(Complaint $complaint, User $actor, string $action, array $data = []): void
    {
        ComplaintEvent::create([
            'complaint_id' => $complaint->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'previous_status' => ($data['previous_status'] ?? null)?->value,
            'new_status' => ($data['new_status'] ?? null)?->value,
            'previous_assignee_id' => $data['previous_assignee_id'] ?? null,
            'new_assignee_id' => $data['new_assignee_id'] ?? null,
            'waiting_on' => ($data['waiting_on'] ?? null)?->value,
            'notes' => $data['notes'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    private function reference(): string
    {
        do {
            $reference = 'CMP-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
        } while (Complaint::where('reference', $reference)->exists());

        return $reference;
    }
}
