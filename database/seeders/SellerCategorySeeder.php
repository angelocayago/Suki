<?php

namespace Database\Seeders;

use App\Support\SellerCategories;
use Illuminate\Database\Seeder;

class SellerCategorySeeder extends Seeder
{
    public function run(): void
    {
        SellerCategories::populate();
    }
}
