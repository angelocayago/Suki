<?php

namespace App\Services\Admin;

use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\ProductModerationEvent;
use App\Models\User;
use App\Services\SellerCategoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductModerationService
{
    private const ADMIN_TRANSITIONS = [
        'PENDING' => ['APPROVED', 'REJECTED'],
        'APPROVED' => ['HIDDEN'],
        'HIDDEN' => ['APPROVED'],
    ];

    public function __construct(private readonly SellerCategoryService $categories) {}

    public function approve(Product $product, User $actor): Product
    {
        return $this->transition($product, ProductModerationStatus::Approved, $actor);
    }

    public function recordInitialSubmission(Product $product, User $actor): Product
    {
        return DB::transaction(function () use ($product, $actor) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $this->ensureOwningSeller($locked, $actor);

            if (! $locked->moderationEvents()->exists()) {
                $this->record(
                    $locked,
                    $actor,
                    null,
                    ProductModerationStatus::Pending,
                    'Product submitted for moderation.',
                    'seller_submission'
                );
            }

            return $locked;
        }, 3);
    }

    public function reject(Product $product, User $actor, string $reason): Product
    {
        return $this->transition($product, ProductModerationStatus::Rejected, $actor, $reason);
    }

    public function hide(Product $product, User $actor, string $reason): Product
    {
        return $this->transition($product, ProductModerationStatus::Hidden, $actor, $reason);
    }

    public function restore(Product $product, User $actor): Product
    {
        return $this->transition($product, ProductModerationStatus::Approved, $actor);
    }

    public function returnToPendingAfterMaterialEdit(Product $product, User $actor): Product
    {
        return DB::transaction(function () use ($product, $actor) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $current = $locked->moderation_status;

            if ($current === ProductModerationStatus::Pending) {
                return $locked;
            }

            $this->ensureOwningSeller($locked, $actor);

            $locked->update([
                'moderation_status' => ProductModerationStatus::Pending->value,
                'moderation_reason' => null,
                'moderated_by' => null,
                'moderated_at' => null,
            ]);

            $this->record(
                $locked,
                $actor,
                $current,
                ProductModerationStatus::Pending,
                'Product returned to review after material seller changes.',
                'seller_material_edit'
            );

            return $locked->fresh();
        }, 3);
    }

    private function transition(
        Product $product,
        ProductModerationStatus $next,
        User $actor,
        ?string $reason = null
    ): Product {
        return DB::transaction(function () use ($product, $next, $actor, $reason) {
            $this->ensureAdmin($actor);
            $locked = Product::query()->with('seller.category')->lockForUpdate()->findOrFail($product->id);
            $current = $locked->moderation_status;

            if (! in_array($next->value, self::ADMIN_TRANSITIONS[$current->value] ?? [], true)) {
                throw ValidationException::withMessages([
                    'moderation_status' => "Product cannot move from {$current->value} to {$next->value}.",
                ]);
            }

            if (in_array($next, [ProductModerationStatus::Rejected, ProductModerationStatus::Hidden], true) && blank($reason)) {
                throw ValidationException::withMessages([
                    'reason' => 'A reason is required for this moderation action.',
                ]);
            }

            if ($next === ProductModerationStatus::Approved && ! $this->categories->allows($locked->seller, (int) $locked->category_id)) {
                throw ValidationException::withMessages([
                    'category' => 'This product category is not approved for the seller.',
                ]);
            }

            $locked->update([
                'moderation_status' => $next->value,
                'moderation_reason' => $reason,
                'moderated_by' => $actor->id,
                'moderated_at' => now(),
            ]);

            $this->record($locked, $actor, $current, $next, $reason, 'admin_portal');

            return $locked->fresh();
        }, 3);
    }

    private function record(
        Product $product,
        User $actor,
        ?ProductModerationStatus $previous,
        ProductModerationStatus $next,
        ?string $reason,
        string $source
    ): void {
        ProductModerationEvent::create([
            'product_id' => $product->id,
            'seller_id' => $product->seller_id,
            'actor_id' => $actor->id,
            'previous_status' => $previous?->value,
            'new_status' => $next->value,
            'reason' => $reason,
            'source' => $source,
            'metadata' => [
                'product_name' => $product->name,
                'category_id' => $product->category_id,
            ],
        ]);
    }

    private function ensureAdmin(User $actor): void
    {
        abort_unless($actor->isAdmin() && $actor->status === 'active' && ! $actor->is_suspended, 403);
    }

    private function ensureOwningSeller(Product $product, User $actor): void
    {
        abort_unless((int) $product->seller?->user_id === (int) $actor->id, 403);
    }
}
