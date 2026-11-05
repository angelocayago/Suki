<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\SellerSalesService;

/** Dashboard-only, read-only presentation; shared business services remain authoritative. */
class SellerDashboard
{
    // Match the existing Seller Inventory page's low-stock classification.
    private const LOW_STOCK_THRESHOLD = 10;

    public function __construct(
        private readonly SellerSalesService $sales,
        private readonly SellerReports $reports,
    ) {}

    /** Also supports the existing legacy dashboard view without trusting session records. */
    public function forUser(?User $user): array
    {
        abort_unless($user && $user->hasRole('seller') && $user->status === 'active'
            && ! $user->is_suspended && $user->hasVerifiedContacts(), 403);
        $seller = $user->sellers()->where('status', 'approved')->first();
        abort_unless($seller, 403, 'No approved seller profile is available.');

        return $this->data($seller);
    }

    public function data(Seller $seller): array
    {
        $statusCounts = [];
        foreach ($seller->orders()->selectRaw('workflow_status, status, COUNT(*) AS total')
            ->groupBy('workflow_status', 'status')->get() as $row) {
            $status = $this->status($row);
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + (int) $row->total;
        }

        $businessTimezone = 'Asia/Manila';
        $salesSeries = $this->reports->dashboardSeries($seller);
        $salesByDay = collect($salesSeries['7d']['points'])->map(fn ($day) => $day + ['gross_minor'=>$day['raw_minor'], 'gross'=>$day['raw_minor'] / 100]);

        $recentOrders = $seller->orders()->with(['order', 'items', 'shipment'])
            ->latest()->orderByDesc('id')->limit(5)->get()->map(fn (SellerOrder $order) => [
                'id' => $order->id,
                'display_reference' => $order->order?->order_number ?: 'Seller order #'.$order->id,
                'status' => $this->status($order),
                'gross_amount' => $order->items->sum(fn ($item) => (int) $item->unit_price_minor * (int) $item->quantity) / 100,
                'gross_minor' => $order->items->sum(fn ($item) => (int) $item->unit_price_minor * (int) $item->quantity),
                'item_count' => $order->items->count(),
                'recipient_name' => $order->order?->recipient_name,
                'product_name' => $order->items->first()?->product_name,
                'tracking_code' => $order->shipment?->tracking_code,
                'created_at' => $order->created_at,
            ]);

        $products = $seller->products()->where('is_active', true);
        $lowStockQuery = (clone $products)->whereHas('variants', fn ($query) => $query
            ->where('is_active', true)->where('stock', '<=', self::LOW_STOCK_THRESHOLD));
        $lowStockProducts = (clone $lowStockQuery)->count();
        $lowStockList = $lowStockQuery->with(['images', 'variants' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')->orderBy('id')->limit(5)->get()->map(function ($product) {
                $stock = $product->variants->sum('stock');

                return ['name' => $product->name, 'stock' => $stock,
                    'id' => $product->id,
                    'image' => app(\App\Services\ProductCatalogService::class)->thumbnail($product),
                    'variants' => $product->variants->where('stock', '<=', self::LOW_STOCK_THRESHOLD)->map(fn ($variant) => ['name'=>$variant->name, 'stock'=>(int)$variant->stock])->values()->all(),
                    'low_stock_variants' => $product->variants->where('stock', '>', 0)->where('stock', '<=', self::LOW_STOCK_THRESHOLD)->count(),
                    'out_of_stock_variants' => $product->variants->where('stock', '<=', 0)->count()];
            });
        $lowStockVariants = \App\Models\ProductVariant::query()->where('is_active', true)
            ->where('stock', '<=', self::LOW_STOCK_THRESHOLD)
            ->whereHas('product', fn ($query) => $query->where('seller_id', $seller->id)->where('is_active', true))->count();

        $completedSales = $this->sales->completedSummary($seller);

        return compact('salesByDay', 'salesSeries', 'businessTimezone', 'recentOrders', 'statusCounts', 'lowStockProducts', 'lowStockVariants', 'lowStockList', 'completedSales') + [
            'productCount' => $seller->products()->count(),
            'orderCount' => array_sum($statusCounts),
            'completedCount' => $statusCounts['completed'] ?? 0,
            'toConfirm' => $statusCounts['placed'] ?? 0,
            'customerCount' => Order::query()->whereHas('sellerOrders', fn ($query) => $query->where('seller_id', $seller->id))
                ->whereHas('buyer')->distinct()->count('buyer_id'),
            'grossCompleted' => $completedSales['raw_sales_minor'] / 100,
            'stockThreshold' => self::LOW_STOCK_THRESHOLD,
            'topProducts' => $this->reports->dashboardTopProducts($seller)->map(fn ($row) => (array)$row),
        ];
    }

    private function status(SellerOrder $order): string
    {
        if ($order->status === 'cancelled') {
            return 'cancelled';
        }

        return strtolower(OrderStatus::tryFrom((string) $order->getRawOriginal('workflow_status'))?->value ?? 'unknown');
    }
}
