<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_cleanup_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->json('before');
            $table->json('after')->nullable();
            $table->string('reason', 255);
            $table->timestamps();
            $table->index(['product_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_cleanup_audits');
    }
};
