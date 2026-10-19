<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);
            $table->string('eligibility_key')->unique();
            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('seller_order_id')->constrained();
            $table->foreignId('order_item_id')->nullable()->constrained();
            $table->foreignId('seller_id')->constrained();
            $table->foreignId('product_id')->nullable()->constrained();
            $table->foreignId('rider_id')->nullable()->constrained();
            $table->unsignedTinyInteger('rating');
            $table->string('body', 500)->nullable();
            $table->boolean('edited')->default(false);
            $table->text('seller_response')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();
            $table->index(['kind', 'product_id', 'hidden_at']);
            $table->index(['kind', 'seller_id', 'hidden_at']);
        });
        Schema::create('review_moderation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_review_id')->constrained();
            $table->foreignId('admin_id')->constrained('users');
            $table->string('action', 16);
            $table->string('reason', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_moderation_events');
        Schema::dropIfExists('transaction_reviews');
    }
};
