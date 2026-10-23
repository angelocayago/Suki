<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderReceiptFinalizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class AutoConfirmReceivedOrders extends Command
{
    protected $signature = 'orders:auto-confirm-received {--dry-run}';

    protected $description =
        'Auto-confirm delivered orders after the configured waiting period.';

    public function handle(OrderReceiptFinalizationService $service): int
    {
        if (!Schema::hasColumn('orders', 'confirmed_received_at')) {
            $this->warn('orders.confirmed_received_at does not exist. Nothing changed.');
            return self::SUCCESS;
        }

        $checked = 0;
        $confirmed = 0;

        Order::query()
            ->whereNull('confirmed_received_at')
            ->where('status', 'delivered')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (
                $service,
                &$checked,
                &$confirmed
            ) {
                foreach ($orders as $order) {
                    $checked++;

                    if (!$service->isEligibleForAutoConfirm($order)) {
                        continue;
                    }

                    if ($this->option('dry-run')) {
                        $this->line("Would auto-confirm Order #{$order->id}");
                        $confirmed++;
                        continue;
                    }

                    $service->finalize($order, 'auto_3_days');
                    $confirmed++;

                    $this->info("Auto-confirmed Order #{$order->id}");
                }
            });

        $label = $this->option('dry-run') ? 'eligible' : 'auto-confirmed';
        $this->info("Checked {$checked} delivered orders; {$confirmed} {$label}.");

        return self::SUCCESS;
    }
}
