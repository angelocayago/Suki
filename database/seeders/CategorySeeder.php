<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Electronics',
            'Fashion',
            'Beauty',
            'Home & Living',
            'Groceries',
            'Health',
            'Sports',
            'Toys & Hobbies',
            'Automotive',
            'Pet Supplies',
            'Books & Stationery',
            'Others',
        ] as $position => $name) {
            Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'position' => $position + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
