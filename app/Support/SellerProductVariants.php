<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerProductVariants
{
    public static function type(Product $product): string
    {
        if (($product->seller_listing_data['product_type'] ?? null) === 'variable') {
            return 'variable';
        }

        return $product->variants->count() > 1 || $product->variants->contains(fn ($v) => ! empty($v->options)) ? 'legacy' : 'simple';
    }

    public static function key(array $options): string
    {
        $normalized = [];
        foreach ($options as $name => $value) {
            $normalized[mb_strtolower(Str::squish($name))] = mb_strtolower(Str::squish($value));
        }
        ksort($normalized);

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function combinations(array $groups): array
    {
        $result = [[]];
        foreach ($groups as $group) {
            $next = [];
            foreach ($result as $options) {
                foreach ($group['values'] as $value) {
                    $next[] = $options + [$group['name'] => $value];
                }
            }
            $result = $next;
        }

        return $result;
    }

    public static function input(Request $request, ?Product $product = null): array
    {
        $savedType = $product ? self::type($product) : 'simple';
        $request->validate(['product_type' => 'sometimes|required|string|in:simple,variable,legacy']);
        $type = $request->input('product_type', $savedType);
        if (($product && $type !== $savedType) || (! $product && $type === 'legacy')) {
            throw ValidationException::withMessages(['product_type' => 'Saved product types cannot be converted. Create a separate listing to change its selling configuration.']);
        }
        if ($type !== 'variable') {
            return ['product_type' => $type];
        }
        $data = $request->validate([
            'option_groups' => 'required|array|min:1|max:3',
            'option_groups.*' => 'required|array:name,values',
            'option_groups.*.name' => 'required|string|max:50',
            'option_groups.*.values' => 'required|array|min:1|max:10',
            'option_groups.*.values.*' => 'required|string|max:50',
            'variant_rows' => 'required|array|min:1|max:50',
            'variant_rows.*' => 'required|array:id,options,selections,price,stock,is_active,sku,sale_price,on_sale',
            'variant_rows.*.id' => 'nullable|integer|min:1',
            'variant_rows.*.options' => 'sometimes|array|min:1|max:3',
            'variant_rows.*.options.*' => 'required|string|max:50',
            'variant_rows.*.selections' => 'sometimes|array|min:1|max:3',
            'variant_rows.*.selections.*' => 'required|string|max:50',
            'variant_rows.*.price' => 'required|numeric|decimal:0,2|gt:0|max:42949672.95',
            'variant_rows.*.stock' => 'required|integer|min:0|max:4294967295',
            'variant_rows.*.is_active' => 'required|boolean',
            'variant_rows.*.sale_price' => 'prohibited',
            'variant_rows.*.on_sale' => 'prohibited',
        ]);
        if ($request->boolean('on_sale') || $request->filled('sale_price')) {
            throw ValidationException::withMessages(['on_sale' => 'Sale pricing is currently available for simple products only.']);
        }
        $groups = [];
        $names = [];
        $count = 1;
        foreach ($data['option_groups'] as $group) {
            $name = Str::squish($group['name']);
            $values = array_map(fn ($v) => Str::squish($v), array_values($group['values']));
            $nameKey = mb_strtolower($name);
            $valueKeys = array_map('mb_strtolower', $values);
            if ($name === '' || in_array($nameKey, $names, true) || in_array('', $values, true) || count(array_unique($valueKeys)) !== count($values)) {
                throw ValidationException::withMessages(['option_groups' => 'Use unique, nonempty option names and values. Whitespace and case differences do not create distinct options.']);
            }
            $names[] = $nameKey;
            $groups[] = ['name' => $name, 'values' => $values];
            $count *= count($values);
        }
        if ($count > 50) {
            throw ValidationException::withMessages(['option_groups' => 'Use at most 50 sellable combinations.']);
        }
        $rows = [];
        foreach ($data['variant_rows'] as $row) {
            unset($row['sku']);
            if (isset($row['selections'])) {
                if (isset($row['options']) || count($row['selections']) !== count($groups)) {
                    throw ValidationException::withMessages(['variant_rows' => 'Select one value for each option group.']);
                }
                $row['options'] = array_combine(array_column($groups, 'name'), array_values($row['selections']));
                unset($row['selections']);
            }
            if (empty($row['options'])) {
                throw ValidationException::withMessages(['variant_rows' => 'Each variant must have its option combination.']);
            }
            $rows[] = $row;
        }
        $result = ['product_type' => $type, 'has_variations' => true, 'option_groups' => $groups, 'variant_rows' => $rows];
        self::guard($product, $result);

        return $result;
    }

    public static function guard(?Product $product, array $data): void
    {
        if ($product && ($data['product_type'] ?? self::type($product)) !== self::type($product)) {
            throw ValidationException::withMessages(['product_type' => 'Saved product types cannot be converted.']);
        }
        if (($data['product_type'] ?? null) !== 'variable') {
            return;
        }
        $groups = $data['option_groups'];
        foreach ($product?->seller_listing_data['option_groups'] ?? [] as $savedGroup) {
            $incoming = collect($groups)->firstWhere('name', $savedGroup['name']);
            if (! $incoming || array_diff($savedGroup['values'], $incoming['values'])) {
                throw ValidationException::withMessages(['option_groups' => 'Saved labels cannot be renamed or removed. Deactivate combinations to preserve their inventory history.']);
            }
        }
        if ($product && count($groups) !== count($product->seller_listing_data['option_groups'] ?? [])) {
            throw ValidationException::withMessages(['option_groups' => 'Saved option groups cannot be added or removed. You can add values to existing groups.']);
        }
        $expected = array_map(fn ($o) => self::key($o), self::combinations($groups));
        $actual = array_map(fn ($r) => self::key($r['options']), $data['variant_rows']);
        if (count($actual) !== count(array_unique($actual)) || count($actual) !== count($expected) || array_diff($actual, $expected)) {
            throw ValidationException::withMessages(['variant_rows' => 'Generate each option combination exactly once and enter its price and stock.']);
        }
    }

    public static function sync(Product $product, array $data, $existing): void
    {
        $byKey = [];
        foreach ($existing as $variant) {
            $key = self::key($variant->options ?? []);
            if (isset($byKey[$key])) {
                throw ValidationException::withMessages(['variant_rows' => 'Existing duplicate combinations require a support review.']);
            }
            $byKey[$key] = $variant;
        }
        $canonical = [];
        foreach (self::combinations($data['option_groups']) as $options) {
            $canonical[self::key($options)] = $options;
        }
        if (array_diff(array_keys($byKey), array_keys($canonical))) {
            throw ValidationException::withMessages(['variant_rows' => 'Existing variant options require a support review before changing this configuration.']);
        }
        foreach ($product->variants()->onlyTrashed()->get() as $archived) {
            if (isset($canonical[self::key($archived->options ?? [])])) {
                throw ValidationException::withMessages(['variant_rows' => 'A deleted variant already uses these options. Contact support to restore its original identity.']);
            }
        }
        foreach ($data['variant_rows'] as $row) {
            $key = self::key($row['options']);
            $variant = $byKey[$key] ?? null;
            if (! empty($row['id']) && (! $variant || $variant->id !== (int) $row['id'])) {
                throw ValidationException::withMessages(['variant_rows' => 'Variant IDs must match this product and their original options.']);
            }
        }
        $rows = collect($data['variant_rows'])->keyBy(fn ($r) => self::key($r['options']));
        foreach ($canonical as $key => $options) {
            $row = $rows[$key];
            $variant = $byKey[$key] ?? null;
            $attributes = ['options' => $options, 'name' => implode(' / ', array_values($options)),
                'price_minor' => (int) round((float) $row['price'] * 100), 'stock' => (int) $row['stock'], 'is_active' => (bool) $row['is_active']];
            if ($variant) {
                $variant->fill($attributes);
                if ($variant->isDirty()) {
                    $variant->save();
                }
            } else {
                $saved = SellerProductSku::createVariant($product, $attributes, $existing->isNotEmpty());
                // Only the first configuration of a new product uses the base SKU.
                $existing->push($saved);
            }
        }
    }
}
