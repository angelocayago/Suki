<?php

namespace App\Support;

use App\Models\Payout;
use App\Models\Seller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Read-only payout history and integer money presentation; no wallet calculations. */
class SellerWalletPage
{
    public const STATUSES = ['PENDING'=>'Pending', 'APPROVED'=>'Approved', 'PAID'=>'Paid', 'REJECTED'=>'Rejected'];
    public const DATES = ['any'=>'Any date', 'today'=>'Today', '7d'=>'Last 7 days', '30d'=>'Last 30 days'];

    public function history(Seller $seller, Request $request): array
    {
        $validated = $request->validate([
            'status'=>['nullable', Rule::in(array_merge(['all'], array_keys(self::STATUSES)))],
            'date'=>['nullable', Rule::in(array_keys(self::DATES))],
            'page'=>['nullable', 'integer', 'min:1'],
        ]);
        $criteria = [];
        if (!empty($validated['status']) && $validated['status'] !== 'all') $criteria['status'] = $validated['status'];
        if (!empty($validated['date']) && $validated['date'] !== 'any') $criteria['date'] = $validated['date'];
        $query = Payout::query()->where('seller_id', $seller->id);
        if (isset($criteria['status'])) $query->where('status', $criteria['status']);
        if (isset($criteria['date'])) {
            $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
            $days = match ($criteria['date']) { 'today'=>0, '7d'=>6, '30d'=>29 };
            $query->where('created_at', '>=', $today->subDays($days)->setTimezone(config('app.timezone')))
                ->where('created_at', '<', $today->addDay()->setTimezone(config('app.timezone')));
        }
        $payouts = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->appends($criteria);
        return compact('payouts', 'criteria') + ['statuses'=>self::STATUSES, 'dates'=>self::DATES];
    }

    public static function decimal(int $minor): string
    {
        $digits = str_pad(ltrim((string) $minor, '-'), 3, '0', STR_PAD_LEFT);
        return ($minor < 0 ? '-' : '').substr($digits, 0, -2).'.'.substr($digits, -2);
    }

    public static function money(int $minor): string
    {
        [$whole, $fraction] = explode('.', self::decimal($minor));
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole).'.'.$fraction;
    }
}
