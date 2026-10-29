
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->string('reschedule_status', 20)
                ->default('none');

            $table->text('reschedule_reason')
                ->nullable();

            $table->timestamp('reschedule_requested_at')
                ->nullable();

            $table->timestamp('rescheduled_at')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn([
                'reschedule_status',
                'reschedule_reason',
                'reschedule_requested_at',
                'rescheduled_at',
            ]);
        });
    }
};
