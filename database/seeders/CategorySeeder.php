<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $taxonomy = [
            'Electronics & Gadgets' => ['Mobile Phones & Accessories', 'Computers & Laptops', 'Audio & Video', 'Cameras & Photography', 'Wearables', 'Smart Home & Accessories'],
            "Women's Fashion" => ['Tops & Blouses', 'Dresses & Skirts', 'Pants & Bottoms', 'Outerwear', 'Shoes', 'Bags & Accessories'],
            "Men's Fashion" => ['Shirts', 'Pants & Jeans', 'Shorts', 'Outerwear', 'Shoes', 'Accessories'],
            'Kids & Baby' => ['Baby Clothing', 'Kids Clothing', 'Baby Care', 'Toys for Kids', 'Nursery & Gear', 'School Essentials'],
            'Health & Beauty' => ['Skincare', 'Makeup & Cosmetics', 'Hair Care', 'Personal Care', 'Fragrances', 'Health Essentials'],
            'Home & Living' => ['Kitchen & Dining', 'Home Decor', 'Bedding & Bath', 'Storage & Organization', 'Cleaning Supplies', 'Home Improvement'],
            'Furniture & Office' => ['Living Room Furniture', 'Bedroom Furniture', 'Office Furniture', 'Office Supplies', 'Desks & Chairs', 'Lighting'],
            'Groceries & Food' => ['Snacks', 'Beverages', 'Pantry Essentials', 'Cooking Ingredients', 'Fresh & Packaged Food', 'Specialty Food'],
            'Sports & Outdoors' => ['Exercise & Fitness', 'Sports Equipment', 'Outdoor Recreation', 'Cycling', 'Sportswear', 'Camping & Hiking'],
            'Toys & Hobbies' => ['Toys', 'Games', 'Collectibles', 'Arts & Crafts', 'Musical Instruments', 'Hobby Supplies'],
            'Automotive' => ['Car Accessories', 'Motorcycle Accessories', 'Replacement Parts', 'Car Care', 'Tools & Equipment', 'Electronics & Navigation'],
            'Pet Supplies' => ['Dog Supplies', 'Cat Supplies', 'Pet Food', 'Grooming', 'Pet Accessories', 'Other Pet Supplies'],
            'Books & Stationery' => ['Books', 'School Supplies', 'Office Stationery', 'Art Supplies', 'Writing Materials', 'Paper Products'],
            'Jewelry & Watches' => ['Necklaces', 'Bracelets', 'Rings', 'Earrings', 'Watches', 'Fashion Jewelry'],
            'Others' => ['Miscellaneous'],
        ];

        $rootPosition = 0;
        foreach ($taxonomy as $rootName => $children) {
            $rootPosition++;
            $rootSlug = Str::slug($rootName);
            $root = Category::updateOrCreate(['slug' => $rootSlug], [
                'parent_id' => null,
                'name' => $rootName,
                'position' => $rootPosition,
                'is_active' => true,
            ]);

            foreach ($children as $childPosition => $childName) {
                Category::updateOrCreate(['slug' => $rootSlug.'--'.Str::slug($childName)], [
                    'parent_id' => $root->id,
                    'name' => $childName,
                    'position' => $childPosition + 1,
                    'is_active' => true,
                ]);
            }
        }
    }
}
