<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Normalize flexible options into the existing primary/additional variants. */
class ProductOptions
{
    public const MAX_GROUPS = 3;
    public const MAX_VALUES = 20;
    public const MAX_COMBINATIONS = 50;

    public static function options(array $options): array
    {
        if (count($options) > self::MAX_GROUPS) self::invalid('variants', 'Use at most 3 option groups.');
        $out = [];
        $seen = [];
        foreach ($options as $name => $value) {
            $name = trim((string) $name);
            $value = trim((string) $value);
            if ($value === '') continue;
            self::name($name);
            if ($value === '' || mb_strlen($value) > 100 || preg_match('/[\pC]/u', $value) || ! preg_match('/[\pL\pN]/u', $value)) {
                self::invalid('variants', 'Enter meaningful option values of at most 100 characters.');
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) self::invalid('variants', 'Option names must be unique.');
            $seen[$key] = true;
            $out[$name] = $value;
        }
        return $out;
    }

    public static function creation(array $data): array
    {
        $groups = [];
        foreach ($data['option_groups'] ?? [] as $group) {
            $name = trim((string) ($group['name'] ?? ''));
            $raw = trim((string) ($group['values'] ?? ''));
            if ($name === '' && $raw === '') continue;
            self::name($name);
            $key = mb_strtolower($name);
            if (isset($groups[$key])) self::invalid('option_groups', 'Option names must be unique.');
            $values = [];
            foreach (explode(',', $raw) as $value) {
                $value = trim($value);
                if ($value === '') continue;
                self::options([$name => $value]);
                $values[mb_strtolower($value)] ??= $value;
            }
            if (! $values || count($values) > self::MAX_VALUES) self::invalid('option_groups', 'Each option needs 1–20 distinct values.');
            $groups[$key] = ['name' => $name, 'values' => array_values($values)];
        }
        if (count($groups) > self::MAX_GROUPS) self::invalid('option_groups', 'Use at most 3 option groups.');
        if ($data['product_mode'] === 'simple') {
            if ($groups || ! empty($data['variants'])) self::invalid('option_groups', 'Generate the options, or remove them to save a simple product.');
            $data['variation_name'] = $data['variation_value'] = $data['variants'] = [];
            return $data;
        }
        if (! $groups) self::invalid('option_groups', 'Add an option with values before generating variants.');
        $combinations = [[]];
        foreach ($groups as $group) {
            if (count($combinations) * count($group['values']) > self::MAX_COMBINATIONS) self::invalid('option_groups', 'Use at most 50 variant combinations.');
            $next = [];
            foreach ($combinations as $combination) {
                foreach ($group['values'] as $value) $next[] = $combination + [$group['name'] => $value];
            }
            $combinations = $next;
        }
        $expected = [];
        foreach ($combinations as $options) $expected[self::identity($options)] = $options;
        $rows = [];
        foreach ($data['variants'] ?? [] as $row) {
            $options = self::options($row['options'] ?? []);
            $key = self::identity($options);
            if (! isset($expected[$key]) || isset($rows[$key])) self::invalid('variants', 'Variant combinations must match the option groups without duplicates. Regenerate the combinations.');
            $options = $expected[$key];
            $row['options'] = $options;
            $row['name'] = Str::limit(implode(' / ', array_values($options)), 100, '');
            $row['weight'] = filled($row['weight'] ?? null) ? $row['weight'] : ($data['weight'] ?? null);
            $row['status'] = $data['status'];
            $rows[$key] = $row;
        }
        if (count($rows) !== count($expected)) self::invalid('variants', 'Generate every combination and enter its price and stock.');
        $rows = array_values($rows);
        $primary = array_shift($rows);
        $data['price'] = $primary['price'];
        $data['stock'] = $primary['stock'];
        $data['sku'] = $primary['sku'] ?? null;
        $data['weight'] = $primary['weight'];
        $data['primary_variant_name'] = $primary['name'];
        $data['variation_name'] = array_keys($primary['options']);
        $data['variation_value'] = array_values($primary['options']);
        $data['variants'] = $rows;
        return $data;
    }

    private static function identity(array $options): string
    {
        $pairs = [];
        foreach ($options as $name => $value) $pairs[mb_strtolower($name)] = mb_strtolower($value);
        ksort($pairs);
        return json_encode($pairs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function name(string $name): void
    {
        if (mb_strlen($name) > 40 || ! preg_match('/^[\pL\pN][\pL\pN _\/().%\-]*$/u', $name)
            || in_array(mb_strtolower($name), ['__proto__', 'prototype', 'constructor'], true)) {
            self::invalid('option_groups', 'Use meaningful option names of at most 40 characters (letters, numbers, spaces or ordinary punctuation).');
        }
    }

    private static function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
