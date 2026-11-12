<?php

namespace App\Support;

use App\Models\{Category, Product, ProductVariant, Seller, User};
use App\Services\{SellerCategoryService, SellerInventoryService};
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Shared rules for the fixed importer and separately authorized Admin batch. */
class DemoProductBatch
{
    public function target(SellerCategoryService $categories, bool $lock = false, ?string $expectedOwner = null): array
    {
        $seller = Seller::with(['owner', 'category'])->find(3);
        if ($lock && $seller) {
            // Match account administration's owner-before-store lock order.
            $ownerId = $seller->user_id;
            $owner = User::whereKey($ownerId)->lockForUpdate()->first();
            $seller = Seller::whereKey(3)->lockForUpdate()->first();
            $this->require($seller && (int) $seller->user_id === (int) $ownerId, 'Seller owner changed during preflight.');
            $seller->setRelation('owner', $owner);
            $seller->setRelation('category', Category::whereKey($seller->category_id)->lockForUpdate()->first());
        }
        $this->require($seller && $seller->name === 'Lahat Meron' && $seller->status === 'approved', 'Seller 3 is missing, renamed, or not approved.');
        $owner = $seller->owner;
        $this->require($owner && $owner->hasRole('seller') && $owner->status === 'active' && ! $owner->is_suspended && $owner->hasVerifiedContacts(), 'The related Seller owner is missing, unverified, inactive, suspended, or lacks the Seller role.');
        if ($expectedOwner !== null) {
            $id = (string) $expectedOwner;
            $this->require(ctype_digit($id) && (int) $id === (int) $owner->id, 'Expected owner does not match sellers.user_id.');
        }
        $root = $seller->category;
        $this->require($root && $root->name === 'Others' && $root->parent_id === null && $root->is_active, 'Seller must have the active Others root assigned.');
        $matches = Category::query()->where('parent_id', $root->id)->where('name', 'Miscellaneous')->where('is_active', true)
            ->when($lock, fn ($q) => $q->lockForUpdate())->get();
        $this->require($matches->count() === 1, 'Exactly one active Miscellaneous child of Others is required.');
        $category = $matches->sole();
        $this->require($categories->allows($seller, $category->id), 'Miscellaneous is not allowed by SellerCategoryService.');
        return [$seller, $category];
    }

