<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Pet Supplies', 'slug' => 'pet-supplies', 'description' => 'Food, accessories, and care for pets'],
            ['name' => 'Kids and Baby', 'slug' => 'kids-and-baby', 'description' => 'Products for babies and children'],
            ['name' => 'Electronics and Gadgets', 'slug' => 'electronics-and-gadgets', 'description' => 'Smartphones, computers, electronics, and gadgets'],
            ['name' => 'Home and Garden', 'slug' => 'home-and-garden', 'description' => 'Home, furniture, garden, and improvement products'],
            ['name' => "Women's Apparel", 'slug' => 'womens-apparel', 'description' => 'Clothing, footwear, and fashion for women'],
            ['name' => 'Sports and Outdoors', 'slug' => 'sports-and-outdoors', 'description' => 'Sports gear, fitness equipment, and outdoor products'],
            ['name' => "Men's Apparel", 'slug' => 'mens-apparel', 'description' => 'Clothing, footwear, and grooming products for men'],
            ['name' => 'Health and Beauty', 'slug' => 'health-and-beauty', 'description' => 'Health, beauty, cosmetics, and personal care products'],
            ['name' => 'Books and Media', 'slug' => 'books-and-media', 'description' => 'Books, magazines, music, movies, games, and educational media'],
            ['name' => 'Food and Gourmet', 'slug' => 'food-and-gourmet', 'description' => 'Food, beverages, baking supplies, and specialty grocery products'],
            ['name' => 'Furniture and Office Equipment', 'slug' => 'furniture-and-office-equipment', 'description' => 'Office furniture, storage, workstations, and workplace accessories'],
            ['name' => 'Jewelry and Watches', 'slug' => 'jewelry-and-watches', 'description' => 'Jewelry, watches, fashion accessories, and jewelry care'],
        ];

        $subcategoryMap = config('categories.subcategories', []);

        foreach ($categories as $category) {
            $mainCategory = Category::updateOrCreate(
                ['slug' => $category['slug']],
                array_merge($category, ['parent_id' => null])
            );

            foreach ($subcategoryMap[$category['slug']] ?? [] as $subcategory) {
                Category::updateOrCreate(
                    ['slug' => $subcategory['slug']],
                    [
                        'name' => $subcategory['name'],
                        'description' => $subcategory['name'],
                        'parent_id' => $mainCategory->id,
                    ]
                );
            }
        }
    }
}
