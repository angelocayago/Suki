<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class SellerDateFilter
{
    public const PRESETS = ['all' => 'All Time', 'today' => 'Today', 'yesterday' => 'Yesterday', 'last7' => 'Last 7 Days', 'last30' => 'Last 30 Days', 'week' => 'This Week', 'last_week' => 'Last Week', 'month' => 'This Month', 'last_month' => 'Last Month', 'custom' => 'Custom Date Range'];

    public static function bounds(Request $request): ?array
    {
        $data = $request->validate([
            'date_range' => 'nullable|string|in:'.implode(',', array_keys(self::PRESETS)),
            'from' => 'nullable|required_if:date_range,custom|date_format:Y-m-d',
            'to' => 'nullable|required_if:date_range,custom|date_format:Y-m-d|after_or_equal:from',
        ]);
        $today = CarbonImmutable::now(config('seller_inventory.business_timezone'))->startOfDay();
        $range = match ($data['date_range'] ?? 'all') {
            'today' => [$today, $today->addDay()],
            'yesterday' => [$today->subDay(), $today],
            'last7' => [$today->subDays(6), $today->addDay()],
            'last30' => [$today->subDays(29), $today->addDay()],
            'week' => [$today->startOfWeek(), $today->startOfWeek()->addWeek()],
            'last_week' => [$today->startOfWeek()->subWeek(), $today->startOfWeek()],
            'month' => [$today->startOfMonth(), $today->startOfMonth()->addMonth()],
            'last_month' => [$today->startOfMonth()->subMonth(), $today->startOfMonth()],
            'custom' => [CarbonImmutable::parse($data['from'], $today->timezone), CarbonImmutable::parse($data['to'], $today->timezone)->addDay()],
            default => null,
        };

        return $range ? array_map(fn ($date) => $date->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'), $range) : null;
    }

    public static function apply($query, Request $request, string $column): void
    {
        if ($bounds = self::bounds($request)) {
            $query->where($column, '>=', $bounds[0])->where($column, '<', $bounds[1]);
        }
    }
}
