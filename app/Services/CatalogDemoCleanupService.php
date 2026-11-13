<?php

namespace App\Services;

use App\Models\CatalogCleanupAudit;
use App\Models\Product;
use App\Support\DemoProductBatch;
use Illuminate\Support\Facades\DB;

/** Converts only known importer records; it never guesses about customer catalog data. */
class CatalogDemoCleanupService
{
    public function __construct(private readonly ProductMediaService $media) {}

    public function plan(): array
    {
        $rows = collect(app(DemoProductBatch::class)->dataset(0))->keyBy('reference');

        return Product::query()->with(['images', 'variants'])
            ->where('legacy_reference', 'like', 'suki-demo:%')
            ->orderBy('id')->get()->map(function (Product $product) use ($rows): array {
                $row = $rows->get($product->legacy_reference);
                $before = ['name' => $product->name, 'description' => $product->description, 'is_active' => (bool) $product->is_active];
                if (! $row) return compact('product', 'before') + ['action' => 'SKIP', 'reason' => 'Unknown importer provenance.'];
                if (! $this->media->hasUsableImage($product) && $product->is_active) {
                    return compact('product', 'before') + ['action' => 'UNPUBLISH', 'reason' => 'No usable local product image.', 'after' => ['is_active' => false]];
                }
                if (! $this->media->hasUsableImage($product)) {
                    return compact('product', 'before') + ['action' => 'SKIP', 'reason' => 'Already unpublished because no usable local product image.'];
                }
                $after = ['name' => $row['data']['name'], 'description' => $row['data']['description'], 'is_active' => (bool) $product->is_active];
                return compact('product', 'before', 'after') + [
                    'action' => $before === $after ? 'SKIP' : 'NORMALIZE',
                    'reason' => $before === $after ? 'Already customer-facing.' : 'Known importer provenance has customer-facing source text.',
                ];
            })->all();
    }

    public function apply(): array
    {
        return DB::transaction(function (): array {
            $changed = [];
            foreach ($this->plan() as $entry) {
                if (! in_array($entry['action'], ['NORMALIZE', 'UNPUBLISH'], true)) continue;
                $product = Product::query()->with(['images', 'variants'])->lockForUpdate()->findOrFail($entry['product']->id);
                // Recalculate while locked, so a changed image cannot turn a preview into an unsafe write.
                $current = collect($this->plan())->first(fn ($item) => $item['product']->id === $product->id);
                if (! $current || ! in_array($current['action'], ['NORMALIZE', 'UNPUBLISH'], true)) continue;
                $after = $current['after'];
                $product->forceFill($after)->save();
                CatalogCleanupAudit::create([
                    'product_id' => $product->id, 'action' => strtolower($current['action']),
                    'before' => $current['before'], 'after' => $after, 'reason' => $current['reason'],
                ]);
                $changed[] = $product->id;
            }
            return $changed;
        }, 3);
    }
}
