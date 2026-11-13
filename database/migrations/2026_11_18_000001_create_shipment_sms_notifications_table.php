<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipment_sms_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('logistics_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('retry_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);
            $table->string('recipient_key', 80);
            $table->string('recipient_phone', 16)->nullable();
            $table->string('recipient_masked', 24)->nullable();
            $table->text('body');
            $table->string('driver', 30);
            $table->string('status', 20)->default('PENDING');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('provider_message_id', 120)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('retried_at')->nullable();
            $table->timestamps();
            $table->unique(['shipment_id', 'event', 'recipient_key'], 'shipment_sms_event_recipient_unique');
            $table->index(['status', 'created_at']);
            $table->index(['logistics_provider_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_sms_notifications');
    }
};
