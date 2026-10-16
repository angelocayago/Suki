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
        // Keep spaces and punctuation in an invalid submission so the owning
        // FormRequest can return a useful field error. Only the documented
        // +63 representation is normalized to the public 09XXXXXXXXX form.
        $value = trim((string) $phone);

        if (preg_match('/^\+639\d{9}$/', $value)) {
            return '0'.substr($value, 3);
        }

        // Keep invalid input for the owning FormRequest to report alongside
        // every other validation error instead of short-circuiting validation.
        return $value;
    }
}
