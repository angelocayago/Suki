<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->restrictOnDelete();
            $table->string('name', 100);
            $code = $table->string('code', 32);
            if (DB::getDriverName() === 'sqlite') {
                $code->collation('NOCASE');
            } elseif (in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
                $code->collation('utf8mb4_unicode_ci');
            }
            $code->unique();
            $table->text('description')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->unsignedSmallInteger('discount_bps')->nullable();
            $table->unsignedInteger('discount_minor')->nullable();
            $table->unsignedInteger('minimum_minor')->default(0);
            $table->unsignedInteger('cap_minor')->nullable();
            $table->enum('product_scope', ['all', 'selected']);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('total_limit')->nullable();
            $table->unsignedInteger('per_buyer_limit');
            // No application action increments this counter in S-VOU-01.
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('terms_locked_at')->nullable();
            $table->enum('status', ['active', 'paused'])->default('paused');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'status', 'ends_at']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX seller_vouchers_code_folded_unique ON seller_vouchers (UPPER(code))');
        }
        Schema::create('seller_voucher_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_voucher_id')->constrained('seller_vouchers')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unique(['seller_voucher_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_voucher_products');
        Schema::dropIfExists('seller_vouchers');
    }
};
