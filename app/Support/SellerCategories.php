<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Seller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SellerCategories
{
    // Main-category synonyms only: preserve persisted names and identifiers.
    private const MAIN_ALIASES = [
        'Kids and Baby Apparel' => 'Kids and Baby',
        'Office and School Supplies Group' => 'Office and School Supplies',
    ];

    public static function normalize(string $name): string
    {
        return Str::lower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    public static function main(Seller $seller): ?Category
    {
        $name = self::name($seller->business_category);
        if ($name === null) {
            return null;
        }

        $matches = Category::whereNull('parent_id')->get()
            ->filter(fn ($category) => self::name($category->name) === $name);

        return $matches->count() === 1 && $matches->first()->is_active ? $matches->first() : null;
    }

    public static function name(?string $name): ?string
    {
        $normalized = self::normalize($name ?? '');
        foreach (self::MAIN_ALIASES as $alias => $preferred) {
            if (self::normalize($alias) === $normalized) {
                return $preferred;
            }
        }
        foreach (array_keys(config('seller_categories', [])) as $preferred) {
            if (self::normalize($preferred) === $normalized) {
                return $preferred;
            }
        }

        return null;
    }

    public static function display(?string $name): string
    {
        return self::name($name) ?? $name ?? '';
    }

    public static function children(Seller $seller, ?Category $main): Collection
    {
        if (! $main) {
            return collect();
        }

        $names = array_map(self::normalize(...), config('seller_categories')[self::name($seller->business_category)]);

        return $main->children()->where('is_active', true)->orderBy('position')->get()
            ->filter(fn ($child) => in_array(self::normalize($child->name), $names, true))->values();
    }

    // Never rename, move, activate or delete existing shared categories.
    // Ambiguous matches roll back the entire reference setup.
    public static function populate(): void
    {
        DB::transaction(function () {
            foreach (config('seller_categories') as $name => $children) {
                $main = self::reference($name, null);
                foreach ($children as $position => $child) {
                    self::reference($child, $main, $position);
                }
            }
        });
    }

    private static function reference(string $name, ?Category $parent, int $position = 0): Category
    {
        $matches = Category::where('parent_id', $parent?->id)->get()
            ->filter(fn ($record) => $parent
                ? self::normalize($record->name) === self::normalize($name)
                : self::name($record->name) === $name);
        if ($matches->count() > 1) {
            throw new RuntimeException('Ambiguous category reference: '.$name.'. Resolve duplicates before setup.');
        }
        if ($matches->count() === 1) {
            return $matches->first();
        }

        $slug = Str::slug(($parent ? $parent->slug.' ' : '').$name);
        if (Category::where('slug', $slug)->exists()) {
            throw new RuntimeException('Category slug conflict: '.$slug.'. Existing records were preserved.');
        }

        return Category::create(['name' => $name, 'slug' => $slug, 'parent_id' => $parent?->id, 'position' => $position, 'is_active' => true]);
    }
}
