<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class IdentityNormalizer
{
    public static function email(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    public static function philippinePhone(?string $phone): string
    {
        $value = preg_replace('/[\s()-]+/', '', trim((string) $phone));

        if (preg_match('/^\+639\d{9}$/', $value)) {
            return '0'.substr($value, 3);
        }

        // Keep invalid input for the owning FormRequest to report alongside
        // every other validation error instead of short-circuiting validation.
        return $value;
    }
}
