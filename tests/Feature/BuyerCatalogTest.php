<?php

namespace Tests\Feature;

use App\Models\{CartItem, Category, Product, ProductVariant, Seller, User, WishlistItem};
use App\Services\ProductCatalogService;
use Illuminate\Support\Facades\{DB, Http, Storage};
use Tests\Support\{BuyerPurchasesSchema, IsolatedStorageTestCase};

class BuyerCatalogTest extends IsolatedStorageTestCase
{
    private User $buyer;
    private Seller $seller;
    private Category $category;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('SQLite memory required.');
        parent::setUp();
        $this->assertStoragePathIsIsolated();
        BuyerPurchasesSchema::create();
        foreach (['product_id', 'seller_id', 'buyer_id', 'seller_order_id', 'order_item_id', 'rider_id', 'rating'] as $column) DB::statement('ALTER TABLE transaction_reviews ADD COLUMN '.$column.' INTEGER');
        foreach (['kind', 'eligibility_key', 'body', 'seller_response', 'hidden_at', 'edited'] as $column) DB::statement('ALTER TABLE transaction_reviews ADD COLUMN '.$column.' TEXT');
        // Disposable fixtures only: no migrations or configured application database.
        $cart = new CartItem();
        DB::statement('CREATE TABLE cart_items (id INTEGER PRIMARY KEY)');
        foreach (array_unique([...$cart->getFillable(), 'created_at', 'updated_at']) as $column) {
            $type = str_ends_with($column, '_id') || in_array($column, ['quantity', 'selected'], true) ? 'INTEGER' : 'TEXT';
            DB::statement('ALTER TABLE cart_items ADD COLUMN '.$column.' '.$type);
        }
        DB::statement('CREATE UNIQUE INDEX catalog_cart_variant ON cart_items(user_id, product_variant_id)');
        Http::preventStrayRequests(); Storage::fake('public');
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false]);
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $this->seller = Seller::create(['user_id' => $owner->id, 'name' => 'Catalog Shop', 'slug' => 'catalog-shop', 'status' => 'approved']);
        $this->category = Category::create(['name' => 'Clothing', 'slug' => 'clothing', 'is_active' => true]);
    }

    private function product(string $name, array $prices = [10000], array $changes = []): Product
    {
        $product = Product::create($changes + ['seller_id' => $this->seller->id, 'category_id' => $this->category->id,
            'name' => $name, 'slug' => str($name)->slug().'-'.Product::count(), 'description' => 'Everyday cotton item', 'is_active' => true, 'moderation_status' => 'APPROVED']);
        foreach ($prices as $index => $price) $product->variants()->create(['sku' => 'CAT-'.$product->id.'-'.$index, 'name' => 'Option '.$index, 'price_minor' => $price, 'stock' => 5, 'is_active' => true]);
        return $product;
    }

    private function page(array $filters = []): array
    {
        return app(ProductCatalogService::class)->marketplacePage($filters)->items();
    }

    public function test_rating_filter_uses_visible_review_averages_and_tracks_review_changes(): void
    {
        $rated = $this->product('Rated product', [15000]);
        $perfect = $this->product('Perfect product', [20000]);
        $unrated = $this->product('Unrated product');
        $review = \App\Models\TransactionReview::create(['product_id' => $rated->id, 'kind' => 'product', 'rating' => 4]);
        \App\Models\TransactionReview::create(['product_id' => $rated->id, 'kind' => 'product', 'rating' => 5]);
        \App\Models\TransactionReview::create(['product_id' => $rated->id, 'kind' => 'product', 'rating' => 1, 'hidden_at' => now()]);
        \App\Models\TransactionReview::create(['product_id' => $rated->id, 'kind' => 'seller', 'rating' => 1]);
        \App\Models\TransactionReview::create(['product_id' => $perfect->id, 'kind' => 'product', 'rating' => 5]);
        $filters = ['rating' => '4', 'categories' => [$this->category->id], 'seller' => $this->seller->slug, 'q' => 'product', 'in_stock' => '1', 'min_price' => '150', 'max_price' => '200', 'sort' => 'price_asc'];
        $rows = $this->page($filters);
        $this->assertSame([$rated->id, $perfect->id], array_column($rows, 'id'));
        $this->assertSame(4.5, $rows[0]['rating']); $this->assertSame(2, $rows[0]['ratings']);
        $this->assertSame([$perfect->id], array_column($this->page(['rating' => '5']), 'id'));
        $response = $this->get(route('buyer.shop', $filters))->assertOk()->assertSee('4.5 (2)')->assertSee('4 stars &amp; up', false)->assertDontSee('Unrated product');
        $this->assertStringContainsString('rating=4', $response->getContent());
        $this->get(route('buyer.shop'))->assertOk()->assertSee('No ratings yet')->assertSee($unrated->name);
        $review->update(['rating' => 1]);
        $this->assertNotContains($rated->id, array_column($this->page(['rating' => '4']), 'id'));
        $review->update(['hidden_at' => now()]);
        $this->assertContains($rated->id, array_column($this->page(['rating' => '5']), 'id'));
        \App\Models\TransactionReview::where('product_id', $rated->id)->delete();
        $this->assertNotContains($rated->id, array_column($this->page(['rating' => '1']), 'id'));
        foreach (['invalid', '0', '6', '4.5', ['4']] as $invalid) $this->getJson(route('buyer.shop', ['rating' => $invalid]))->assertOk()->assertJsonPath('count', 3);
    }

    public function test_rating_thresholds_match_card_rounding_and_survive_pagination(): void
    {
        $boundary = $this->product('Rounding boundary');
        for ($i = 0; $i < 25; $i++) \App\Models\TransactionReview::create(['product_id' => $boundary->id, 'kind' => 'product', 'rating' => $i === 0 ? 3 : 4]);
        $row = $this->page(['rating' => '4'])[0];
        $this->assertSame(4.0, $row['rating']); $this->assertSame(25, $row['ratings']);
        for ($i = 0; $i < 25; $i++) {
            $product = $this->product('Rating page '.$i);
            \App\Models\TransactionReview::create(['product_id' => $product->id, 'kind' => 'product', 'rating' => 4]);
        }
        $params = ['rating' => '4', 'sort' => 'name', 'categories' => [$this->category->id]];
        $html = $this->get(route('buyer.shop', $params))->assertOk()->getContent();
        $json = $this->getJson(route('buyer.shop', $params))->assertOk()->assertJsonPath('count', 26)->json('html');
        preg_match_all('/<article\b.*?<\/article>/s', $html, $normalCards);
        preg_match_all('/<article\b.*?<\/article>/s', $json, $ajaxCards);
        $this->assertSame($normalCards[0], $ajaxCards[0]);
        $this->assertStringContainsString('rating=4', $json);
        $page = $this->getJson(route('buyer.shop', $params + ['page' => 2]))->assertOk()->assertJsonPath('count', 26)->json('html');
        $this->assertSame(2, preg_match_all('/<article\b/', $page));
        $this->get(route('buyer.shop', ['rating' => 5]))->assertOk()->assertSee('No products found with 5 stars.');
    }

    public function test_each_rating_threshold_and_rating_chip_use_the_existing_filter_pipeline(): void
    {
        $products = [];
        for ($rating = 1; $rating <= 5; $rating++) {
            $products[$rating] = $this->product('Threshold product '.$rating);
            \App\Models\TransactionReview::create(['product_id' => $products[$rating]->id, 'kind' => 'product', 'rating' => $rating]);
        }
        for ($rating = 1; $rating <= 5; $rating++) {
            $expected = array_map(fn ($product) => $product->id, array_slice($products, $rating - 1));
            $actual = array_column($this->page(['rating' => (string) $rating, 'sort' => 'name']), 'id');
            $this->assertSame($expected, $actual);
        }
        $params = ['rating' => 4, 'q' => 'Threshold', 'categories' => [$this->category->id], 'min_price' => 100];
        $html = $this->get(route('buyer.shop', $params))->assertOk()->getContent();
        $document = new \DOMDocument(); @$document->loadHTML($html); $xpath = new \DOMXPath($document);
        $this->assertSame(6, $xpath->query('//input[@data-catalog-rating]')->length);
        $this->assertSame('4', $xpath->query('//input[@data-catalog-rating and @checked]')->item(0)->getAttribute('value'));
        $chip = $xpath->query('//a[@aria-label="Remove 4 stars & up"]')->item(0); $this->assertNotNull($chip);
        parse_str(parse_url($chip->getAttribute('href'), PHP_URL_QUERY), $remaining);
        $this->assertArrayNotHasKey('rating', $remaining); $this->assertSame('Threshold', $remaining['q']);
        $this->assertSame([(string) $this->category->id], $remaining['categories']); $this->assertSame('100', $remaining['min_price']);
        $this->assertSame('4', trim($xpath->query('//span[@data-catalog-filter-count]')->item(0)->textContent));
        $this->assertStringNotContainsString('SALE', $html);
    }

    private function hierarchy(): array
    {
        $child = Category::create(['name' => 'Shirts', 'slug' => 'shirts', 'parent_id' => $this->category->id, 'is_active' => true]);
        $grandchild = Category::create(['name' => 'Cotton Shirts', 'slug' => 'cotton-shirts', 'parent_id' => $child->id, 'is_active' => true]);
        $other = Category::create(['name' => 'Food', 'slug' => 'food', 'is_active' => true]);
        $snacks = Category::create(['name' => 'Snacks', 'slug' => 'snacks', 'parent_id' => $other->id, 'is_active' => true]);
        $rootProduct = $this->product('Root cotton', [10000]);
        $childProduct = $this->product('Shirt cotton', [15000], ['category_id' => $child->id]);
        $grandProduct = $this->product('Nested cotton', [20000], ['category_id' => $grandchild->id]);
        $otherProduct = $this->product('Food cotton', [25000], ['category_id' => $other->id]);
        $snackProduct = $this->product('Snack cotton', [30000], ['category_id' => $snacks->id]);
        return compact('child', 'grandchild', 'other', 'snacks', 'rootProduct', 'childProduct', 'grandProduct', 'otherProduct', 'snackProduct');
    }

    public function test_category_union_includes_descendants_without_duplicate_products(): void
    {
        $h = $this->hierarchy();
        $cases = [
            [[$this->category->id], ['rootProduct', 'childProduct', 'grandProduct']],
            [[$this->category->id, $h['other']->id], ['rootProduct', 'childProduct', 'grandProduct', 'otherProduct', 'snackProduct']],
            [[$h['child']->id], ['childProduct', 'grandProduct']],
            [[$h['child']->id, $h['snacks']->id], ['childProduct', 'grandProduct', 'snackProduct']],
            [[$this->category->id, $h['child']->id, $this->category->id], ['rootProduct', 'childProduct', 'grandProduct']],
            [[$this->category->id, $h['snacks']->id], ['rootProduct', 'childProduct', 'grandProduct', 'snackProduct']],
        ];
        foreach ($cases as [$selected, $keys]) {
            $rows = $this->page(['categories' => $selected]);
            $expected = array_map(fn ($key) => $h[$key]->id, $keys);
            $actual = array_column($rows, 'id'); sort($expected); sort($actual);
            $this->assertSame($expected, $actual);
        }
        $filters = ['categories' => [$this->category->id, $h['other']->id], 'q' => 'cotton', 'seller' => $this->seller->slug,
            'in_stock' => '1', 'min_price' => '150', 'max_price' => '250', 'sort' => 'price_desc'];
        $h['childProduct']->variants()->update(['stock' => 0]);
        $this->assertSame([$h['otherProduct']->id, $h['grandProduct']->id], array_column($this->page($filters), 'id'));
        $filters['categories'] = [999999];
        $this->assertSame([], $this->page($filters));
    }

    public function test_sidebar_uses_all_actual_active_nested_categories_including_others(): void
    {
        $h = $this->hierarchy();
        $others = Category::create(['name' => '  oThErS  ', 'slug' => 'others', 'is_active' => true]);
        $longer = Category::create(['name' => 'Others and more', 'slug' => 'others-and-more', 'is_active' => true]);
        $inactive = Category::create(['name' => 'Inactive', 'slug' => 'inactive', 'is_active' => false]);
        $otherProduct = $this->product('Others item', changes: ['category_id' => $others->id]);
        $before = Category::orderBy('id')->get()->toArray();
        $html = $this->actingAs($this->buyer)->get(route('buyer.shop'))->assertOk()->getContent();
        $document = new \DOMDocument(); @$document->loadHTML($html); $xpath = new \DOMXPath($document);
        $list = $xpath->query('//div[@aria-label="Filter by category"]')->item(0);
        $this->assertSame(4, $xpath->query('./div[@data-catalog-node]', $list)->length);
        $this->assertSame(0, $xpath->query('.//details[@open]', $list)->length);
        $this->assertSame(1, $xpath->query('.//details[@data-catalog-branch="'.$this->category->id.'"]//input[@value="'.$h['grandchild']->id.'"]', $list)->length);
        $this->assertSame(0, $xpath->query('.//details[@data-catalog-branch="'.$h['grandchild']->id.'"]', $list)->length);
        $this->assertSame(1, $xpath->query('//input[@data-catalog-category-input and @value="'.$others->id.'"]')->length);
        $this->assertSame(0, $xpath->query('//input[@data-catalog-category-input and @value="'.$inactive->id.'"]')->length);
        $this->assertSame(1, $xpath->query('//input[@data-catalog-category-input and @value="'.$longer->id.'"]')->length);
        $this->assertSame(0, $xpath->query('//a[@data-catalog-category="'.$h['child']->slug.'"]')->length);
        $this->assertStringContainsString('catalog-category-list', $list->getAttribute('class'));
        $this->assertStringNotContainsString('overflow-y', $list->getAttribute('style'));
        $selected = $this->get(route('buyer.shop', ['categories' => [$h['grandchild']->id]]))->assertOk()->getContent();
        @$document->loadHTML($selected); $xpath = new \DOMXPath($document);
        $this->assertSame(2, $xpath->query('//details[@data-catalog-branch and @open]')->length);
        $this->assertSame(1, $xpath->query('//input[@data-catalog-category-input and @checked and @value="'.$h['grandchild']->id.'"]')->length);
        $this->assertContains($otherProduct->id, array_column($this->page(), 'id'));
        $this->assertSame($before, Category::orderBy('id')->get()->toArray());
    }

    public function test_others_and_miscellaneous_use_the_existing_tree_and_public_visibility_rules(): void
    {
        $others = Category::create(['name' => 'Others', 'slug' => 'others', 'is_active' => true]);
        $misc = Category::forceCreate(['id' => 100, 'name' => 'Miscellaneous', 'slug' => 'miscellaneous', 'parent_id' => $others->id, 'is_active' => true]);
        $this->seller->update(['name' => 'Lahat Meron']);
        $approved = $this->product('Demo approved', [15000], ['category_id' => $misc->id]);
        foreach (['PENDING', 'REJECTED', 'HIDDEN'] as $status) {
            $this->product('Demo '.$status, changes: ['category_id' => $misc->id, 'moderation_status' => $status]);
        }
        $this->product('Demo inactive', changes: ['category_id' => $misc->id, 'is_active' => false]);
        $this->product('Demo archived', changes: ['category_id' => $misc->id])->delete();
        $ordinary = $this->product('Ordinary clothing');
        $before = Category::orderBy('id')->get()->toArray();

        $html = $this->get(route('buyer.shop', ['categories' => [$misc->id]]))->assertOk()->getContent();
        $document = new \DOMDocument(); @$document->loadHTML($html); $xpath = new \DOMXPath($document);
        $list = $xpath->query('//div[@aria-label="Filter by category"]')->item(0);
        $parent = $xpath->query('.//input[@data-catalog-category-input and @value="'.$others->id.'"]', $list);
        $this->assertSame(1, $parent->length);
        $this->assertSame('Others', $parent->item(0)->getAttribute('data-category-name'));
        $this->assertSame('others', $parent->item(0)->getAttribute('data-category-slug'));
        $child = $xpath->query('.//details[@data-catalog-branch="'.$others->id.'" and @open]//input[@value="'.$misc->id.'" and @checked]', $list);
        $this->assertSame(1, $child->length);
        $this->assertSame('Miscellaneous', $child->item(0)->getAttribute('data-category-name'));
        $this->assertSame((string) $others->id, $child->item(0)->getAttribute('data-category-parent'));
        $this->assertSame(1, $xpath->query('.//input[@value="100"]', $list)->length);
        foreach ([['categories' => [$others->id]], ['categories' => [$misc->id]], ['category' => $others->slug], ['category' => $misc->slug]] as $selection) {
            $this->assertSame([$approved->id], array_column($this->page($selection), 'id'));
            $response = $this->getJson(route('buyer.shop', $selection))->assertOk()->assertJsonPath('count', 1);
            $response->assertSee($approved->name)->assertDontSee($ordinary->name);
            foreach (['PENDING', 'REJECTED', 'HIDDEN', 'inactive', 'archived'] as $hidden) $response->assertDontSee('Demo '.$hidden);
        }
        $this->assertSame([$ordinary->id], array_column($this->page(['categories' => [$this->category->id]]), 'id'));
        $this->getJson(route('buyer.shop', ['categories' => [$others->id, $this->category->id], 'q' => 'Demo', 'sort' => 'price_desc', 'min_price' => 100, 'max_price' => 200, 'page' => 1]))
            ->assertOk()->assertJsonPath('count', 1)->assertSee($approved->name);
        $this->getJson(route('buyer.shop'))->assertOk()->assertJsonPath('count', 2);
        $this->assertSame($before, Category::orderBy('id')->get()->toArray());
    }

    public function test_multiple_categories_are_validated_and_preserved_in_filtered_pagination_and_ajax_cards(): void
    {
        $other = Category::create(['name' => 'Food', 'slug' => 'food', 'is_active' => true]);
        $inactive = Category::create(['name' => 'Unavailable', 'slug' => 'unavailable', 'is_active' => false]);
        foreach ([[999999], ['invalid'], [$inactive->id]] as $ids) {
            $this->getJson(route('buyer.shop', ['categories' => $ids]))->assertUnprocessable()->assertJsonValidationErrors('categories.0');
        }
        $this->getJson(route('buyer.shop', ['categories' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('categories');
        for ($i = 0; $i < 27; $i++) $last = $this->product('Union item '.$i, changes: ['category_id' => $i % 2 ? $this->category->id : $other->id]);
        WishlistItem::create(['user_id' => $this->buyer->id, 'product_slug' => $last->slug, 'product_name' => $last->name, 'price' => 100]);
        $params = ['categories' => [$this->category->id, $other->id], 'q' => 'Union', 'seller' => $this->seller->slug, 'in_stock' => '1', 'sort' => 'price_asc'];
        $normal = $this->actingAs($this->buyer)->get(route('buyer.shop', $params))->assertOk()->getContent();
        $ajax = $this->getJson(route('buyer.shop', $params))->assertOk()->assertJsonPath('count', 27)->json('html');
        preg_match_all('/<article\b.*?<\/article>/s', $normal, $normalCards);
        preg_match_all('/<article\b.*?<\/article>/s', $ajax, $ajaxCards);
        $this->assertCount(24, $normalCards[0]); $this->assertSame($normalCards[0], $ajaxCards[0]);
        $this->assertStringContainsString('aria-pressed="true"', $ajax);
        $document = new \DOMDocument(); @$document->loadHTML($ajax); $xpath = new \DOMXPath($document);
        $next = $xpath->query('//a[@rel="next"]')->item(0); $this->assertNotNull($next);
        parse_str(parse_url($next->getAttribute('href'), PHP_URL_QUERY), $query);
        $this->assertSame(array_map('strval', $params['categories']), $query['categories']);
        $this->assertSame('2', $query['page']);
        $second = $this->getJson(route('buyer.shop', $params + ['page' => 2]))->assertOk()->assertJsonPath('count', 27)->json('html');
        $this->assertSame(3, preg_match_all('/<article\b/', $second));
        $this->getJson(route('buyer.shop'))->assertOk()->assertJsonPath('count', 27);
    }

    public function test_navbar_and_shop_use_q_and_search_is_case_insensitive_with_an_empty_state(): void
    {
        $shirt = $this->product('Blue Shirt'); $this->product('Red Jacket');
        $response = $this->actingAs($this->buyer)->get(route('buyer.shop', ['q' => 'blue']))->assertOk()
            ->assertSee('Blue Shirt')->assertDontSee('Red Jacket')->assertSee('data-catalog-navbar-search', false)
            ->assertSee('value="blue"', false)->assertSee('placeholder="Search products"', false)->assertDontSee('Search products and stores');
        $this->assertSame(1, substr_count($response->getContent(), 'id="catalogFilters"'));
        $this->assertStringNotContainsString('name="search"', $response->getContent());
        $response->assertSee('Browse Categories')->assertSee('Apply Price')->assertSee('lg:grid-cols-[280px_1fr]', false)->assertDontSee('SALE');
        $response->assertSee('Customer Rating')->assertDontSee('Rating filtering is not available yet.')->assertSee('data-catalog-rating', false)->assertSee('data-catalog-apply-price', false)->assertSee('data-catalog-mobile-filters', false);
        $this->get(route('buyer.shop', ['q' => 'no-such-product']))->assertOk()->assertSee('No products match these filters.')->assertSee('Clear filters');
        $this->getJson(route('buyer.shop', ['q' => 'cotton']))->assertJsonPath('count', 2);
    }

    public function test_category_tree_store_and_availability_filters_are_connected(): void
    {
        $child = Category::create(['name' => 'Shirts', 'slug' => 'shirts', 'parent_id' => $this->category->id, 'is_active' => true]);
        $shirt = $this->product('Child Shirt', changes: ['category_id' => $child->id]);
        $otherCategory = Category::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);
        $this->product('Different category', changes: ['category_id' => $otherCategory->id]);
        $out = $this->product('Sold out'); $out->variants()->update(['stock' => 0]);
        $rows = $this->page(['category' => 'clothing', 'in_stock' => '1', 'seller' => 'catalog-shop']);
        $this->assertSame([$shirt->id], array_column($rows, 'id'));
        $this->assertSame(3, count($this->page()));
        $this->assertSame([], $this->page(['category' => 'unknown']));
        $this->assertSame([], $this->page(['seller' => 'another-store']));
        $this->get(route('buyer.shop', ['category' => 'shirts', 'in_stock' => '1']))->assertOk()->assertSee('1 category selected')->assertSee('name="in_stock" value="1" checked', false);
    }

    public function test_many_real_categories_stay_inside_a_bounded_list_and_remain_selectable(): void
    {
        for ($i = 0; $i < 45; $i++) {
            $category = Category::create(['name' => 'Long category name '.$i, 'slug' => 'category-'.$i, 'is_active' => true]);
        }
        $product = $this->product('Last category product', changes: ['category_id' => $category->id]);
        $response = $this->actingAs($this->buyer)->get(route('buyer.shop'))->assertOk();
        $response->assertSee('lg:grid-cols-[280px_1fr] items-start', false)
            ->assertSee('catalog-category-list', false)->assertSee('data-catalog-show-more', false)->assertSee('Search categories')
            ->assertSee('min-w-0 break-words', false)->assertSee('Price Range')->assertSee('Customer Rating');
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $list = $xpath->query('//aside[@id="catalogSidebar"]//div[@aria-label="Filter by category"]')->item(0);
        $this->assertNotNull($list);
        $this->assertSame('0', $list->getAttribute('tabindex'));
        $this->assertSame(46, $xpath->query('.//input[@data-catalog-category-input]', $list)->length);
        $this->assertStringContainsString($category->name, $list->textContent);
        $this->assertStringNotContainsString('Price Range', $list->textContent);
        $this->assertStringNotContainsString('Customer Rating', $list->textContent);
        $this->getJson(route('buyer.shop', ['category' => $category->slug]))->assertOk()->assertJsonPath('count', 1)
            ->assertSee($product->name)->assertDontSee('SALE');
    }

    public function test_regular_prices_have_no_sale_badges_and_initial_ajax_cards_are_identical(): void
    {
        $single = $this->product('Regular price product', [12000]);
        $multiple = $this->product('Regular variant product', [10000, 20000]);
        foreach ($this->page() as $product) {
            $this->assertNull($product['old_price']);
            $this->assertNull($product['discount']);
        }
        $params = ['sort' => 'price_asc'];
        $html = $this->actingAs($this->buyer)->get(route('buyer.shop', $params))->assertOk()->assertDontSee('SALE')->getContent();
        $ajax = $this->getJson(route('buyer.shop', $params))->assertOk()->assertJsonPath('count', 2)->json('html');
        $this->assertStringNotContainsString('SALE', $ajax);
        preg_match_all('/<article\b.*?<\/article>/s', $html, $initialCards);
        preg_match_all('/<article\b.*?<\/article>/s', $ajax, $ajaxCards);
        $this->assertCount(2, $initialCards[0]);
        $this->assertSame($initialCards[0], $ajaxCards[0]);
        foreach ([$single, $multiple] as $product) {
            $this->assertStringContainsString(route('buyer.wishlist.toggle', $product->slug), $ajax);
            $this->assertStringContainsString(route('buyer.product', $product->slug), $ajax);
        }
        $this->assertStringContainsString('100.00', $ajax);
        $this->assertStringContainsString('120.00', $ajax);
        $this->assertStringContainsString('From ', $ajax);
    }

    public function test_entire_price_range_and_stock_must_match_the_same_variant(): void
    {
        $gap = $this->product('Disjoint prices', [10000, 100000]);
        $matching = $this->product('Matching option', [90000, 55000]);
        $rows = $this->page(['min_price' => '500', 'max_price' => '600']);
        $this->assertSame([$matching->id], array_column($rows, 'id'));
        $this->assertSame(550, $rows[0]['price']);
        $this->assertSame($matching->variants()->orderByDesc('id')->first()->id, $rows[0]['variant_id']);
        $matching->variants()->where('price_minor', 55000)->update(['stock' => 0]);
        $this->assertSame([], $this->page(['min_price' => '500', 'max_price' => '600', 'in_stock' => '1']));
        $this->assertCount(1, $this->page(['min_price' => '500', 'max_price' => '600']));
    }

    public function test_price_sorts_use_the_displayed_lowest_matching_price_and_ignore_deleted_or_inactive_variants(): void
    {
        $a = $this->product('First', [90000, 10000, 10, 1]);
        $a->variants()->where('price_minor', 10)->update(['is_active' => false]);
        $a->variants()->where('price_minor', 1)->delete();
        $b = $this->product('Second', [30000]);
        $this->assertSame([$a->id, $b->id], array_column($this->page(['sort' => 'price_asc']), 'id'));
        $this->assertSame([100, 300], array_column($this->page(['sort' => 'price_asc']), 'price'));
        $this->assertSame([$b->id, $a->id], array_column($this->page(['sort' => 'price_desc']), 'id'));
        $this->assertSame([], $this->page(['max_price' => '1']));
        $this->getJson(route('buyer.shop', ['max_price' => '100']))->assertOk()->assertJsonPath('count', 1);
        // Product Details/default cart variant selection remain unchanged.
        $detail = app(ProductCatalogService::class)->marketplaceProduct($a->slug);
        $this->assertSame(900, $detail['price']);
        $this->assertSame($a->variants()->oldest('id')->first()->id, $detail['variant_id']);
    }

    public function test_visibility_rules_remain_in_force(): void
    {
        $public = $this->product('Visible');
        $this->product('Pending', changes: ['moderation_status' => 'PENDING']);
        $this->product('Inactive', changes: ['is_active' => false]);
        $deleted = $this->product('Deleted'); $deleted->delete();
        $noVariants = $this->product('No active options'); $noVariants->variants()->update(['is_active' => false]);
        $this->assertSame([$public->id], array_column($this->page(), 'id'));
        $this->seller->owner->update(['is_suspended' => true]);
        $this->assertSame([], $this->page());
    }

    public function test_best_selling_uses_existing_sales_counts_and_stable_ordering_for_unsold_products(): void
    {
        $sold = $this->product('Has sales'); $unsold = $this->product('No sales');
        $order = \App\Models\Order::create(['order_number' => 'CAT-SALE', 'buyer_id' => $this->buyer->id, 'status' => 'PLACED']);
        $parcel = \App\Models\SellerOrder::create(['order_id' => $order->id, 'seller_id' => $this->seller->id, 'workflow_status' => 'PLACED', 'status' => 'pending']);
        \App\Models\OrderItem::create(['order_id' => $order->id, 'seller_order_id' => $parcel->id, 'product_id' => $sold->id, 'quantity' => 3]);
        $this->assertSame([$sold->id, $unsold->id], array_column($this->page(['sort' => 'best_selling']), 'id'));
        $this->assertSame([3, 0], array_column($this->page(['sort' => 'best_selling']), 'sold'));
        $order->update(['status' => 'CANCELLED']);
        $this->assertSame([$unsold->id, $sold->id], array_column($this->page(['sort' => 'best_selling']), 'id'));
    }

    public function test_initial_and_ajax_results_preserve_owned_wishlist_forms_pagination_and_product_links(): void
    {
        for ($i = 0; $i < 27; $i++) $last = $this->product('Paged Shirt '.$i);
        WishlistItem::create(['user_id' => $this->buyer->id, 'product_slug' => $last->slug, 'product_name' => $last->name, 'price' => 100]);
        $params = ['q' => 'Paged', 'category' => 'clothing', 'sort' => 'price_desc', 'in_stock' => '1'];
        $this->actingAs($this->buyer)->get(route('buyer.shop', $params))->assertOk()->assertSee('data-catalog-pagination', false)->assertSee('aria-pressed="true"', false);
        $json = $this->getJson(route('buyer.shop', $params))->assertOk()->assertJsonPath('count', 27)->assertHeader('Cache-Control', 'no-store, private');
        $html = $json->json('html');
        $this->assertStringContainsString(route('buyer.wishlist.toggle', $last->slug), $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('aria-pressed="true"', $html);
        $this->assertStringContainsString(route('buyer.product', $last->slug), $html);
        $this->assertStringContainsString('q=Paged', $html); $this->assertStringContainsString('category=clothing', $html);
        $this->assertStringContainsString('page=2', $html);
        $this->getJson(route('buyer.shop', $params + ['page' => 2]))->assertOk()->assertJsonPath('count', 27)
            ->assertJson(fn ($json) => $json->where('html', fn ($value) => preg_match_all('/<article\s/', $value) === 3)->etc());
        $this->post(route('buyer.wishlist.toggle', $last->slug))->assertRedirect();
        $this->assertDatabaseMissing('wishlist_items', ['user_id' => $this->buyer->id, 'product_slug' => $last->slug]);
    }

    public function test_catalog_to_product_add_to_cart_and_buy_now_navigation_stays_connected(): void
    {
        $product = $this->product('Cart Product', [15000, 20000]);
        $variant = $product->variants()->orderByDesc('id')->first();
        $this->actingAs($this->buyer)->get(route('buyer.product', $product->slug))->assertOk()
            ->assertSee(route('buyer.cart.add', $product->slug), false)->assertSee(route('buyer.buy-now', $product->slug), false);
        $this->post(route('buyer.cart.add', $product->slug), ['variant_id' => $variant->id, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cart_items', ['user_id' => $this->buyer->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);
        $this->get(route('buyer.cart'))->assertOk()->assertSee('Cart Product')->assertSee('200.00');
    }

    public function test_invalid_ranges_return_actionable_json_errors_and_guest_cards_link_to_sign_in(): void
    {
        $product = $this->product('Public Product');
        $this->getJson(route('buyer.shop', ['min_price' => '200', 'max_price' => '100']))->assertUnprocessable()->assertJsonValidationErrors('max_price');
        $this->getJson(route('buyer.shop', ['page' => 0]))->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson(route('buyer.shop'))->assertOk()->assertJson(fn ($json) => $json->where('html', fn ($html) => str_contains($html, route('login')) && !str_contains($html, route('buyer.wishlist.toggle', $product->slug)))->etc());
    }
}
