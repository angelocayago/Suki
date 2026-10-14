<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 50)->default('local');
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['complaint_id', 'created_at'], 'complaint_evidence_case_created_index');
            $table->index(['uploaded_by', 'created_at'], 'complaint_evidence_uploader_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_evidence');
    }
};
