<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('order_items', function (Blueprint $table) { $table->unsignedBigInteger('seller_funded_discount_minor')->default(0)->after('commission_minor'); $table->unsignedBigInteger('platform_funded_discount_minor')->default(0)->after('seller_funded_discount_minor'); });
        Schema::create('return_refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained()->restrictOnDelete();
            $table->string('type', 20); // RETURN or REFUND
            $table->string('status', 30)->default('SUBMITTED');
            $table->unsignedInteger('quantity');
            $table->string('buyer_reason', 100);
            $table->text('buyer_description');
            $table->string('seller_recommendation', 20)->nullable();
            $table->text('seller_note')->nullable();
            $table->timestamp('seller_responded_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_decision', 20)->nullable();
            $table->text('admin_reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedBigInteger('refund_minor')->nullable();
            $table->timestamps();
            $table->unique(['order_item_id', 'status'], 'return_refund_item_status_unique');
            $table->index(['seller_id', 'status']);
            $table->index(['buyer_id', 'status']);
        });
        Schema::create('return_refund_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_refund_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->json('data')->nullable();
            $table->timestamps();
            $table->index(['return_refund_request_id', 'created_at']);
        });
        Schema::create('return_refund_evidence', function (Blueprint $table) {
            $table->id(); $table->foreignId('return_refund_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('path'); $table->string('mime', 100); $table->unsignedInteger('size'); $table->string('description', 500)->nullable(); $table->timestamps();
            $table->index(['return_refund_request_id','created_at']);
        });
        Schema::create('return_refund_accounting', function (Blueprint $table) {
            $table->id(); $table->foreignId('return_refund_request_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('merchandise_minor'); $table->unsignedBigInteger('seller_adjustment_minor');
            $table->unsignedBigInteger('commission_reversal_minor'); $table->unsignedBigInteger('seller_voucher_minor')->default(0); $table->unsignedBigInteger('platform_voucher_minor')->default(0);
            $table->string('refund_status', 30)->default('REFUND_DUE'); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('return_refund_accounting'); Schema::dropIfExists('return_refund_evidence'); Schema::dropIfExists('return_refund_events'); Schema::dropIfExists('return_refund_requests'); Schema::table('order_items', function (Blueprint $table) { $table->dropColumn(['seller_funded_discount_minor','platform_funded_discount_minor']); }); }
};
