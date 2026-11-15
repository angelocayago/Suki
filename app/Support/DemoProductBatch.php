<?php

namespace App\Support;

use App\Services\SellerInventoryService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Maps retained demo provenance to its reviewed customer-facing source data. */
class DemoProductBatch
{
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
            $this->require(! $this->containsCustomerFacingDemoLabel($item['name']) && ! $this->containsCustomerFacingDemoLabel($item['description']), 'Retained source text contains a demo, sample, lorem ipsum, or test label.');
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
                // legacy_reference preserves known demo provenance. Customer-facing text
                // deliberately remains the reviewed dataset text.
                'name' => $item['name'], 'description' => $item['description'],
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

    private function require(mixed $condition, string $message): void
    {
        if (! $condition) throw ValidationException::withMessages(['demo_batch' => $message]);
    }

    private function containsCustomerFacingDemoLabel(string $value): bool
    {
        return (bool) preg_match('/\b(?:demo|sample|test|lorem\s+ipsum)\b/i', $value);
    }
}
