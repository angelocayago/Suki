<?php

namespace App\Console\Commands;

use App\Models\SellerSettlement;
use App\Services\SellerWalletService;
use Illuminate\Console\Command;

class BackfillSellerWallets extends Command
{
    protected $signature = 'wallet:backfill-approved-settlements';

    protected $description =
        'Credit existing approved seller settlements into seller wallets exactly once.';

    public function handle(SellerWalletService $walletService): int
    {
        $count = 0;

        SellerSettlement::query()
            ->whereIn('status', ['approved', 'wallet_credited'])
            ->orderBy('id')
            ->chunkById(100, function ($settlements) use ($walletService, &$count) {
                foreach ($settlements as $settlement) {
                    $walletService->creditApprovedSettlement($settlement);
                    $count++;
                    $this->line('Processed settlement #' . $settlement->id);
                }
            });

        $this->info("Processed {$count} settlement(s).");

        return self::SUCCESS;
    }
}
