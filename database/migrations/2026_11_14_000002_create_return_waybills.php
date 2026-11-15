<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('return_waybills', function(Blueprint $table){
            $table->foreignId('return_refund_request_id')->primary()->constrained()->restrictOnDelete();
            $table->string('tracking_number',50)->unique();
            $table->json('buyer_pickup');
            $table->json('seller_return');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        if(DB::table('return_waybills')->exists())throw new RuntimeException('Retain issued return waybill references and address snapshots; destructive rollback refused.');
        Schema::dropIfExists('return_waybills');
    }
};
