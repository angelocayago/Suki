<?php

namespace App\Services\Admin;

use App\Models\AccountStatusEvent;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountStatusService
{
    private const TRANSITIONS = [
        'active' => ['suspended', 'inactive'],
        'suspended' => ['active', 'inactive'],
        'inactive' => ['active'],
    ];

    public function changeStatus(
        User $user,
        string $newStatus,
        User $actor,
        ?string $reason = null,
        string $source = 'admin_portal'
    ): User {
        return DB::transaction(function () use ($user, $newStatus, $actor, $reason, $source) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            $this->ensureAdminActor($actor);
            $this->ensureSellerSuspensionUsesCompliance($lockedUser, $newStatus);
            $this->ensureAllowedTransition($lockedUser, $newStatus, $reason);
            $this->ensureAdminSafeguards($lockedUser, $newStatus, $actor);

            $this->applyStatus($lockedUser, $newStatus, $actor, $reason, $source);

            return $lockedUser->fresh();
        });
    }

    public function suspendSellerForCompliance(
        User $user,
        User $actor,
        string $reason,
        array $metadata
    ): AccountStatusEvent {
        return DB::transaction(function () use ($user, $actor, $reason, $metadata) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            $this->ensureAdminActor($actor);

            if (! $lockedUser->sellers()->exists()) {
                throw ValidationException::withMessages([
                    'seller' => 'The selected account does not own a seller profile.',
                ]);
            }

            if (blank($metadata['seller_violation_id'] ?? null)) {
                throw ValidationException::withMessages([
                    'violation' => 'A compliance case is required for seller suspension.',
                ]);
            }

            $this->ensureAllowedTransition($lockedUser, 'suspended', $reason);
            $this->ensureAdminSafeguards($lockedUser, 'suspended', $actor);

            return $this->applyStatus(
                $lockedUser,
                'suspended',
                $actor,
                $reason,
                'seller_compliance',
                $metadata
            );
        });
    }

    public function approveApplication(Application $application, User $actor): Application
    {
        return DB::transaction(function () use ($application, $actor) {
            $this->ensureAdminActor($actor);

            $lockedApplication = Application::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($application->getKey());

            $this->ensureReviewable($lockedApplication);
            $user = User::query()->lockForUpdate()->findOrFail($lockedApplication->user_id);
            $previousStatus = $user->status;

            if ($user->requiresContactVerification() && ! $user->hasVerifiedContacts()) {
                throw ValidationException::withMessages([
                    'application' => 'The applicant must verify both email and mobile number before approval.',
                ]);
            }

            if ($previousStatus !== 'pending') {
                throw ValidationException::withMessages([
                    'application' => 'Only a pending account can be approved.',
                ]);
            }

            $this->writeUserStatus($user, 'active');
            $this->synchronizeApprovedProfile($user, $lockedApplication->application_type);

            if ($lockedApplication->application_type === 'seller') {
                $lockedApplication->sellerDocuments()
                    ->where('verification_status', 'pending')
                    ->update([
                        'verification_status' => 'approved',
                        'reviewed_by' => $actor->id,
                        'reviewed_at' => now(),
                        'rejection_reason' => null,
                    ]);
            }

            $lockedApplication->update([
                'status' => 'approved',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->recordEvent(
                $user,
                $actor,
                $previousStatus,
                'active',
                'Registration application approved.',
                'application_approval',
                $lockedApplication
            );

            return $lockedApplication->fresh(['user']);
        });
    }

    public function rejectApplication(Application $application, User $actor, string $reason): Application
    {
        return DB::transaction(function () use ($application, $actor, $reason) {
            $this->ensureAdminActor($actor);

            $lockedApplication = Application::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($application->getKey());

            $this->ensureReviewable($lockedApplication);
            $user = User::query()->lockForUpdate()->findOrFail($lockedApplication->user_id);
            $previousStatus = $user->status;

            if ($previousStatus !== 'pending') {
                throw ValidationException::withMessages([
                    'application' => 'Only a pending account can be rejected.',
                ]);
            }

            $this->writeUserStatus($user, 'rejected');

            if ($lockedApplication->application_type === 'seller') {
                $user->sellers()->where('status', 'pending')->update([
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                ]);

                $lockedApplication->sellerDocuments()
                    ->where('verification_status', 'pending')
                    ->update([
                        'verification_status' => 'rejected',
                        'reviewed_by' => $actor->id,
                        'reviewed_at' => now(),
                        'rejection_reason' => $reason,
                    ]);
            }

            if ($lockedApplication->application_type === 'logistics') {
                $user->logisticsProvider()->where('status', 'pending')->update([
                    'status' => 'rejected',
                    'rejection_reason' => $reason,
                ]);
            }

            $lockedApplication->update([
                'status' => 'rejected',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->recordEvent(
                $user,
                $actor,
                $previousStatus,
                'rejected',
                $reason,
                'application_rejection',
                $lockedApplication
            );

            return $lockedApplication->fresh(['user']);
        });
    }

    private function applyStatus(
        User $user,
        string $newStatus,
        User $actor,
        ?string $reason,
        string $source,
        array $eventMetadata = []
    ): AccountStatusEvent {
        $previousStatus = $user->status;
        $profileMetadata = $this->synchronizeOperationalProfiles($user, $newStatus);

        $this->writeUserStatus($user, $newStatus);
        return $this->recordEvent(
            $user,
            $actor,
            $previousStatus,
            $newStatus,
            $reason,
            $source,
            null,
            array_merge($eventMetadata, $profileMetadata)
        );
    }

    private function writeUserStatus(User $user, string $status): void
    {
        $user->update([
            'status' => $status,
            'is_suspended' => $status === 'suspended',
        ]);
    }

    private function synchronizeOperationalProfiles(User $user, string $status): array
    {
        $metadata = [];
        $profileStatus = $status === 'active' ? 'approved' : 'suspended';

        foreach ($user->sellers()->lockForUpdate()->get() as $seller) {
            if (! in_array($seller->status, ['approved', 'suspended'], true)) {
                continue;
            }

            $metadata['sellers'][$seller->id] = [
                'previous_status' => $seller->status,
                'new_status' => $profileStatus,
            ];
            $seller->update(['status' => $profileStatus]);
        }

        $provider = $user->logisticsProvider()->lockForUpdate()->first();
        if ($provider && in_array($provider->status, ['approved', 'suspended'], true)) {
            $metadata['logistics_provider'] = [
                'id' => $provider->id,
                'previous_status' => $provider->status,
                'new_status' => $profileStatus,
            ];
            $provider->update(['status' => $profileStatus]);
        }

        return $metadata;
    }

    private function synchronizeApprovedProfile(User $user, string $applicationType): void
    {
        if ($applicationType === 'seller') {
            $user->sellers()->where('status', 'pending')->update([
                'status' => 'approved',
                'rejection_reason' => null,
            ]);
        }

        if ($applicationType === 'logistics') {
            $user->logisticsProvider()->where('status', 'pending')->update([
                'status' => 'approved',
                'rejection_reason' => null,
            ]);
        }
    }

    private function ensureAllowedTransition(User $user, string $newStatus, ?string $reason): void
    {
        if (! in_array($newStatus, ['active', 'suspended', 'inactive'], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid account status.']);
        }

        if (! in_array($newStatus, self::TRANSITIONS[$user->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "The account cannot move from {$user->status} to {$newStatus}.",
            ]);
        }

        if (in_array($newStatus, ['suspended', 'inactive'], true) && blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required when suspending or deactivating an account.',
            ]);
        }
    }

    private function ensureSellerSuspensionUsesCompliance(User $user, string $newStatus): void
    {
        if ($newStatus === 'suspended' && $user->sellers()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Seller accounts must be suspended from an open compliance case.',
            ]);
        }
    }

    private function ensureAdminSafeguards(User $user, string $newStatus, User $actor): void
    {
        if ($newStatus === 'active') {
            return;
        }

        if ($user->is($actor)) {
            throw ValidationException::withMessages([
                'status' => 'Administrators cannot suspend or deactivate their own account.',
            ]);
        }

        if (! $user->isAdmin()) {
            return;
        }

        $activeAdminCount = User::query()
            ->where('status', 'active')
            ->where('is_suspended', false)
            ->where(function ($query) {
                $query->whereIn('role', ['admin', 'superadmin'])
                    ->orWhereHas('roles', fn ($roles) => $roles->whereIn('name', ['admin', 'superadmin']));
            })
            ->lockForUpdate()
            ->count();

        if ($activeAdminCount <= 1) {
            throw ValidationException::withMessages([
                'status' => 'The last active administrator cannot be disabled.',
            ]);
        }
    }

    private function ensureAdminActor(User $actor): void
    {
        if (! $actor->isAdmin() || $actor->status !== 'active' || $actor->is_suspended) {
            abort(403, 'Only an active administrator can manage account status.');
        }
    }

    private function ensureReviewable(Application $application): void
    {
        if ($application->application_type === 'courier') {
            abort(403, 'Rider applications must be reviewed by Logistics.');
        }

        if (! in_array($application->application_type, ['buyer', 'seller', 'logistics'], true)) {
            abort(422, 'Unsupported application type.');
        }

        if ($application->status !== 'pending') {
            abort(422, 'Only pending applications can be reviewed.');
        }
    }

    private function recordEvent(
        User $user,
        User $actor,
        string $previousStatus,
        string $newStatus,
        ?string $reason,
        string $source,
        ?Application $application = null,
        array $metadata = []
    ): AccountStatusEvent {
        return AccountStatusEvent::create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'application_id' => $application?->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'source' => $source,
            'metadata' => $metadata ?: null,
        ]);
    }
}
