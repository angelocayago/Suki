<?php

namespace Tests\Feature;

use Tests\TestCase;

class BuyerShopFiltersTest extends TestCase
{
    public function test_shop_search_matches_product_names(): void
    {
        $this->get(route('buyer.shop', ['search' => 'headphones']))
            ->assertOk()
            ->assertSee('Wireless Headphones')
            ->assertDontSee('Minimalist Shoulder Bag');
    }

    public function test_shop_filters_by_category(): void
    {
        $this->get(route('buyer.shop', ['category' => ['Fashion']]))
            ->assertOk()
            ->assertSee('Everyday Sneakers')
            ->assertDontSee('Wireless Headphones');
    }

    public function test_shop_filters_by_price_range(): void
    {
        $this->get(route('buyer.shop', [
            'min_price' => 500,
            'max_price' => 700,
        ]))
            ->assertOk()
            ->assertSee('Ceramic Home Set')
            ->assertSee('Portable Bluetooth Speaker')
            ->assertDontSee('Minimalist Shoulder Bag');
    }

    public function test_shop_sorts_by_price_from_low_to_high(): void
    {
        $this->get(route('buyer.shop', ['sort' => 'price_asc']))
            ->assertOk()
            ->assertSeeInOrder([
                'Minimalist Shoulder Bag',
                'Skincare Essentials Set',
                'Modern Table Lamp',
            ]);
    }
}