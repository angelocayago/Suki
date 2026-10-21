<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // MySQL does not roll back DDL after a failed create; this only clears
        // the incomplete table from a previous attempt of this uncommitted migration.
        Schema::dropIfExists('logistics_cod_remittance_items');
        Schema::dropIfExists('logistics_cod_remittances');
        Schema::dropIfExists('rider_cod_remittances');
        Schema::create('rider_cod_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->unique();
            $table->foreignId('seller_order_id')->constrained();
            $table->foreignId('rider_id')->constrained();
            $table->foreignId('logistics_provider_id')->constrained();
            $table->unsignedBigInteger('expected_minor');
            $table->unsignedBigInteger('submitted_minor')->nullable();
            $table->unsignedBigInteger('accepted_minor')->nullable();
            $table->string('status')->default('OUTSTANDING');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('reason')->nullable();
            $table->string('reference')->nullable();
            $table->json('evidence_metadata')->nullable();
            $table->timestamps();
            $table->index(['logistics_provider_id', 'status']);
            $table->index(['rider_id', 'status']);
        });
        Schema::create('logistics_cod_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_provider_id')->constrained();
            $table->unsignedBigInteger('expected_minor');
            $table->unsignedBigInteger('submitted_minor')->nullable();
            $table->unsignedBigInteger('accepted_minor')->nullable();
            $table->string('status')->default('DRAFT');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('reason')->nullable();
            $table->string('reference')->nullable();
            $table->json('evidence_metadata')->nullable();
            $table->timestamps();
            $table->index(['logistics_provider_id', 'status']);
        });
        Schema::create('logistics_cod_remittance_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistics_cod_remittance_id');
            $table->unsignedBigInteger('rider_cod_remittance_id')->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
            $table->foreign('logistics_cod_remittance_id', 'cod_lri_logistics_fk')->references('id')->on('logistics_cod_remittances')->cascadeOnDelete();
            $table->foreign('rider_cod_remittance_id', 'cod_lri_rider_fk')->references('id')->on('rider_cod_remittances');
        });
        Schema::create('cod_remittance_audits', function (Blueprint $table) {
            $table->id(); $table->string('subject_type'); $table->unsignedBigInteger('subject_id');
            $table->foreignId('actor_id')->nullable()->constrained('users'); $table->string('action');
            $table->text('reason')->nullable(); $table->json('metadata')->nullable(); $table->timestamp('occurred_at');
            $table->index(['subject_type', 'subject_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('cod_remittance_audits'); Schema::dropIfExists('logistics_cod_remittance_items'); Schema::dropIfExists('logistics_cod_remittances'); Schema::dropIfExists('rider_cod_remittances'); }
};
