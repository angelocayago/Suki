<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('name', 150)->nullable();
            $table->text('description')->nullable();
            // NULL preserves existing code-only vouchers and historical behavior.
            $table->string('audience', 20)->nullable()->index();
            $table->unsignedInteger('max_discount_minor')->nullable();
            $table->unsignedInteger('per_buyer_limit')->nullable();
            $table->timestamp('followers_notified_at')->nullable();
        });
        Schema::create('voucher_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('claimed_at');
            $table->unique(['voucher_id', 'buyer_id']);
            $table->index(['buyer_id', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_claims');
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['audience']);
            $table->dropColumn(['name', 'description', 'audience', 'max_discount_minor', 'per_buyer_limit', 'followers_notified_at']);
        });
    }
};
