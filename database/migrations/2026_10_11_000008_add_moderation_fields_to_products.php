<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('moderation_status', 20)->default('PENDING')->after('is_active');
            $table->text('moderation_reason')->nullable()->after('moderation_status');
            $table->foreignId('moderated_by')->nullable()->after('moderation_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable()->after('moderated_by');
            $table->index(['moderation_status', 'created_at'], 'products_moderation_created_index');
        });

        // Preserve products that were already available before moderation existed.
        DB::table('products')->update(['moderation_status' => 'APPROVED']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_moderation_created_index');
            $table->dropForeign(['moderated_by']);
            $table->dropColumn([
                'moderation_status',
                'moderation_reason',
                'moderated_by',
                'moderated_at',
            ]);
        });
    }
};
