<?php
namespace App\Services;
use App\Mail\RiderActivationMail;
use App\Models\RiderActivationToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class RiderActivationService
{
    public function send(User $user, bool $enforceCooldown = true): void
    {
        abort_unless($user->hasRole('rider') && $user->rider?->approved_at, 422);
        $plain = Str::random(64);
        DB::transaction(function () use ($user, $plain, $enforceCooldown): void {
            $existing = RiderActivationToken::query()->whereBelongsTo($user)->lockForUpdate()->first();
            if ($enforceCooldown && $existing?->last_sent_at?->gt(now()->subMinute())) throw ValidationException::withMessages(['activation' => 'Please wait before resending the activation link.']);
            RiderActivationToken::query()->updateOrCreate(['user_id' => $user->id], ['token_hash' => hash('sha256', $plain), 'expires_at' => now()->addDay(), 'used_at' => null, 'last_sent_at' => now()]);
        }, 3);
        Mail::to($user->email)->send(new RiderActivationMail(route('rider.activation.show', $plain)));
    }
    public function userFor(string $token): User
    {
        $record = RiderActivationToken::query()->where('token_hash', hash('sha256', $token))->first();
        abort_unless($record && ! $record->used_at && $record->expires_at->isFuture(), 403, 'This activation link is invalid, expired, or already used.');
        return $record->user;
    }
    public function activate(string $token, string $password): User
    {
        return DB::transaction(function () use ($token, $password): User {
            $record = RiderActivationToken::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            abort_unless($record && ! $record->used_at && $record->expires_at->isFuture(), 403, 'This activation link is invalid, expired, or already used.');
            $record->update(['used_at' => now()]);
            $record->user->update(['password' => $password, 'email_verified_at' => now(), 'verification_required_at' => now(), 'status' => 'pending']);
            return $record->user->fresh();
        }, 3);
    }
}
