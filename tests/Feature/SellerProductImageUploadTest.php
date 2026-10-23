<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Models\User;
use App\Support\SellerCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerProductImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    protected function setUp(): void
    {
        parent::setUp();
        SellerCategories::populate();
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->shop = Seller::create(['user_id' => $owner->id, 'name' => 'Image fixture', 'slug' => 'image-fixture', 'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $this->actingAs($owner);
    }

    private function subcategory(): int
    {
        return SellerCategories::children($this->shop, SellerCategories::main($this->shop))->first()->id;
    }

    public function test_seller_product_images_are_saved_and_the_first_is_the_main_image(): void
    {
        Storage::fake('public');

        $response = $this
            ->withSession([
                'seller_logged_in' => true,
                'seller_profile' => ['business_category' => 'Pet Supplies'],
            ])
            ->post(route('seller.products.store'), [
                'name' => 'Test product',
                'category' => "Women's Apparel",
                'subcategory' => $this->subcategory(),
                'description' => 'A product used for testing.',
                'price' => 25,
                'on_sale' => 1,
                'sale_price' => 20,
                'stock' => 3,
                'low_stock_threshold' => 4,
                'status' => 'active',
                'has_variations' => 1,
                'variation_name' => ['Color'],
                'variation_value' => ['Black'],
                'images' => [
                    UploadedFile::fake()->createWithContent(
                        'main.png',
                        file_get_contents(public_path('images/logo.png'))
                    ),
                    UploadedFile::fake()->createWithContent(
                        'detail.png',
                        file_get_contents(public_path('images/logo.png'))
                    ),
                ],
                'additional_image' => UploadedFile::fake()->createWithContent(
                    'supporting.png',
                    file_get_contents(public_path('images/logo.png'))
                ),
                'product_video' => UploadedFile::fake()->create(
                    'demo.mp4',
                    1,
                    'video/mp4'
                ),
            ]);

        $response->assertRedirect(route('seller.products'));
        $response->assertSessionHas('seller_products.P0001', function (array $product): bool {
            return count($product['images']) === 2
                && $product['image'] === $product['images'][0]
                && $product['additional_image'] !== null
                && $product['product_video'] !== null
                && $product['category'] === 'Pet Supplies'
                && $product['subcategory'] === 'Dog Food & Treats'
                && $product['price'] === 20.0
                && $product['regular_price'] === 25.0
                && $product['low_stock_threshold'] === 4
                && $product['has_variations']
                && $product['variation_names'] === ['Color'];
        });

        $this->assertCount(4, Storage::disk('public')->allFiles('products'));
        $this->assertCount(1, Storage::disk('public')->allFiles('products/videos'));
    }

    public function test_seller_product_requires_at_least_one_image(): void
    {
        $this
            ->withSession([
                'seller_logged_in' => true,
                'seller_profile' => ['business_category' => 'Pet Supplies'],
            ])
            ->post(route('seller.products.store'), [
                'name' => 'Test product',
                'category' => 'Accessories',
                'subcategory' => $this->subcategory(),
                'description' => 'A product used for testing.',
                'price' => 25,
                'stock' => 3,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('images');
    }

    public function test_seller_can_save_an_incomplete_product_as_a_draft(): void
    {
        $response = $this
            ->withSession([
                'seller_logged_in' => true,
                'seller_profile' => ['business_category' => 'Pet Supplies'],
            ])
            ->post(route('seller.products.store'), [
                'submit_action' => 'draft',
            ]);

        $response->assertRedirect(route('seller.products'));
        $response->assertSessionHas('seller_products.P0001', function (array $product): bool {
            return $product['status'] === 'draft'
                && $product['name'] === null
                && $product['image'] === null;
        });

        $this
            ->withSession([
                'seller_logged_in' => true,
                'seller_products' => [
                    'P0001' => [
                        'id' => 'P0001',
                        'name' => null,
                        'category' => null,
                        'description' => null,
                        'price' => null,
                        'stock' => 0,
                        'status' => 'draft',
                    ],
                ],
            ])
            ->put(route('seller.products.update', 'P0001'), [
                'name' => 'Completed draft',
                'subcategory' => $this->subcategory(),
                'category' => 'Accessories',
                'description' => 'Product details completed.',
                'price' => 25,
                'stock' => 3,
                'sku' => 'DRAFT-1',
                'status' => 'active',
            ])
            ->assertRedirect(route('seller.products'))
            ->assertSessionHas('seller_products.P0001.name', 'Completed draft')
            ->assertSessionHas('seller_products.P0001.status', 'active');
    }

    public function test_seller_registration_displays_the_supported_business_categories(): void
    {
        $response = $this->get(route('seller.register'));

        $response->assertOk();

        foreach ([
            'Pet Supplies',
            'Kids and Baby',
            'Electronics and Gadgets',
            'Home and Garden',
            "Women's Apparel",
            "Men's Apparel",
            'Health and Beauty',
            'Sports and Outdoors',
        ] as $category) {
            $response->assertSeeText($category);
        }

        $response->assertDontSee('value="Food"');
    }

    public function test_add_product_shows_only_the_registered_business_category(): void
    {
        $this->shop->update(['business_category' => "Women's Apparel"]);
        $response = $this
            ->withSession([
                'seller_logged_in' => true,
                'seller_profile' => ['business_category' => "Women's Apparel"],
            ])
            ->get(route('seller.products.create'));

        $response->assertOk();
        $response->assertDontSee('name="category"', false);
        $response->assertSee('value="Women&#039;s Apparel"', false);
        $response->assertSee('value="'.$this->subcategory().'"', false);
        $response->assertSeeText('Dresses & Skirts');
        $response->assertDontSee('value="Pet Food"', false);
    }

    public function test_seller_registration_rejects_unlisted_business_categories(): void
    {
        $this->post(route('seller.register.submit'), [
            'business_category' => 'Food',
        ])->assertSessionHasErrors('business_category');
    }
}
