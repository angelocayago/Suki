<?php
namespace App\Console\Commands;
use App\Models\{Shipment,RiderWalletTransaction};
use App\Services\RiderWalletService;
use Illuminate\Console\Command;
class BackfillRiderWallets extends Command {
    protected $signature='rider-wallet:backfill-delivered {--chunk=100}';
    protected $description='Credit rider wallets for delivered shipments not yet credited.';
    public function handle(RiderWalletService $service): int {
        $checked=0;$credited=0;
        Shipment::where('status','delivered')->orderBy('id')->chunkById((int)$this->option('chunk'),function($rows)use($service,&$checked,&$credited){
            foreach($rows as $s){ $checked++; $before=RiderWalletTransaction::where('shipment_id',$s->id)->where('type','delivery_credit')->exists(); $service->creditDeliveredShipment($s); $after=RiderWalletTransaction::where('shipment_id',$s->id)->where('type','delivery_credit')->exists(); if(!$before&&$after)$credited++; }
        });
        $this->info("Checked {$checked} delivered shipments; credited {$credited} new rider earnings.");
        return self::SUCCESS;
    }
}
