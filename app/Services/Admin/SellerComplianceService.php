<?php

namespace App\Services\Admin;

use App\Enums\SellerViolationCategory;
use App\Enums\SellerViolationSeverity;
use App\Enums\SellerViolationStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerComplianceEvent;
use App\Models\SellerOrder;
use App\Models\SellerViolation;
use App\Models\SellerViolationEvidence;
use App\Models\SellerWarning;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SellerComplianceService
{
    private const BLOCKING_ORDER_STATUSES = [
        'PLACED',
        'CONFIRMED',
        'PREPARING',
        'READY_FOR_PICKUP',
    ];

    public function __construct(
        private readonly AccountStatusService $accountStatus
    ) {}

    public function createViolation(
        Seller $seller,
        User $actor,
        SellerViolationCategory $category,
        SellerViolationSeverity $severity,
        string $reason,
        ?string $details = null,
        ?Product $product = null
    ): SellerViolation {
        return DB::transaction(function () use ($seller, $actor, $category, $severity, $reason, $details, $product) {
            $this->ensureAdmin($actor);
            $this->ensureReason($reason, 'reason');

            $lockedSeller = Seller::query()->lockForUpdate()->findOrFail($seller->id);
            if ($product && (int) $product->seller_id !== (int) $lockedSeller->id) {
                throw ValidationException::withMessages([
                    'product' => 'The related product must belong to the selected seller.',
                ]);
            }

            $violation = SellerViolation::create([
                'reference' => $this->reference(),
                'seller_id' => $lockedSeller->id,
                'product_id' => $product?->id,
                'created_by' => $actor->id,
                'category' => $category->value,
                'severity' => $severity->value,
                'status' => SellerViolationStatus::Open->value,
                'reason' => trim($reason),
                'details' => filled($details) ? trim($details) : null,
            ]);

            $this->record($violation, $actor, 'VIOLATION_CREATED', [
                'new_status' => SellerViolationStatus::Open,
                'new_severity' => $severity,
                'reason' => $violation->reason,
                'metadata' => [
                    'category' => $category->value,
                    'product_id' => $product?->id,
                ],
            ]);

            return $violation->fresh(['seller', 'product', 'creator', 'events']);
        }, 3);
    }

    public function changeSeverity(
        SellerViolation $violation,
        User $actor,
        SellerViolationSeverity $severity,
        ?string $reason = null
    ): SellerViolation {
        return DB::transaction(function () use ($violation, $actor, $severity, $reason) {
            $this->ensureAdmin($actor);
            $locked = $this->lockedOpenViolation($violation);
            $previous = $locked->severity;

            if ($previous === $severity) {
                throw ValidationException::withMessages([
                    'severity' => 'The violation already has this severity.',
                ]);
            }

            $locked->update(['severity' => $severity->value]);
            $this->record($locked, $actor, 'SEVERITY_CHANGED', [
                'previous_severity' => $previous,
                'new_severity' => $severity,
                'reason' => filled($reason) ? trim($reason) : null,
            ]);

            return $locked->fresh();
        }, 3);
    }

    public function addWarning(
        SellerViolation $violation,
        User $actor,
        string $message
    ): SellerWarning {
        return DB::transaction(function () use ($violation, $actor, $message) {
            $this->ensureAdmin($actor);
            $this->ensureReason($message, 'message');
            $locked = $this->lockedOpenViolation($violation);

            $warning = SellerWarning::create([
                'seller_violation_id' => $locked->id,
                'seller_id' => $locked->seller_id,
                'issued_by' => $actor->id,
                'message' => trim($message),
                'issued_at' => now(),
            ]);

            $this->record($locked, $actor, 'WARNING_ISSUED', [
                'reason' => $warning->message,
                'metadata' => ['warning_id' => $warning->id],
            ]);

            return $warning->fresh(['violation', 'seller', 'issuer']);
        }, 3);
    }

    public function acknowledgeWarning(SellerWarning $warning, User $actor): SellerWarning
    {
        return DB::transaction(function () use ($warning, $actor) {
            $locked = SellerWarning::query()
                ->with('violation')
                ->lockForUpdate()
                ->findOrFail($warning->id);

            abort_unless((int) $locked->seller->user_id === (int) $actor->id, 403);

            if ($locked->acknowledged_at) {
                return $locked;
            }

            $locked->update(['acknowledged_at' => now()]);
            $this->record($locked->violation, $actor, 'WARNING_ACKNOWLEDGED', [
                'metadata' => ['warning_id' => $locked->id],
            ]);

            return $locked->fresh();
        }, 3);
    }

    public function addEvidence(
        SellerViolation $violation,
        User $actor,
        UploadedFile $file,
        ?string $description = null
    ): SellerViolationEvidence {
        $this->ensureAdmin($actor);

        $disk = (string) config('compliance.evidence_disk', 'local');
        $path = null;

        try {
            return DB::transaction(function () use ($violation, $actor, $file, $description, $disk, &$path) {
                $locked = $this->lockedOpenViolation($violation);
                $checksum = hash_file('sha256', $file->getRealPath());
                $path = $file->store('seller-compliance/'.$locked->id, $disk);

                if (! $path) {
                    throw ValidationException::withMessages([
                        'evidence' => 'The evidence file could not be stored.',
                    ]);
                }

                $evidence = SellerViolationEvidence::create([
                    'seller_violation_id' => $locked->id,
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

                return $evidence->fresh(['violation', 'uploader']);
            }, 3);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }
    }

    public function suspensionImpact(SellerViolation $violation): array
    {
        $seller = Seller::query()
            ->with(['owner.roles', 'owner.logisticsProvider'])
            ->findOrFail($violation->seller_id);

        return $this->buildSuspensionImpact($violation, $seller->owner);
    }

    public function suspendSeller(
        SellerViolation $violation,
        User $actor,
        string $reason,
        bool $confirmed
    ): SellerViolation {
        return DB::transaction(function () use ($violation, $actor, $reason, $confirmed) {
            $this->ensureAdmin($actor);
            $this->ensureReason($reason, 'reason');

            if (! $confirmed) {
                throw ValidationException::withMessages([
                    'confirmation' => 'Confirm that suspension affects the account and all related profiles.',
                ]);
            }

            $lockedViolation = $this->lockedOpenViolation($violation);
            $seller = Seller::query()->findOrFail($lockedViolation->seller_id);
            $owner = User::query()->lockForUpdate()->findOrFail($seller->user_id);
            $stores = Seller::query()
                ->where('user_id', $owner->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $storeIds = $stores->pluck('id');

            ProductVariant::query()
                ->whereHas('product', fn ($query) => $query->whereIn('seller_id', $storeIds))
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $blockingOrders = SellerOrder::query()
                ->whereIn('seller_id', $storeIds)
                ->whereIn('workflow_status', self::BLOCKING_ORDER_STATUSES)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'seller_id', 'workflow_status']);

            if ($blockingOrders->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'active_orders' => 'Seller suspension is blocked until all active seller orders have been picked up.',
                ]);
            }

            $impact = $this->buildSuspensionImpact($lockedViolation, $owner);
            $accountEvent = $this->accountStatus->suspendSellerForCompliance(
                $owner,
                $actor,
                trim($reason),
                [
                    'seller_violation_id' => $lockedViolation->id,
                    'seller_violation_reference' => $lockedViolation->reference,
                    'triggering_seller_id' => $lockedViolation->seller_id,
                    'affected_seller_ids' => $storeIds->all(),
                    'active_order_snapshot' => $impact['orders_by_status'],
                    'affected_roles' => $impact['roles'],
                    'logistics_provider_id' => $impact['logistics_provider']['id'] ?? null,
                ]
            );

            $this->record($lockedViolation, $actor, 'SELLER_SUSPENDED', [
                'account_status_event_id' => $accountEvent->id,
                'reason' => trim($reason),
                'metadata' => [
                    'user_id' => $owner->id,
                    'affected_seller_ids' => $storeIds->all(),
                    'active_order_snapshot' => $impact['orders_by_status'],
                    'affected_roles' => $impact['roles'],
                    'logistics_provider_id' => $impact['logistics_provider']['id'] ?? null,
                ],
            ]);

            return $lockedViolation->fresh(['seller.owner', 'events.accountStatusEvent']);
        }, 3);
    }

    public function resolve(SellerViolation $violation, User $actor, string $notes): SellerViolation
    {
        return $this->close($violation, $actor, SellerViolationStatus::Resolved, $notes, 'CASE_RESOLVED');
    }

    public function dismiss(SellerViolation $violation, User $actor, string $notes): SellerViolation
    {
        return $this->close($violation, $actor, SellerViolationStatus::Dismissed, $notes, 'CASE_DISMISSED');
    }

    private function close(
        SellerViolation $violation,
        User $actor,
        SellerViolationStatus $status,
        string $notes,
        string $action
    ): SellerViolation {
        return DB::transaction(function () use ($violation, $actor, $status, $notes, $action) {
            $this->ensureAdmin($actor);
            $this->ensureReason($notes, 'resolution_notes');
            $locked = $this->lockedOpenViolation($violation);
            $previous = $locked->status;

            $locked->update([
                'status' => $status->value,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
                'resolution_notes' => trim($notes),
            ]);

            $this->record($locked, $actor, $action, [
                'previous_status' => $previous,
                'new_status' => $status,
                'reason' => $locked->resolution_notes,
            ]);

            return $locked->fresh(['resolver']);
        }, 3);
    }

    private function lockedOpenViolation(SellerViolation $violation): SellerViolation
    {
        $locked = SellerViolation::query()->lockForUpdate()->findOrFail($violation->id);

        if ($locked->status !== SellerViolationStatus::Open) {
            throw ValidationException::withMessages([
                'status' => 'Only an open compliance case can be changed.',
            ]);
        }

        return $locked;
    }

    private function record(SellerViolation $violation, User $actor, string $action, array $data = []): void
    {
        SellerComplianceEvent::create([
            'seller_violation_id' => $violation->id,
            'seller_id' => $violation->seller_id,
            'actor_id' => $actor->id,
            'account_status_event_id' => $data['account_status_event_id'] ?? null,
            'action' => $action,
            'previous_status' => ($data['previous_status'] ?? null)?->value,
            'new_status' => ($data['new_status'] ?? null)?->value,
            'previous_severity' => ($data['previous_severity'] ?? null)?->value,
            'new_severity' => ($data['new_severity'] ?? null)?->value,
            'reason' => $data['reason'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    private function buildSuspensionImpact(SellerViolation $violation, User $owner): array
    {
        $owner->loadMissing(['roles', 'logisticsProvider']);
        $stores = Seller::query()->where('user_id', $owner->id)->orderBy('name')->get();
        $storeIds = $stores->pluck('id');
        $counts = SellerOrder::query()
            ->whereIn('seller_id', $storeIds)
            ->whereIn('workflow_status', self::BLOCKING_ORDER_STATUSES)
            ->selectRaw('seller_id, workflow_status as status_key, COUNT(*) as aggregate')
            ->groupBy('seller_id', 'workflow_status')
            ->get();

        $ordersByStatus = collect(self::BLOCKING_ORDER_STATUSES)
            ->mapWithKeys(fn ($status) => [$status => (int) $counts->where('status_key', $status)->sum('aggregate')])
            ->all();
        $storeImpact = $stores->map(function (Seller $store) use ($counts) {
            $statusCounts = collect(self::BLOCKING_ORDER_STATUSES)
                ->mapWithKeys(fn ($status) => [
                    $status => (int) $counts
                        ->where('seller_id', $store->id)
                        ->where('status_key', $status)
                        ->sum('aggregate'),
                ])
                ->all();

            return [
                'id' => $store->id,
                'name' => $store->name,
                'status' => $store->status,
                'orders_by_status' => $statusCounts,
                'active_orders_count' => array_sum($statusCounts),
            ];
        })->all();
        $roles = $owner->roles->pluck('name')
            ->push($owner->role)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
        $provider = $owner->logisticsProvider;
        $blockingCount = array_sum($ordersByStatus);

        return [
            'user_id' => $owner->id,
            'account_status' => $owner->status,
            'is_suspended' => (bool) $owner->is_suspended,
            'stores' => $storeImpact,
            'roles' => $roles,
            'logistics_provider' => $provider ? [
                'id' => $provider->id,
                'name' => $provider->name,
                'status' => $provider->status,
            ] : null,
            'orders_by_status' => $ordersByStatus,
            'active_orders_count' => $blockingCount,
            'can_suspend' => $violation->status === SellerViolationStatus::Open
                && $owner->status === 'active'
                && ! $owner->is_suspended
                && $blockingCount === 0,
        ];
    }

    private function ensureAdmin(User $actor): void
    {
        abort_unless($actor->isAdmin() && $actor->status === 'active' && ! $actor->is_suspended, 403);
    }

    private function ensureReason(string $value, string $field): void
    {
        if (blank($value)) {
            throw ValidationException::withMessages([
                $field => 'This field is required.',
            ]);
        }
    }

    private function reference(): string
    {
        do {
            $reference = 'SC-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
        } while (SellerViolation::where('reference', $reference)->exists());

        return $reference;
    }
}
