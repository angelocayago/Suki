<?php

namespace App\Support;

use App\Models\OrderItem;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\VoucherRedemption;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Seller-only, read-only projection of ReportService's financial contracts.
 * SQL aggregation avoids loading every completed order/item into the dashboard.
 * ReportService, commissions, wallets, refunds and other reports are unchanged.
 */
class SellerReports
{
    private const STATUS_SQL = "CASE WHEN status = 'cancelled' THEN 'cancelled' WHEN workflow_status = 'COMPLETED' THEN 'completed' WHEN workflow_status = 'RTS_COMPLETED' THEN 'returned' WHEN workflow_status = 'DELIVERY_FAILED' THEN 'failed' WHEN workflow_status IN ('RTS_INITIATED','RTS_RETURNING') THEN 'returning' WHEN workflow_status IN ('PLACED','CONFIRMED','PREPARING','READY_FOR_PICKUP','PICKED_UP','AT_SORTING_CENTER','SORTED','ASSIGNED_TO_RIDER','OUT_FOR_DELIVERY','DELIVERED') THEN 'pending' ELSE 'unavailable' END";
    private const DATE_SQL = "CASE WHEN workflow_status = 'COMPLETED' AND status != 'cancelled' THEN completed_at ELSE updated_at END";
    private const RAW_SQL = 'order_items.unit_price_minor * order_items.quantity';

    public static function dateOptions(): array
    {
        return ['any'=>'Any date', 'today'=>'Today', '7d'=>'Last 7 days', '30d'=>'Last 30 days', 'custom'=>'Custom range'];
    }

    public function period(Request $request): array
    {
        // Existing from/to-only report and CSV links remain valid, including
        // open-ended links. New Custom range submissions require both bounds.
        $custom = $request->query('date') === 'custom';
        $legacy = !$request->has('date') && ($request->filled('from') || $request->filled('to'));
        $rules = ['date'=>['nullable', Rule::in(array_keys(self::dateOptions()))], 'page'=>['nullable', 'integer', 'min:1']];
        foreach (['from', 'to'] as $field) $rules[$field] = ($custom || $legacy)
            ? [$custom ? 'required' : 'nullable', 'date_format:Y-m-d'] : ['exclude'];
        if (($custom || $legacy) && $request->filled('from')) $rules['to'][] = 'after_or_equal:from';
        $input = $request->validate($rules);
        $date = $legacy ? 'custom' : ($input['date'] ?? 'any');
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        [$start, $end] = match ($date) {
            'today'=>[$today, $today], '7d'=>[$today->subDays(6), $today],
            '30d'=>[$today->subDays(29), $today],
            'custom'=>[isset($input['from']) ? CarbonImmutable::parse($input['from'], 'Asia/Manila') : null, isset($input['to']) ? CarbonImmutable::parse($input['to'], 'Asia/Manila') : null],
            default=>[null, null],
        };
        $query = $date === 'any' ? [] : ['date'=>$date];
        if ($date === 'custom') {
            if ($start) $query['from'] = $start->format('Y-m-d');
            if ($end) $query['to'] = $end->format('Y-m-d');
            if (!$start || !$end) unset($query['date']);
        }
        $label = $date === 'any' ? 'All time' : ($date === 'custom'
            ? ($start?->format('M d, Y') ?? 'Beginning').' – '.($end?->format('M d, Y') ?? 'Latest')
            : self::dateOptions()[$date].' · '.$start->format('M d').' – '.$end->format('M d, Y'));

        return compact('date', 'query', 'label', 'start', 'end') + [
            'from'=>$start?->startOfDay()->setTimezone(config('app.timezone')),
            'until'=>$end?->addDay()->startOfDay()->setTimezone(config('app.timezone')),
        ];
    }

    public function data(Seller $seller, array $period): array
    {
        $completed = $this->completed($seller, $period);
        $items = $this->items($seller, $period);
        $totals = (clone $items)->selectRaw('COALESCE(SUM('.self::RAW_SQL.'), 0) AS raw_minor')
            ->selectRaw('COALESCE(SUM(order_items.commission_minor), 0) AS commission_minor')
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0) AS units')->first();
        $discounts = VoucherRedemption::query()->whereIn('seller_order_id', (clone $completed)->select('id'))
            ->selectRaw('scope_snapshot, SUM(discount_minor) AS total')->groupBy('scope_snapshot')->get()->pluck('total', 'scope_snapshot');
        $summary = [
            'raw_minor'=>(int) $totals->raw_minor, 'commission_minor'=>(int) $totals->commission_minor,
            'seller_discount_minor'=>(int) ($discounts['SELLER'] ?? 0),
            'platform_discount_minor'=>(int) ($discounts['PLATFORM'] ?? 0),
            'shipping_minor'=>(int) (clone $completed)->sum('shipping_fee_minor'),
        ];
        // Exactly ReportService::financialSummary: platform discounts and
        // shipping do not reduce/add to net merchandise sales.
        $summary['net_minor'] = $summary['raw_minor'] - $summary['commission_minor'] - $summary['seller_discount_minor'];
        $statuses = SellerOrder::query()->where('seller_id', $seller->id);
        $this->dated($statuses, $period, DB::raw(self::DATE_SQL));
        $counts = $statuses->selectRaw(self::STATUS_SQL.' AS report_status, COUNT(*) AS total')->groupByRaw(self::STATUS_SQL)->get()->pluck('total', 'report_status');
        $statusChart = [];
        foreach (['completed'=>'Completed', 'pending'=>'Pending', 'cancelled'=>'Cancelled', 'returned'=>'Returned to Seller', 'failed'=>'Failed Delivery', 'returning'=>'Return in progress', 'unavailable'=>'Status not recorded'] as $key=>$label) {
            if (in_array($key, ['returning', 'unavailable'], true) && !isset($counts[$key])) continue;
            $statusChart[] = ['key'=>$key, 'label'=>$label, 'count'=>(int) ($counts[$key] ?? 0)];
        }
        $products = $this->productQuery($seller, $period)->paginate(10)->appends($period['query']);

