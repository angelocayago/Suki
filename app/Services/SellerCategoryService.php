<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Seller;
use Illuminate\Support\Collection;

class SellerCategoryService
{
    /**
     * Return active descendants of the seller's assigned root category.
     * The legacy category_seller pivot is intentionally not an authority.
     */
    public function allowedCategories(Seller $seller): Collection
    {
        if (! $seller->category_id) {
            return collect();
        }

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $byId = $categories->keyBy('id');
        if (! $byId->has($seller->category_id)) {
            return collect();
        }

        $children = $categories->groupBy(fn (Category $category) => (int) ($category->parent_id ?? 0));
        $allowed = collect();
        $visited = [];
        $visit = function (int $categoryId, int $depth) use (&$visit, &$visited, $byId, $children, $allowed): void {
            if (isset($visited[$categoryId])) {
                return;
            }

            $category = $byId->get($categoryId);
            if (! $category) {
                return;
            }

            $visited[$categoryId] = true;
            $category->setAttribute('display_name', str_repeat('-- ', $depth).$category->name);
            if ($depth > 0) {
                $allowed->push($category);
            }

            foreach ($children->get($categoryId, collect()) as $child) {
                $visit((int) $child->id, $depth + 1);
            }
        };

        $visit((int) $seller->category_id, 0);

        return $allowed;
    }

    public function allows(Seller $seller, int $categoryId): bool
    {
        return $this->allowedCategories($seller)
            ->contains(fn (Category $category) => (int) $category->id === $categoryId);
    }
}
