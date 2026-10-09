<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('path');
            $table->timestamps();

            $table->unique(['seller_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_documents');
    }
};
