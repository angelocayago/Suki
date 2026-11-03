<?php

namespace App\Services;

use App\Models\BuyerStoreFollow;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Discovery and claims only. VoucherService remains the discount authority. */
class ShopVoucherService
{
    private function buyer(User $buyer): void
    {
        abort_unless($buyer->isBuyer() && $buyer->status === 'active' && ! $buyer->is_suspended, 403);
    }

    public function active(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('archived_at')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->whereHas('seller', fn ($q) => $q->where('status', 'approved')
                ->whereHas('owner', fn ($q) => $q->where('status', 'active')->where('is_suspended', false)));
    }

    public function listing(User $buyer, string $tab): array
    {
        $this->buyer($buyer);
        $owned = fn ($q) => $q->where('buyer_id', $buyer->id);
        $base = Voucher::query()->where('scope', 'SELLER')->with('seller.owner')
            ->withExists(['claims as claimed' => $owned])
            ->withCount(['claims', 'redemptions as buyer_uses' => $owned]);
        if ($tab === 'available') {
            $query = $this->active($base)->whereNotNull('audience')->whereDoesntHave('claims', $owned)
                ->where(fn ($q) => $q->where('audience', 'PUBLIC')->orWhere(fn ($q) => $q->where('audience', 'FOLLOWERS')
                    ->whereIn('seller_id', BuyerStoreFollow::where('buyer_id', $buyer->id)->select('seller_id'))))
                ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereRaw('(select count(*) from voucher_claims where voucher_claims.voucher_id = vouchers.id) < vouchers.usage_limit'));
        } elseif ($tab === 'mine') {
            $query = $this->usableQuery($base->whereHas('claims', $owned), $buyer);
        } else {
            $usable = $this->usableQuery(Voucher::query()->select('vouchers.id'), $buyer);
            $query = $base->where(fn ($q) => $q->whereHas('claims', $owned)->orWhereHas('redemptions', $owned))
                ->where(fn ($q) => $q->whereHas('redemptions', $owned)->orWhereNotIn('vouchers.id', $usable));
        }
        return ['vouchers' => $query->orderByDesc('id')->paginate(20)->withQueryString(), 'tab' => $tab];
    }

    private function usableQuery(Builder $query, User $buyer): Builder
    {
        return $this->active($query)->where(fn ($q) => $q->whereNull('per_buyer_limit')
            ->orWhereRaw('(select count(distinct order_id) from voucher_redemptions where voucher_redemptions.voucher_id = vouchers.id and buyer_id = ?) < vouchers.per_buyer_limit', [$buyer->id]));
    }

    public function claim(User $buyer, int $voucherId): VoucherClaim
    {
        $this->buyer($buyer);
        return DB::transaction(function () use ($buyer, $voucherId) {
            $voucher = Voucher::where('scope', 'SELLER')->whereNotNull('audience')->lockForUpdate()->findOrFail($voucherId);
            $existing = $voucher->claims()->where('buyer_id', $buyer->id)->first();
            if ($existing) return $existing;
            $this->assertActive($voucher);
            if ($voucher->audience === 'FOLLOWERS' && ! BuyerStoreFollow::where('buyer_id', $buyer->id)->where('seller_id', $voucher->seller_id)->exists()) {
                $this->invalid('Follow this shop to claim this voucher.');
            }
            if (! in_array($voucher->audience, ['FOLLOWERS', 'PUBLIC'], true)) $this->invalid('This voucher cannot be claimed.');
            if ($voucher->usage_limit !== null && $voucher->claims()->count() >= $voucher->usage_limit) $this->invalid('All available claims have been taken.');
            return $voucher->claims()->create(['buyer_id' => $buyer->id, 'claimed_at' => now()]);
        });
    }

    public function assertActive(Voucher $voucher): void
    {
        if ($voucher->expires_at && $voucher->expires_at->lte(now())) $this->invalid('This voucher has expired.');
        if ($voucher->starts_at && $voucher->starts_at->gt(now())) $this->invalid('This voucher is not active yet.');
        if (! $voucher->is_active || $voucher->archived_at) $this->invalid('This voucher has been deactivated.');
        if ($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit) $this->invalid('This voucher has reached its usage limit.');
        $voucher->loadMissing('seller.owner');
        if ($voucher->seller?->status !== 'approved' || $voucher->seller?->owner?->status !== 'active' || $voucher->seller?->owner?->is_suspended) $this->invalid('The issuing shop is unavailable.');
    }

    public function assertRedeemable(Voucher $voucher, ?User $buyer): void
    {
        if (! $buyer || ! $buyer->isBuyer() || $buyer->status !== 'active' || $buyer->is_suspended) $this->invalid('Sign in with an active Buyer account to use this shop voucher.');
        $this->assertActive($voucher);
        if ($voucher->audience !== null && ! $voucher->claims()->where('buyer_id', $buyer->id)->exists()) $this->invalid('Claim this shop voucher before applying it at checkout.');
        $uses = $voucher->redemptions()->where('buyer_id', $buyer->id)->distinct()->count('order_id');
        if ($voucher->per_buyer_limit !== null && $uses >= $voucher->per_buyer_limit) $this->invalid('You have already reached your usage limit for this voucher.');
        // A legitimate claim survives unfollowing. Seller activation still applies.
    }

    public function claimedForCheckout(User $buyer, \Illuminate\Support\Collection $subtotals)
    {
        return $this->usableQuery(Voucher::where('scope', 'SELLER')->whereIn('seller_id', $subtotals->keys())
            ->whereHas('claims', fn ($q) => $q->where('buyer_id', $buyer->id))->with('seller'), $buyer)->orderBy('seller_id')->orderBy('id')->get()->filter(fn($voucher)=>(int)$subtotals->get($voucher->seller_id,0)>0 && (int)$subtotals->get($voucher->seller_id,0)>=$voucher->minimum_spend_minor);
    }

    public function notifyFollowers(Voucher $voucher): void
    {
        DB::transaction(function () use ($voucher) {
            $record = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
            if ($record->scope !== 'SELLER' || $record->audience !== 'FOLLOWERS' || $record->followers_notified_at) return;
            if (! $this->active(Voucher::whereKey($record->id))->exists()) return;
            $record->loadMissing('seller');
            User::where('role', 'buyer')->where('status', 'active')->where('is_suspended', false)
                ->whereIn('id', BuyerStoreFollow::where('seller_id', $record->seller_id)->select('buyer_id'))
                ->chunkById(200, function ($buyers) use ($record) {
                    foreach ($buyers as $buyer) app(DomainNotificationService::class)->afterCommit($buyer->id,
                        'New Shop Voucher from '.$record->seller->name,
                        'A voucher is available from a shop you follow. View and claim it before it expires.',
                        ['url' => route('buyer.vouchers.index'), 'voucher_id' => $record->id]);
                });
            $record->forceFill(['followers_notified_at' => now()])->save();
        });
    }

    public function notifyDue(): void
    {
        $this->active(Voucher::where('scope', 'SELLER')->where('audience', 'FOLLOWERS')->whereNull('followers_notified_at'))
            ->chunkById(100, function ($vouchers) { foreach ($vouchers as $voucher) $this->notifyFollowers($voucher); });
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['voucher' => $message]);
    }
}
