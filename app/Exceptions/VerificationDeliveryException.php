<?php

namespace App\Exceptions;

use RuntimeException;

class VerificationDeliveryException extends RuntimeException
{
    public function __construct(public readonly string $channel)
    {
        parent::__construct("Unable to deliver {$channel} verification code.");
    }
}