    public function dataset(int $categoryId): array
    {
        $items = json_decode(file_get_contents(database_path('demo-products/lahat-meron-v1.json')), true, flags: JSON_THROW_ON_ERROR);
        Validator::make(['items' => $items], [
            'items' => 'required|array|size:60',
            'items.*' => 'array:key,name,description,group,option_groups,variants',
            'items.*.key' => ['required', 'distinct', 'regex:/^lm[0-9]{3}$/'],
            'items.*.name' => 'required|string|max:140|distinct',
            'items.*.description' => 'required|string|max:2800',
            'items.*.group' => 'required|string|max:50',
            'items.*.option_groups' => 'present|array|max:3',
            'items.*.option_groups.*' => 'array:name,values',
            'items.*.option_groups.*.name' => 'required|string|max:40',
            'items.*.option_groups.*.values' => 'required|array|min:2|max:4',
            'items.*.option_groups.*.values.*' => ['required', 'string', 'max:100', 'not_regex:/[,\pC]/u'],
            'items.*.variants' => 'required|array|min:1|max:4',
            'items.*.variants.*' => 'array:price_minor,stock,options',
            'items.*.variants.*.price_minor' => 'required|integer|min:0|max:2147483647',
            'items.*.variants.*.stock' => 'required|integer|min:0|max:'.SellerInventoryService::MAX_STOCK,
            'items.*.variants.*.options' => 'present|array|max:3',
            'items.*.variants.*.options.*' => 'required|string|max:100',
        ])->validate();
        $distribution = array_count_values(array_map(fn ($item) => count($item['variants']), $items));
        ksort($distribution);
        $this->require($distribution === [1 => 36, 2 => 12, 3 => 6, 4 => 6], 'Dataset must contain exactly 36/12/6/6 products with 1/2/3/4 variants.');
        $rows = [];
        foreach ($items as $item) {
            $prices = $stocks = $variants = $skus = [];
            foreach ($item['variants'] as $i => $variant) {
                $this->require(is_int($variant['price_minor']) && is_int($variant['stock']), 'Price and stock must be JSON integers.');
                $skus[] = $sku = 'DEMO-LM-'.strtoupper($item['key']).'-'.($i + 1);
                $prices[] = $minor = $variant['price_minor'];
                $stocks[] = $variant['stock'];
                $variants[] = ['sku' => $sku, 'price' => intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT), 'stock' => $variant['stock'], 'options' => ProductOptions::options($variant['options'])];
            }
            $simple = count($variants) === 1;
            $this->require(! $simple || ($item['option_groups'] === [] && $variants[0]['options'] === []), 'Simple products must have no option choices.');
            $data = [
                'name' => 'Demo '.$item['name'], 'description' => '[SUKI-DEMO:'.$item['key'].'] '.$item['description'].' Sample catalog entry for demonstrations.',
                'category_id' => $categoryId, 'status' => 'active', 'shipping_options' => [],
                'product_mode' => $simple ? 'simple' : 'variants',
                'option_groups' => array_map(fn ($g) => ['name' => $g['name'], 'values' => implode(',', $g['values'])], $item['option_groups']),
                'price' => $variants[0]['price'], 'stock' => $variants[0]['stock'], 'sku' => $variants[0]['sku'],
                'variants' => $simple ? [] : $variants,
            ];
            $data = ProductOptions::creation($data);
            $rows[] = ['key' => $item['key'], 'reference' => 'suki-demo:'.$item['key'], 'data' => $data, 'skus' => $skus, 'prices' => $prices, 'stocks' => $stocks];
        }
        return $rows;
    }

    public function preflight(Seller $seller, array $rows): array
    {
        $plan = [];
        foreach ($rows as $row) {
            $existing = $seller->products()->withTrashed()->where('legacy_reference', $row['reference'])->first();
            $skuRows = ProductVariant::withTrashed()->whereIn('sku', $row['skus'])->get();
            if ($existing) {
                $actualSkus = $skuRows->pluck('sku')->sort()->values()->all();
                $expectedSkus = $row['skus']; sort($expectedSkus);
                $this->require(str_starts_with((string) $existing->description, '[SUKI-DEMO:'.$row['key'].'] ')
                    && $actualSkus === $expectedSkus && $skuRows->every(fn ($v) => (int) $v->product_id === (int) $existing->id), 'Existing reference conflicts with demo provenance; no records will be overwritten.');
                $plan[$row['key']] = $existing->trashed() ? 'SKIP archived demo' : 'SKIP existing demo';
            } else {
                $this->require($skuRows->isEmpty(), 'A demo SKU is already assigned to another/non-demo record.');
                $slug = \Illuminate\Support\Str::slug($row['data']['name']);
                $this->require(! Product::withTrashed()->where('slug', $slug)->exists(), 'A demo product slug collides with an existing record.');
                $plan[$row['key']] = 'INSERT';
            }
        }
        return $plan;
    }

    public function importFingerprint(Seller $seller, Category $category, array $plan, ?array $databaseIdentity = null): string
    {
        return hash('sha256',json_encode([$databaseIdentity,$seller->id,$seller->user_id,$category->id,$plan,
            hash_file('sha256',database_path('demo-products/lahat-meron-v1.json')),
            hash_file('sha256',database_path('demo-products/images/manifest.json'))],JSON_THROW_ON_ERROR));
    }

    public function approvalPlan(SellerCategoryService $categories, bool $lock = false): array
    {
        [$seller, $category] = $this->target($categories, $lock);
        $rows = $this->dataset($category->id);
        app(DemoProductImages::class)->published(array_column($rows, 'key'));
        $this->preflight($seller, $rows);
        $products = $seller->products()->withTrashed()->whereIn('legacy_reference', array_column($rows, 'reference'))
            ->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())
            ->with(['variants' => fn ($q) => $q->withTrashed()->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate()),
                'images' => fn ($q) => $q->orderBy('id')->when($lock, fn ($q) => $q->lockForUpdate())])
            ->get()->keyBy('legacy_reference');
        $this->require($products->count() === 60, 'The complete 60-product demo batch is required before bulk approval.');
        $pending = $approved = 0; $identity = [];
        foreach ($rows as $row) {
            $product = $products->get($row['reference']);
            $state = $product?->moderation_status?->value;
            $this->require($product && !$product->trashed() && in_array($state, ['PENDING','APPROVED'], true), 'Archived, rejected, hidden or missing demo products cannot be bulk approved.');
            $this->require($product->name === $row['data']['name'] && $product->description === $row['data']['description']
                && $product->brand === null && $product->video_path === null && ($product->shipping_options ?? []) === []
                && (int)$product->category_id === (int)$category->id && $product->is_active && $product->variants->count() === count($row['skus']), 'A demo product was modified or has unexpected variants; review it individually.');
            $expectedOptions = [array_combine($row['data']['variation_name'], $row['data']['variation_value'])];
            $expectedNames = [$row['data']['primary_variant_name'] ?? 'Default'];
            foreach ($row['data']['variants'] as $variant) { $expectedOptions[] = $variant['options']; $expectedNames[] = $variant['name']; }
            foreach ($row['skus'] as $i => $sku) {
                $variant = $product->variants->firstWhere('sku', $sku);
                $actualOptions = $variant?->options ?? []; $options = $expectedOptions[$i]; ksort($actualOptions); ksort($options);
                $this->require($variant && !$variant->trashed() && $variant->is_active && $variant->name === $expectedNames[$i]
                    && $actualOptions === $options && $variant->price_minor === $row['prices'][$i]
                    && $variant->image_path === null && $variant->weight_grams === null && $variant->length_mm === null && $variant->width_mm === null && $variant->height_mm === null
                    && ($state === 'APPROVED' || $variant->stock === $row['stocks'][$i]), 'A demo variant conflicts with the dataset; review it individually.');
            }
            $this->require($product->images->count() === 1 && $product->images->first()->path === DemoProductImages::path($row['key'])
                && $product->images->first()->position === 1, 'A demo image conflicts with the published batch.');
            $state === 'PENDING' ? $pending++ : $approved++;
            $identity[] = [$row['key'], $product->id, $product->images->first()->path];
        }
        // Deliberately exclude approval timestamps/status so a repeated confirmation
        // safely skips products approved by the first request, without new side effects.
        $hash = hash('sha256', json_encode([$seller->user_id, $category->id, $identity,
            hash_file('sha256', database_path('demo-products/lahat-meron-v1.json')),
            hash_file('sha256', database_path('demo-products/images/manifest.json'))], JSON_THROW_ON_ERROR));
        return compact('seller','category','products','pending','approved','hash');
    }

    private function require(mixed $condition, string $message): void
    {
        if (! $condition) throw ValidationException::withMessages(['demo_batch' => $message]);
    }
}
