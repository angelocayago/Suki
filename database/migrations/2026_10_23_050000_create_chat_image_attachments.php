<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('seller_messages')->restrictOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('path')->unique();
            $table->string('mime', 30);
            $table->unsignedInteger('bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('source_sha256', 64);
            $table->timestamps();
            $table->unique(['message_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_message_attachments');
    }
};
