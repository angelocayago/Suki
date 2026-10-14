<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_violation_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_violation_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 50)->default('local');
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['seller_violation_id', 'created_at'], 'violation_evidence_case_created_index');
            $table->index(['uploaded_by', 'created_at'], 'violation_evidence_uploader_created_index');
        });

        Schema::table('seller_violations', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'seller_violations_status_created_index');
            $table->index(['category', 'status'], 'seller_violations_category_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('seller_violations', function (Blueprint $table) {
            $table->dropIndex('seller_violations_status_created_index');
            $table->dropIndex('seller_violations_category_status_index');
        });

        Schema::dropIfExists('seller_violation_evidence');
    }
};
