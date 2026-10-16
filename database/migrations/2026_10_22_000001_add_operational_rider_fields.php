<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table): void {
            // ONLINE preserves the prior meaning of an active, approved rider
            // during the gradual migration to explicit availability controls.
            $table->string('availability', 16)->default('ONLINE')->index()->after('is_active');
        });
        Schema::table('shipments', function (Blueprint $table): void {
            $table->timestamp('delivery_accepted_at')->nullable()->after('pickup_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', fn (Blueprint $table) => $table->dropColumn('delivery_accepted_at'));
        Schema::table('riders', fn (Blueprint $table) => $table->dropColumn('availability'));
    }
};
