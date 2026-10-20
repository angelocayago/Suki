<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Seller;
use App\Models\User;
use App\Support\ProductImageUrl;
use App\Support\SellerCategories;
use App\Support\SellerProducts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerProductThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        SellerCategories::populate();
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->shop = Seller::create(['user_id' => $owner->id, 'name' => 'Thumbnail shop',
            'slug' => 'thumbnail-shop', 'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $this->actingAs($owner);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', file_get_contents(public_path('images/logo.png')));
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['name' => 'Thumbnail product', 'description' => 'Details',
            'subcategory' => SellerCategories::children($this->shop, SellerCategories::main($this->shop))->first()->id,
            'price' => 25, 'stock' => 3, 'status' => 'active'], $extra);
    }

    private function product(): Product
    {
        $this->post(route('seller.products.store'), $this->payload(['images' => [$this->image(), $this->image()]]))
            ->assertSessionHasNoErrors()->assertRedirect(route('seller.products'));

        return Product::sole();
    }

    public function test_each_image_has_hidden_targeted_picker_and_button_controls(): void
    {
        $product = $this->product();
        $response = $this->get(route('seller.products.edit', $product->id))->assertOk();
        foreach ($product->images as $image) {
            $response->assertSee('name="replacement_images['.$image->id.']"', false)
                ->assertSee('src="'.asset('storage/'.$image->path).'"', false);
        }
        $response->assertDontSee('name="replace_images"', false)
            ->assertDontSee('type="checkbox" name="remove_image_ids', false)
            ->assertSee('type="file" hidden disabled data-replacement', false)
            ->assertSee('data-replace', false)->assertSee('data-remove', false)
            ->assertSee('Undo removal')->assertSee('Will be replaced when you save.');
        $this->get(route('seller.products.create'))->assertOk()
            ->assertSee("label.textContent = 'Add Images'", false)
            ->assertSee("removeButton.textContent = 'Remove'", false);
    }

    public function test_individual_replacement_preserves_ids_positions_other_images_and_cover_after_refresh(): void
    {
        $product = $this->product();
        $images = $product->images;
        $beforeOther = $images[1]->toArray();
        $this->put(route('seller.products.update', $product->id), $this->payload([
            'replacement_images' => [$images[0]->id => $this->image()],
        ]))->assertSessionHasNoErrors();
        $replaced = $images[0]->fresh();
        $this->assertSame($images[0]->position, $replaced->position);
        $this->assertNotSame($images[0]->path, $replaced->path);
        $this->assertSame($beforeOther, $images[1]->fresh()->toArray());
        $this->assertDatabaseCount('product_images', 2);
        Storage::disk('public')->assertExists($replaced->path);
        Storage::disk('public')->assertExists($images[0]->path);
        $this->get('http://active.example/seller/products')->assertOk()
            ->assertSee('src="http://active.example/storage/'.$replaced->path.'"', false);
        $this->put(route('seller.products.update', $product->id), $this->payload([
            'remove_image_ids' => [$replaced->id],
        ]))->assertSessionHasNoErrors();
        $this->assertSame($beforeOther, $images[1]->fresh()->toArray());
        $this->assertSame(asset('storage/'.$images[1]->path), SellerProducts::listing($product->fresh())['image']);
    }

    public function test_later_replacement_does_not_change_cover_and_valid_additions_append_up_to_five(): void
    {
        $product = $this->product();
        $images = $product->images;
        $this->put(route('seller.products.update', $product->id), $this->payload([
            'replacement_images' => [$images[1]->id => $this->image()],
            'add_images' => [$this->image(), $this->image(), $this->image()],
        ]))->assertSessionHasNoErrors();
        $this->assertSame($images[0]->path, $images[0]->fresh()->path);
        $this->assertSame([0, 1, 2, 3, 4], $product->fresh()->images->pluck('position')->all());
        $this->assertDatabaseCount('product_images', 5);
        $files = Storage::disk('public')->allFiles();
        $this->put(route('seller.products.update', $product->id), $this->payload([
            'add_images' => [$this->image()],
        ]))->assertSessionHasErrors('add_images');
        $this->assertDatabaseCount('product_images', 5);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_invalid_individual_files_and_conflicting_operations_preserve_images(): void
    {
        $product = $this->product();
        $image = $product->images->first();
        $before = $product->fresh(['images', 'variants'])->toArray();
        $files = Storage::disk('public')->allFiles();
        $spoof = UploadedFile::fake()->createWithContent('fake.png', 'not an image');
        foreach ([UploadedFile::fake()->create('bad.txt'), new UploadedFile($spoof->getPathname(), 'fake.png', null, null, true),
            UploadedFile::fake()->createWithContent('large.png', file_get_contents(public_path('images/logo.png')).str_repeat('x', 5121 * 1024))] as $invalid) {
            $this->put(route('seller.products.update', $product->id), $this->payload([
                'replacement_images' => [$image->id => $invalid],
            ]))->assertSessionHasErrors('replacement_images.'.$image->id);
        }
        $this->put(route('seller.products.update', $product->id), $this->payload([
            'replacement_images' => [$image->id => $this->image()], 'remove_image_ids' => [$image->id],
        ]))->assertSessionHasErrors('replacement_images');
        $this->put(route('seller.products.update', $product->id), $this->payload([
            'add_images' => array_map(fn () => $this->image(), range(1, 6)),
        ]))->assertSessionHasErrors('add_images');
        $this->assertSame($before, $product->fresh(['images', 'variants'])->toArray());
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_foreign_replacement_ids_and_failed_database_save_leave_original_files_and_records(): void
    {
        $product = $this->product();
        $otherOwner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $otherShop = Seller::create(['user_id' => $otherOwner->id, 'name' => 'Other', 'slug' => 'other', 'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $other = $otherShop->products()->create(['name' => 'Other', 'slug' => 'other-product', 'category_id' => $product->category_id]);
        $foreign = $other->images()->create(['path' => 'products/foreign.png']);
        $sameShopProduct = $this->shop->products()->create(['name' => 'Same shop', 'slug' => 'same-shop', 'category_id' => $product->category_id]);
        $sameShopImage = $sameShopProduct->images()->create(['path' => 'products/same-shop.png']);
        $before = $product->fresh(['images', 'variants'])->toArray();
        $files = Storage::disk('public')->allFiles();
        foreach ([$foreign->id, $sameShopImage->id] as $id) {
            $this->put(route('seller.products.update', $product->id), $this->payload([
                'replacement_images' => [$id => $this->image()],
            ]))->assertSessionHasErrors('replacement_images');
            $this->put(route('seller.products.update', $product->id), $this->payload([
                'remove_image_ids' => [$id],
            ]))->assertSessionHasErrors('remove_image_ids');
        }
        $dispatcher = ProductImage::getEventDispatcher();
        ProductImage::setEventDispatcher(clone $dispatcher);
        ProductImage::updating(function () {
            throw new \RuntimeException('Simulated image save failure');
        });
        try {
            $this->put(route('seller.products.update', $product->id), $this->payload([
                'replacement_images' => [$product->images->first()->id => $this->image()],
            ]))->assertStatus(500);
        } finally {
            ProductImage::setEventDispatcher($dispatcher);
        }
        $this->assertSame($before, $product->fresh(['images', 'variants'])->toArray());
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_logout_uses_exact_labels_one_shared_dialog_and_protected_existing_form(): void
    {
        $response = $this->get(route('seller.products'))->assertOk();
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, '<dialog'));
        $response->assertSee('id="sellerLogoutTrigger"', false)->assertSee('id="sellerAccountLogout"', false)
            ->assertSee('form="sellerLogoutForm"', false)->assertSee('name="_token"', false)
            ->assertDontSee('No, Cancel')->assertDontSee('Yes, Log Out');
        $this->assertMatchesRegularExpression('/id="sellerLogoutCancel"[^>]*type="button"[^>]*>No<\/button>/s', $html);
        $this->assertMatchesRegularExpression('/id="sellerLogoutConfirm"[^>]*type="submit"[^>]*>Log Out<\/button>/s', $html);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('seller.products'))->assertRedirect();
    }

    public function test_logout_keeps_csrf_protection_and_current_redirect(): void
    {
        $this->app->instance('env', 'production');
        $this->post(route('logout'))->assertStatus(419);
        $this->assertAuthenticated();
        $this->withSession(['_token' => 'logout-token'])->post(route('logout'), ['_token' => 'logout-token'])
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_persisted_images_use_active_host_on_refresh_and_a_new_authenticated_session(): void
    {
        config(['filesystems.disks.public.url' => 'https://stale.example/storage']);
        $product = $this->product();
        $images = $product->images;
        $this->assertCount(2, $images);
        foreach ($images as $image) {
            Storage::disk('public')->assertExists($image->path);
            $this->assertSame($product->id, $image->product->id);
            $this->assertStringStartsWith('products/', $image->path);
        }
        $images[0]->update(['position' => 10]);
        $expected = 'http://seller.example:8123/storage/'.$images[1]->path;
        for ($i = 0; $i < 2; $i++) {
            $this->get('http://seller.example:8123/seller/products')->assertOk()
                ->assertSee('src="'.$expected.'"', false)->assertDontSee('stale.example');
        }
        $owner = $this->shop->owner;
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get('http://seller.example:8123/seller/products')->assertRedirect();
        $this->actingAs($owner)->get('http://seller.example:8123/seller/products')->assertOk()
            ->assertSee('src="'.$expected.'"', false);
        $this->assertDatabaseCount('product_images', 2);
    }

    public function test_legacy_references_are_read_without_rewriting_and_unsafe_paths_are_rejected(): void
    {
        $product = $this->product();
        $image = $product->images->first();
        $path = $image->path;
        foreach ([$path, 'storage/'.$path, '/storage/'.$path, 'https://old.example/storage/'.$path] as $reference) {
            $image->update(['path' => $reference]);
            $this->assertSame(asset('storage/'.$path), SellerProducts::listing($product->fresh())['image']);
            $this->assertSame($reference, $image->fresh()->path);
        }
        foreach (['../.env', 'products/../../.env', 'products/%2e%2e/secret', 'products\\secret', '//evil.example/a', 'https://evil.example/a', 'photo.png', 'storage/storage/'.$path] as $reference) {
            $this->assertNull(ProductImageUrl::url($reference), $reference);
        }
    }

    public function test_missing_primary_uses_next_available_and_missing_or_absent_images_show_placeholder(): void
    {
        $product = $this->product();
        $images = $product->images;
        Storage::disk('public')->delete($images[0]->path);
        $this->assertSame(asset('storage/'.$images[1]->path), SellerProducts::listing($product->fresh())['image']);
        Storage::disk('public')->delete($images[1]->path);
        $this->get(route('seller.products'))->assertOk()->assertSee('aria-label="No image"', false)
            ->assertDontSee($images[0]->path)->assertDontSee($images[1]->path);
        $this->assertDatabaseCount('product_images', 2);
        $this->assertDatabaseCount('products', 1);
        $this->post(route('seller.products.store'), ['submit_action' => 'draft'])->assertSessionHasNoErrors();
        $this->assertNull(SellerProducts::listing(Product::latest('id')->first())['image']);
    }

    public function test_explicit_replacement_and_removal_preserve_product_variants_and_old_files(): void
    {
        $product = $this->product();
        $old = $product->images;
        $variant = $product->variants->sole()->toArray();
        $url = route('seller.products.update', $product->id);
        $this->get(route('seller.products.edit', $product->id))->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)->assertSee('data-replace', false);
        $this->put($url, $this->payload(['replace_images' => 1, 'images' => [$this->image()]]))->assertSessionHasNoErrors();
        $image = $product->fresh()->images->sole();
        Storage::disk('public')->assertExists($image->path);
        foreach ($old as $previous) {
            Storage::disk('public')->assertExists($previous->path);
        }
        $this->assertSame($variant, $product->fresh()->variants->sole()->toArray());
        $this->assertDatabaseCount('product_images', 1);
        $this->put($url, $this->payload())->assertSessionHasNoErrors();
        $this->assertSame($image->id, $product->fresh()->images->sole()->id);
        $this->put($url, $this->payload(['remove_image_ids' => [$image->id]]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_images', 0);
        Storage::disk('public')->assertExists($image->path);
        $this->get(route('seller.products'))->assertOk()->assertSee('aria-label="No image"', false);
    }

    public function test_invalid_replacement_or_foreign_image_removal_rolls_back_without_orphan_uploads(): void
    {
        $product = $this->product();
        $other = $this->shop->products()->create(['name' => 'Other product', 'slug' => 'other', 'category_id' => $product->category_id]);
        $foreign = $other->images()->create(['path' => 'products/missing.png']);
        $before = $product->fresh(['images', 'variants'])->toArray();
        $files = Storage::disk('public')->allFiles();
        $url = route('seller.products.update', $product->id);
        $this->put($url, $this->payload(['replace_images' => 1]))->assertSessionHasErrors('images');
        $this->put($url, $this->payload(['replace_images' => 1, 'images' => [UploadedFile::fake()->create('bad.txt')]]))
            ->assertSessionHasErrors('images.0');
        $this->put($url, $this->payload(['replace_images' => 1, 'images' => [$this->image()], 'remove_image_ids' => [$foreign->id]]))
            ->assertSessionHasErrors('remove_image_ids');
        $this->assertSame($before, $product->fresh(['images', 'variants'])->toArray());
        $this->assertSame($files, Storage::disk('public')->allFiles());
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        Seller::create(['user_id' => $owner->id, 'name' => 'Foreign shop', 'slug' => 'foreign-shop', 'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $this->actingAs($owner)->put($url, $this->payload(['remove_image_ids' => [$product->images->first()->id]]))->assertNotFound();
        $this->assertSame($before, $product->fresh(['images', 'variants'])->toArray());
    }
}
