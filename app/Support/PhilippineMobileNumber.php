<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class PhilippineMobileNumber
{
    public static function normalise(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $digits = preg_replace('/[\s\-()]+/', '', $value) ?? '';
        if (preg_match('/^09(\d{9})$/', $digits, $match)) return '+639'.$match[1];
        if (preg_match('/^639(\d{9})$/', $digits, $match)) return '+639'.$match[1];
        if (preg_match('/^\+639(\d{9})$/', $digits, $match)) return '+639'.$match[1];
        return null;
    }

    public static function required(string $value): string
    {
        return self::normalise($value) ?? throw ValidationException::withMessages(['phone' => 'Provide a valid Philippine mobile number.']);
    }

    public static function mask(?string $canonical): ?string
    {
        if (! $canonical) return null;
        return substr($canonical, 0, 5).'••••'.substr($canonical, -3);
    }
}
