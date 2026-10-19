<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\DomainEventNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DomainNotificationService
{
    public function adminsAfterCommit(string $title, string $message, array $context = []): void
    {
        User::query()->where('status', 'active')->where('is_suspended', false)
            ->where(fn ($query) => $query->whereIn('role', ['admin', 'superadmin'])
                ->orWhereHas('roles', fn ($roles) => $roles->whereIn('name', ['admin', 'superadmin'])))
            ->each(fn (User $admin) => $this->afterCommit($admin->id, $title, $message, $context));
    }

    public function afterCommit(int $userId, string $title, string $message, array $context = []): void
    {
        $id = (string) Str::uuid();
        DB::table('notification_outbox')->insert([
            'id' => $id, 'user_id' => $userId, 'title' => $title, 'message' => $message,
            'context' => json_encode($context, JSON_THROW_ON_ERROR), 'available_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::afterCommit(fn () => $this->deliver($id));
    }

    public function deliver(string $id): bool
    {
        try {
            return DB::transaction(function () use ($id) {
                $intent = DB::table('notification_outbox')->where('id', $id)->lockForUpdate()->first();
                if (! $intent || $intent->processed_at || $intent->available_at > now()->toDateTimeString()) {
                    return false;
                }
                // The outbox UUID is also the notification primary key. The lock and
                // transaction make delivery and the processed marker indivisible.
                if (! DB::table('notifications')->where('id', $id)->exists()) {
                    $notification = new DomainEventNotification($intent->title, $intent->message, json_decode($intent->context, true, 512, JSON_THROW_ON_ERROR));
                    $notification->id = $id;
                    User::findOrFail($intent->user_id)->notify($notification);
                }
                DB::table('notification_outbox')->where('id', $id)->update(['processed_at' => now(), 'last_error' => null, 'updated_at' => now()]);
                return true;
            });
        } catch (\Throwable $exception) {
            report($exception);
            try {
                DB::transaction(function () use ($id, $exception) {
                    $intent = DB::table('notification_outbox')->where('id', $id)->lockForUpdate()->first();
                    if (! $intent || $intent->processed_at) {
                        return;
                    }
                    $attempts = $intent->attempts + 1;
                    DB::table('notification_outbox')->where('id', $id)->update([
                        'attempts' => $attempts, 'available_at' => now()->addSeconds(min(3600, 30 * (2 ** min($attempts - 1, 7)))),
                        'last_error' => class_basename($exception), 'updated_at' => now(),
                    ]);
                });
            } catch (\Throwable $recordingFailure) {
                report($recordingFailure);
            }
            return false;
        }
    }

    public function retry(int $limit = 100): int
    {
        $ids = DB::table('notification_outbox')->whereNull('processed_at')->where('available_at', '<=', now())
            ->orderBy('available_at')->limit(max(1, min($limit, 100)))->pluck('id');
        $delivered = 0;
        foreach ($ids as $id) {
            $delivered += (int) $this->deliver($id);
        }
        return $delivered;
    }
}
