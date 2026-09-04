<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProductController extends Controller
{
    private function products()
    {
        return [

            [
                'name' => 'Minimalist Shoulder Bag',
                'slug' => 'shoulder-bag',
                'price' => 399,
                'old_price' => 599,
                'rating' => 4.9,
                'ratings' => 328,
                'sold' => '1.2k',
                'category' => 'Fashion',
                'stock' => 45,
                'discount' => '33% OFF',

                'image' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=1000&q=85',

                'images' => [
                    'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=1000&q=85',
                    'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=1000&q=85',
                    'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?auto=format&fit=crop&w=1000&q=85',
                    'https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?auto=format&fit=crop&w=1000&q=85',
                ],

                'colors' => [
                    'Black',
                    'Beige',
                    'Brown'
                ],

                'seller' => 'Everyday Finds PH',
                'seller_rating' => '98%',
                'seller_products' => '1.8k',

                'description' => 'A simple everyday shoulder bag designed for casual use. It features a clean minimalist design with enough space for your daily essentials.',

                'features' => [
                    'Lightweight and comfortable for everyday use.',
                    'Spacious main compartment.',
                    'Adjustable shoulder strap.',
                    'Suitable for casual and everyday outfits.',
                ],
            ],


            [
                'name' => 'Wireless Headphones',
                'slug' => 'wireless-headphones',
                'price' => 899,
                'old_price' => 1299,
                'rating' => 4.8,
                'ratings' => 245,
                'sold' => 856,
                'category' => 'Electronics',
                'stock' => 32,
                'discount' => '31% OFF',

                'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=1000&q=85',

                'images' => [
                    'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=1000&q=85',
                    'https://images.unsplash.com/photo-1484704849700-f032a568e944?auto=format&fit=crop&w=1000&q=85',
                    'https://images.unsplash.com/photo-1546435770-a3e426bf472b?auto=format&fit=crop&w=1000&q=85',
                ],

                'colors' => [
                    'Black',
                    'White'
                ],

                'seller' => 'Tech Finds PH',
                'seller_rating' => '97%',
                'seller_products' => '925',

                'description' => 'Wireless headphones with comfortable ear cushions and reliable sound quality for everyday listening.',

                'features' => [
                    'Wireless Bluetooth connectivity.',
                    'Comfortable over-ear design.',
                    'Clear audio for music and calls.',
                    'Long-lasting battery.',
                ],
            ],


            [
                'name' => 'Ceramic Home Set',
                'slug' => 'ceramic-home-set',
                'price' => 549,
                'old_price' => 799,
                'rating' => 4.7,
                'ratings' => 189,
                'sold' => 642,
                'category' => 'Home',
                'stock' => 24,
                'discount' => '31% OFF',

                'image' => 'https://images.unsplash.com/photo-1610701596007-11502861dcfa?auto=format&fit=crop&w=1000&q=85',

                'images' => [
                    'https://images.unsplash.com/photo-1610701596007-11502861dcfa?auto=format&fit=crop&w=1000&q=85',
                ],

                'colors' => [
                    'White',
                    'Cream',
                    'Gray'
                ],

                'seller' => 'Home Essentials PH',
                'seller_rating' => '96%',
                'seller_products' => '734',

                'description' => 'A minimalist ceramic set designed to complement modern kitchens and dining spaces.',

                'features' => [
                    'Simple minimalist design.',
                    'Durable ceramic material.',
                    'Suitable for everyday use.',
                    'Easy to clean.',
                ],
            ],


            [
                'name' => 'Everyday Sneakers',
                'slug' => 'everyday-sneakers',
                'price' => 799,
                'old_price' => 1099,
                'rating' => 4.9,
                'ratings' => 412,
                'sold' => '2.1k',
                'category' => 'Fashion',
                'stock' => 18,
                'discount' => '27% OFF',

                'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1000&q=85',

                'images' => [
                    'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1000&q=85',
                ],

                'colors' => [
                    'Red',
                    'Black',
                    'White'
                ],

                'seller' => 'Urban Steps PH',
                'seller_rating' => '99%',
                'seller_products' => '1.2k',

                'description' => 'Comfortable everyday sneakers designed for casual outfits, school, work, and daily activities.',

                'features' => [
                    'Lightweight construction.',
                    'Comfortable everyday fit.',
                    'Durable rubber outsole.',
                    'Suitable for casual wear.',
                ],
            ],


            [
                'name' => 'Skincare Essentials Set',
                'slug' => 'skincare-essentials',
                'price' => 459,
                'old_price' => 699,
                'rating' => 4.8,
                'ratings' => 267,
                'sold' => 934,
                'category' => 'Beauty',
                'stock' => 41,
                'discount' => '34% OFF',

                'image' => 'https://images.unsplash.com/photo-1556229010-6c3f2c9ca5f8?auto=format&fit=crop&w=1000&q=85',

                'images' => [
                    'https://images.unsplash.com/photo-1556229010-6c3f2c9ca5f8?auto=format&fit=crop&w=1000&q=85',
                ],

                'colors' => [],

                'seller' => 'Glow Essentials PH',
                'seller_rating' => '98%',
                'seller_products' => '645',

                'description' => 'A simple skincare essentials set for an easy and practical everyday routine.',

                'features' => [
                    'Suitable for everyday skincare routines.',
                    'Convenient set of essentials.',
                    'Travel-friendly packaging.',
                    'Easy to use.',
                ],
            ],


            [
                'name' => 'Classic Analog Watch',
                'slug' => 'analog-watch',
                'price' => 699,
                'old_price' => 999,
                'rating' => 4.8,
                'ratings' => 203,
                'sold' => 721,
                'category' => 'Fashion',
                'stock' => 15,
                'discount' => '30% OFF',

                'image' => 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?auto=format&fit=crop&w=1000&q=85',

                'images' => [
                    'https://images.unsplash.com/photo-1524805444758-089113d48a6d?auto=format&fit=crop&w=1000&q=85',
                ],

                'colors' => [
                    'Black',
                    'Brown',
                    'Silver'
                ],

                'seller' => 'Classic Goods PH',
                'seller_rating' => '97%',
                'seller_products' => '583',

                'description' => 'A classic analog watch with a clean design that works well with casual and formal outfits.',

                'features' => [
                    'Classic analog display.',
                    'Minimalist design.',
                    'Adjustable strap.',
                    'Suitable for everyday wear.',
                ],
            ],


            [
                'name' => 'Portable Bluetooth Speaker',
                'slug' => 'bluetooth-speaker',
                'price' => 649,
                'old_price' => 899,
                'rating' => 4.7,
                'ratings' => 156,
                'sold' => 534,
                'category' => 'Electronics',
                'stock' => 27,
                'discount' => '28% OFF',

                'image' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?auto=format&fit=crop&w=1000&q=85',

                'images' => [
                    'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?auto=format&fit=crop&w=1000&q=85',
                ],

                'colors' => [
                    'Black',
                    'Blue',
                    'White'
                ],

                'seller' => 'Sound Hub PH',
                'seller_rating' => '96%',
                'seller_products' => '412',

                'description' => 'A compact Bluetooth speaker designed for music at home, outdoors, or while traveling.',

                'features' => [
                    'Portable and lightweight.',
                    'Bluetooth connectivity.',
                    'Clear sound output.',
                    'Convenient rechargeable battery.',
                ],
            ],


            [
                'name' => 'Modern Table Lamp',
                'slug' => 'table-lamp',
                'price' => 499,
                'old_price' => 799,
                'rating' => 4.6,
                'ratings' => 134,
                'sold' => 438,
                'category' => 'Home',
                'stock' => 19,
                'discount' => '38% OFF',

                'image' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=1000&q=85',

                'images' => [
                    'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=1000&q=85',
                ],

                'colors' => [
                    'White',
                    'Black'
                ],

                'seller' => 'Modern Home PH',
                'seller_rating' => '95%',
                'seller_products' => '368',

                'description' => 'A modern table lamp that adds a clean and warm look to bedrooms, desks, and living spaces.',

                'features' => [
                    'Modern minimalist design.',
                    'Suitable for desks and bedside tables.',
                    'Compact size.',
                    'Easy to use.',
                ],
            ],

        ];
    }


    public function shop()
    {
        $products = $this->products();

        return view('buyer.shop', compact('products'));
    }


    public function show($slug)
    {
        $products = $this->products();

        $product = collect($products)->firstWhere('slug', $slug);

        if (!$product) {
            abort(404);
        }

        return view('buyer.products-details', compact('product', 'slug'));
    }
}
