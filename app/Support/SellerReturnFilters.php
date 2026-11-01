<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Seller presentation/query options; transitions remain in ReturnRefundService. */
class SellerReturnFilters
{
    public const STATUSES = [
        'SUBMITTED' => 'Awaiting seller response',
        'SELLER_RESPONDED' => 'Awaiting Admin review',
        'UNDER_REVIEW' => 'Under review',
        'ADMIN_APPROVED' => 'Approved — Refund Due',
        'ADMIN_REJECTED' => 'Rejected by Admin',
    ];

    public const DATES = ['any' => 'Any date', 'today' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days'];

    public static function criteria(Request $request): array
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_merge(['all'], array_keys(self::STATUSES)))],
            'date' => ['nullable', Rule::in(array_keys(self::DATES))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return ['search' => trim($data['search'] ?? ''), 'status' => $data['status'] ?? 'all', 'date' => $data['date'] ?? 'any'];
    }

    public static function apply(Builder $query, array $criteria): Builder
    {
        if ($criteria['search'] !== '') {
            // Literal, case-insensitive search on PostgreSQL and isolated SQLite.
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($criteria['search'])).'%';
            $query->where(function (Builder $query) use ($pattern) {
                $query->whereHas('order', fn (Builder $order) => $order
                    ->whereRaw("LOWER(COALESCE(order_number, '')) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(COALESCE(reference, '')) LIKE ? ESCAPE '!'", [$pattern]))
                    ->orWhereHas('buyer', fn (Builder $buyer) => $buyer
                        ->whereRaw("LOWER(COALESCE(name, '')) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ? ESCAPE '!'", [$pattern]))
                    ->orWhereHas('item', fn (Builder $item) => $item
                        ->whereRaw("LOWER(COALESCE(product_name, '')) LIKE ? ESCAPE '!'", [$pattern]));
            });
        }
        if ($criteria['status'] !== 'all') {
            $query->where('status', $criteria['status']);
        }
        if ($criteria['date'] !== 'any') {
            $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
            $days = $criteria['date'] === 'today' ? 1 : (int) $criteria['date'];
            $query->where('created_at', '>=', $today->subDays($days - 1)->utc())
                ->where('created_at', '<', $today->addDay()->utc());
        }

        return $query;
    }
}
