<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Services\ProductCatalogService;
use App\Services\SellerCategoryService;
use App\Services\SellerInventoryService;
use App\Support\ProductOptions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/** Deliberately limited to the approved store and local/disposable SQLite. */
class ImportDemoProducts extends Command
{
    protected $signature = 'demo:products:import
        {--seller-id= : Must explicitly be sellers.id 3}
        {--expect-store= : Must exactly be Lahat Meron}
        {--connection= : Explicit configured SQLite connection}
        {--expect-environment= : Exact local or testing environment}
        {--confirm-database= : Exact existing SQLite real path, or :memory: in testing}
        {--expect-owner-id= : Owner user ID from the reviewed preview; required for execution}
        {--execute : Write the reviewed batch; otherwise preview only}';

    protected $description = 'Preview or import the fixed Lahat Meron demo pack; SQLite local/testing only';

    public function handle(ProductCatalogService $catalog, SellerCategoryService $categories): int
    {
        $previous = DB::getDefaultConnection();
        try {
            $connection = $this->safeConnection(); // No query may precede these checks.
            DB::setDefaultConnection($connection);
            [$seller, $category] = $this->target($categories);
            $rows = $this->dataset($category->id);
            $plan = $this->preflight($seller, $rows);
            $this->info('Target: sellers.id=3; Lahat Meron; owner users.id='.$seller->user_id);
            $this->line('Database: '.$connection.'; '.$this->option('confirm-database').'; environment='.app()->environment());
            $this->line('Category: Others > Miscellaneous; category_id='.$category->id);
            $this->line('Pack: 60 products / 102 variants; 36 simple, 12 two-option, 6 three-option, 6 four-option.');
            $this->table(['Key', 'Action', 'Product', 'Variants', 'Price minor range', 'Stock total'], array_map(fn ($row) => [
                $row['key'], $plan[$row['key']], $row['data']['name'], count($row['skus']),
                min($row['prices']).'..'.max($row['prices']), array_sum($row['stocks']),
            ], $rows));
            $this->line('New products remain PENDING. Normal moderation events/Admin notification intents are preserved. No media is imported.');
            if (! $this->option('execute')) {
                $this->info('DRY RUN: no records created or changed. Proposed inserts: '.count(array_filter($plan, fn ($action) => $action === 'INSERT')).'; existing demo items skipped: '.count(array_filter($plan, fn ($action) => $action !== 'INSERT')).'.');
                return self::SUCCESS;
            }
            $ownerId = (string) $this->option('expect-owner-id');
            $this->require(ctype_digit($ownerId) && (int) $ownerId === (int) $seller->user_id, 'Execution requires --expect-owner-id matching the reviewed owner.');
            $created = 0;
            $createdVariants = 0;
            $skipped = 0;
            DB::transaction(function () use ($categories, $catalog, $rows, &$created, &$createdVariants, &$skipped) {
                // Recheck inside the batch transaction. SQLite writer contention fails
                // safely; unique legacy-reference/SKU constraints remain authoritative.
                [$seller, $category] = $this->target($categories, true);
                $this->require((int) $this->option('expect-owner-id') === (int) $seller->user_id, 'Owner changed since preview.');
                $plan = $this->preflight($seller, $rows);
                foreach ($rows as $row) {
                    if ($plan[$row['key']] !== 'INSERT') { $skipped++; continue; }
                    $this->require($row['data']['category_id'] === $category->id, 'Category changed since preview.');
                    $product = $catalog->create($seller, $row['data']);
                    $product->update(['legacy_reference' => $row['reference']]);
                    $this->require($product->variants->sortBy('id')->pluck('price_minor')->values()->all() === $row['prices'], 'Price conversion failed; batch rolled back.');
                    $created++;
                    $createdVariants += $product->variants->count();
                    $this->line('Prepared '.$created.': '.$row['key'].' (not committed yet)');
                }
            });
            $this->info("COMMITTED: {$created} products inserted; {$skipped} existing demo products skipped. No existing products updated or deleted.");
            $this->info("Variant records inserted: {$createdVariants}.");
            return self::SUCCESS;
        } catch (ValidationException) {
            $this->error('Demo dataset validation failed. No batch changes committed.');
        } catch (RuntimeException $error) {
            // Only our fixed guard messages are intended for the operator.
            $this->error($error instanceof DemoImportGuard ? $error->getMessage() : 'Import failed. Inspect the isolated/application diagnostics before retrying.');
        } catch (Throwable) {
            $this->error('Import failed. No automatic retry or data repair was attempted.');
        } finally {
            DB::setDefaultConnection($previous);
        }
        return self::FAILURE;
    }