        return [
            'summary'=>$summary, 'completed_orders'=>(clone $completed)->count(),
            'cancelled_orders'=>(int) ($counts['cancelled'] ?? 0), 'units_sold'=>(int) $totals->units,
            'products'=>$products, 'status_chart'=>$statusChart,
            'sales_chart'=>$this->salesChart($seller, $period),
        ];
    }

    public function export(Seller $seller, array $period): iterable
    {
        // Same full ranking and CSV columns as before, independent of table page.
        return $this->productQuery($seller, $period)->cursor();
    }

    /** Bounded dashboard series, using the same completed-item source as reports. */
    public function dashboardSeries(Seller $seller): array
    {
        $today = CarbonImmutable::today('Asia/Manila');
        $until = $today->addDay();
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $local = $sqlite ? "datetime(seller_orders.completed_at, '+8 hours')" : "((seller_orders.completed_at AT TIME ZONE 'UTC') AT TIME ZONE 'Asia/Manila')";
        $values = [];
        foreach (['day'=>$today->subDays(29), 'month'=>$today->startOfMonth()->subMonths(11)] as $unit=>$start) {
            $bucket = $unit === 'day'
                ? ($sqlite ? "strftime('%Y-%m-%d', $local)" : "to_char($local, 'YYYY-MM-DD')")
                : ($sqlite ? "strftime('%Y-%m', $local)" : "to_char($local, 'YYYY-MM')");
            $period = ['from'=>$start->setTimezone('UTC'), 'until'=>$until->setTimezone('UTC')];
            $values[$unit] = $this->items($seller, $period)->join('seller_orders', 'seller_orders.id', '=', 'order_items.seller_order_id')
                ->selectRaw($bucket.' AS bucket, SUM('.self::RAW_SQL.') AS raw_minor')->groupByRaw($bucket)->get()->pluck('raw_minor', 'bucket');
        }
        $series = [];
        foreach (['7d'=>7, '30d'=>30, '12m'=>12] as $key=>$count) {
            $monthly = $key === '12m';
            $start = $monthly ? $today->startOfMonth()->subMonths(11) : $today->subDays($count - 1);
            $points = [];
            for ($index = 0; $index < $count; $index++) {
                $date = $monthly ? $start->addMonths($index) : $start->addDays($index);
                $bucket = $date->format($monthly ? 'Y-m' : 'Y-m-d');
                $points[] = ['date'=>$bucket, 'label'=>$date->format($monthly ? 'M Y' : 'M d'), 'period'=>$date->format($monthly ? 'F Y' : 'M d, Y'), 'raw_minor'=>(int)($values[$monthly ? 'month' : 'day'][$bucket] ?? 0)];
            }
            $series[$key] = ['points'=>$points, 'total_minor'=>array_sum(array_column($points, 'raw_minor')), 'from'=>$start->toDateString(), 'to'=>$today->toDateString(), 'granularity'=>$monthly ? 'month' : 'day'];
        }
        return $series;
    }

    public function dashboardTopProducts(Seller $seller)
    {
        return $this->productQuery($seller, ['from'=>null, 'until'=>null])->limit(5)->get();
    }

    private function completed(Seller $seller, array $period)
    {
        $query = SellerOrder::query()->where('seller_id', $seller->id)->where('workflow_status', 'COMPLETED')->where('status', '!=', 'cancelled');
        return $this->dated($query, $period, 'completed_at');
    }

    private function items(Seller $seller, array $period)
    {
        return OrderItem::query()->whereIn('seller_order_id', $this->completed($seller, $period)->select('id'));
    }

    private function dated($query, array $period, $column)
    {
        if ($period['from']) $query->where($column, '>=', $period['from']);
        if ($period['until']) $query->where($column, '<', $period['until']);
        return $query;
    }

    private function productQuery(Seller $seller, array $period)
    {
        $key = "COALESCE(CAST(order_items.product_id AS TEXT), order_items.product_slug, '')";
        $grouped = $this->items($seller, $period)->selectRaw('MIN(order_items.id) AS snapshot_id, SUM(quantity) AS units, SUM('.self::RAW_SQL.') AS raw_minor')->groupByRaw($key);
        // Historical item snapshots survive edits, archived products and variants.
        return DB::query()->fromSub($grouped, 'ranked')->join('order_items AS snapshot', 'snapshot.id', '=', 'ranked.snapshot_id')
            ->select('ranked.*', 'snapshot.product_name AS name', 'snapshot.image')
            ->orderByDesc('ranked.units')->orderByDesc('ranked.raw_minor')->orderBy('ranked.snapshot_id');
    }

    private function salesChart(Seller $seller, array $period): array
    {
        $bounds = $this->completed($seller, $period)->selectRaw('MIN(completed_at) AS first_date, MAX(completed_at) AS last_date')->first();
        $undated = $this->completed($seller, $period)->whereNull('completed_at')->count();
        if ($undated) return ['points'=>[], 'granularity'=>null, 'unavailable'=>'The sales chart is unavailable because '.$undated.' completed order(s) have no recorded completion date. Their values remain in all-time totals.'];
        if (!$bounds->first_date) return ['points'=>[], 'granularity'=>null, 'unavailable'=>null];
        $from = $period['start'] ?? CarbonImmutable::parse($bounds->first_date, config('app.timezone'))->setTimezone('Asia/Manila')->startOfDay();
        $to = $period['end'] ?? CarbonImmutable::parse($bounds->last_date, config('app.timezone'))->setTimezone('Asia/Manila')->startOfDay();
        $days = (int) $from->diffInDays($to) + 1;
        $granularity = $days <= 62 ? 'day' : ($days <= 366 ? 'week' : ($days <= 1461 ? 'month' : 'year'));
        $yearStep = max(1, (int) ceil(($to->year - $from->year + 1) / 60));
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        // Canonical completed_at is a UTC timestamp without timezone. PostgreSQL
        // performs named Manila conversion; SQLite is used only by isolated tests.
        $local = $sqlite ? "datetime(seller_orders.completed_at, '+8 hours')" : "((seller_orders.completed_at AT TIME ZONE 'UTC') AT TIME ZONE 'Asia/Manila')";
        $bucket = match ($granularity) {
            'day'=>$sqlite ? "strftime('%Y-%m-%d', $local)" : "to_char($local, 'YYYY-MM-DD')",
            'week'=>$sqlite ? "date($local, 'weekday 0', '-6 days')" : "to_char(date_trunc('week', $local), 'YYYY-MM-DD')",
            'month'=>$sqlite ? "strftime('%Y-%m', $local)" : "to_char($local, 'YYYY-MM')",
            'year'=>$sqlite ? "CAST((CAST(strftime('%Y', $local) AS INTEGER) - {$from->year}) / $yearStep AS INTEGER)" : "FLOOR((EXTRACT(YEAR FROM $local) - {$from->year}) / $yearStep)",
        };
        $values = $this->items($seller, $period)->join('seller_orders', 'seller_orders.id', '=', 'order_items.seller_order_id')
            ->selectRaw($bucket.' AS bucket, SUM('.self::RAW_SQL.') AS raw_minor')->groupByRaw($bucket)->get()->pluck('raw_minor', 'bucket');
        $cursor = match ($granularity) { 'week'=>$from->startOfWeek(CarbonImmutable::MONDAY), 'month'=>$from->startOfMonth(), 'year'=>$from->startOfYear(), default=>$from };
        $points = [];
        while ($cursor <= $to) {
            $next = match ($granularity) { 'day'=>$cursor->addDay(), 'week'=>$cursor->addWeek(), 'month'=>$cursor->addMonth(), 'year'=>$cursor->addYears($yearStep) };
            $key = match ($granularity) { 'day', 'week'=>$cursor->format('Y-m-d'), 'month'=>$cursor->format('Y-m'), 'year'=>(string) intdiv($cursor->year - $from->year, $yearStep) };
            $start = $cursor->max($from); $end = $next->subDay()->min($to);
            $points[] = ['label'=>$start->format('M d, Y').($start->isSameDay($end) ? '' : ' – '.$end->format('M d, Y')), 'raw_minor'=>(int) ($values[$key] ?? 0)];
            $cursor = $next;
        }
        return ['points'=>$points, 'granularity'=>$granularity === 'year' && $yearStep > 1 ? $yearStep.'-year' : $granularity, 'unavailable'=>null];
    }

    public static function money(int $minor): string
    {
        return ($minor < 0 ? '-' : '').number_format(intdiv(abs($minor), 100)).'.'.str_pad((string) (abs($minor) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function image(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'https://') || str_starts_with($path, 'http://')) return $path;
        if (str_starts_with($path, '/storage/')) return url($path);
        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    }
}
