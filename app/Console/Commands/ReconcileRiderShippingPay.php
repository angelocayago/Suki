<?php

namespace App\Console\Commands;

use App\Models\RiderWallet;
use App\Models\RiderWalletTransaction;
use App\Models\Shipment;
use App\Services\RiderWalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileRiderShippingPay extends Command
{
    protected $signature = 'rider-wallet:reconcile-shipping-pay {--dry-run}';

    protected $description =
        'Top up existing rider delivery credits to the current shipping-fee-funded delivery pay.';

    public function handle(RiderWalletService $service): int
    {
        $checked = 0;
        $adjusted = 0;
        $totalTopUpMinor = 0;

        RiderWalletTransaction::query()
            ->where('type', 'delivery_credit')
            ->whereNotNull('shipment_id')
            ->orderBy('id')
            ->chunkById(100, function ($transactions) use (
                $service,
                &$checked,
                &$adjusted,
                &$totalTopUpMinor
            ) {
                foreach ($transactions as $transaction) {
                    $checked++;

                    $shipment = Shipment::find($transaction->shipment_id);

                    if (!$shipment) {
                        continue;
                    }

                    $expectedMinor = $service->calculateDeliveryEarningMinor($shipment);
                    $alreadyCreditedMinor = (int) $transaction->amount_minor;

                    $existingAdjustmentMinor = (int) RiderWalletTransaction::query()
                        ->where('shipment_id', $shipment->id)
                        ->where('type', 'delivery_credit_adjustment')
                        ->sum('amount_minor');

                    $topUpMinor = $expectedMinor
                        - $alreadyCreditedMinor
                        - $existingAdjustmentMinor;

                    if ($topUpMinor <= 0) {
                        continue;
                    }

                    $adjusted++;
                    $totalTopUpMinor += $topUpMinor;

                    $this->line(
                        'Shipment #' . $shipment->id
                        . ': top-up ₱' . number_format($topUpMinor / 100, 2)
                    );

                    if ($this->option('dry-run')) {
                        continue;
                    }

                    DB::transaction(function () use (
                        $transaction,
                        $shipment,
                        $topUpMinor,
                        $expectedMinor
                    ) {
                        $wallet = RiderWallet::query()
                            ->whereKey($transaction->rider_wallet_id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $wallet->available_balance_minor += $topUpMinor;
                        $wallet->save();

                        RiderWalletTransaction::create([
                            'rider_wallet_id' => $wallet->id,
                            'shipment_id' => $shipment->id,
                            'type' => 'delivery_credit_adjustment',
                            'amount_minor' => $topUpMinor,
                            'balance_after_minor' => (int) $wallet->available_balance_minor,
                            'reference' => 'DELIVERY-ADJUST-' . $shipment->id,
                            'description' =>
                                'Rider delivery pay adjusted to shipping-fee-funded rate.',
                            'meta' => [
                                'expected_delivery_earning_minor' => $expectedMinor,
                                'adjustment_minor' => $topUpMinor,
                                'funding_source' => 'shipping_fee',
                            ],
                        ]);
                    });
                }
            });

        $mode = $this->option('dry-run') ? 'would be adjusted' : 'adjusted';

        $this->info(
            "Checked {$checked} existing delivery credits; "
            . "{$adjusted} {$mode}; total top-up ₱"
            . number_format($totalTopUpMinor / 100, 2)
            . '.'
        );

        return self::SUCCESS;
    }
}