    private function safeConnection(): string
    {
        $this->require((string) $this->option('seller-id') === '3' && $this->option('expect-store') === 'Lahat Meron', 'Explicit target must be --seller-id=3 --expect-store="Lahat Meron".');
        $environment = app()->environment();
        $this->require(in_array($environment, ['local', 'testing'], true) && $this->option('expect-environment') === $environment, 'Explicit local/testing environment confirmation is required.');
        $name = (string) $this->option('connection');
        $config = config('database.connections.'.$name);
        $this->require($name !== '' && is_array($config) && ($config['driver'] ?? null) === 'sqlite', 'Only an explicit SQLite connection is supported. PostgreSQL/Supabase and other drivers are refused.');
        $this->require(empty($config['url']) && empty($config['read']) && empty($config['write']), 'Connection URLs and split read/write connections are refused.');
        $database = $config['database'] ?? null;
        $this->require(is_string($database) && $database !== '', 'SQLite database must be explicitly configured.');
        if ($database === ':memory:') {
            $this->require($environment === 'testing' && $this->option('confirm-database') === ':memory:', 'In-memory SQLite is allowed only in testing with explicit confirmation.');
        } else {
            $path = realpath($database);
            $this->require($path !== false && is_file($path) && $this->option('confirm-database') === $path, 'Confirm the exact real path of an existing SQLite database; new databases and URI paths are refused.');
        }
        return $name;
    }

    private function target(SellerCategoryService $categories, bool $lock = false): array
    {
        $seller = Seller::query()->when($lock, fn ($q) => $q->lockForUpdate())->with(['owner', 'category'])->find(3);
        $this->require($seller && $seller->name === 'Lahat Meron' && $seller->status === 'approved', 'Seller 3 is missing, renamed, or not approved.');
        $owner = $seller->owner;
        $this->require($owner && $owner->hasRole('seller') && $owner->status === 'active' && ! $owner->is_suspended && $owner->hasVerifiedContacts(), 'The related Seller owner is missing, unverified, inactive, suspended, or lacks the Seller role.');
        if ($this->option('expect-owner-id') !== null) {
            $id = (string) $this->option('expect-owner-id');
            $this->require(ctype_digit($id) && (int) $id === (int) $owner->id, 'Expected owner does not match sellers.user_id.');
        }
        $root = $seller->category;
        $this->require($root && $root->name === 'Others' && $root->parent_id === null && $root->is_active, 'Seller must have the active Others root assigned.');
        $matches = Category::query()->where('parent_id', $root->id)->where('name', 'Miscellaneous')->where('is_active', true)->get();
        $this->require($matches->count() === 1, 'Exactly one active Miscellaneous child of Others is required.');
        $category = $matches->sole();
        $this->require($categories->allows($seller, $category->id), 'Miscellaneous is not allowed by SellerCategoryService.');
        return [$seller, $category];
    }

    private function dataset(int $categoryId): array
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

    private function preflight(Seller $seller, array $rows): array
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
                $plan[$row['key']] = 'INSERT';
            }
        }
        return $plan;
    }

    private function require(mixed $condition, string $message): void
    {
        if (! $condition) throw new DemoImportGuard($message);
    }
}

/** Distinguish fixed, safe operator feedback from unexpected infrastructure exceptions. */
class DemoImportGuard extends RuntimeException {}
