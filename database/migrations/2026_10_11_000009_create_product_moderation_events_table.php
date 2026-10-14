<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_moderation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->text('reason')->nullable();
            $table->string('source', 50)->default('admin_portal');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['seller_id', 'created_at']);
            $table->index(['new_status', 'created_at']);
        });

        $now = now();
        DB::table('products')
            ->select(['id', 'seller_id', 'name', 'category_id'])
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($now) {
                DB::table('product_moderation_events')->insert(
                    $products->map(fn ($product) => [
                        'product_id' => $product->id,
                        'seller_id' => $product->seller_id,
                        'actor_id' => null,
                        'previous_status' => null,
                        'new_status' => 'APPROVED',
                        'reason' => 'Existing product preserved during moderation rollout.',
                        'source' => 'legacy_backfill',
                        'metadata' => json_encode([
                            'product_name' => $product->name,
                            'category_id' => $product->category_id,
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_moderation_events');
    }
};
