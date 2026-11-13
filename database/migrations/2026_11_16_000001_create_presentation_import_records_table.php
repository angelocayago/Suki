<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_import_records', function (Blueprint $table) {
            $table->id();
            $table->string('batch_key', 80);
            $table->string('record_key', 120);
            $table->string('model_type', 180);
            $table->unsignedBigInteger('model_id');
            $table->timestamps();

            $table->unique(['batch_key', 'record_key']);
            $table->unique(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_import_records');
    }
};
