<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('logistics_provider_id')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        // Intentionally left nullable so rollback cannot invalidate
        // shipments created before logistics provider assignment.
    }
};
