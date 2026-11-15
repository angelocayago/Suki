<?php

namespace App\Http\Controllers\Api\V1;

/**
 * Versioned entry point for the established Buyer Sanctum contract.
 *
 * The implementation remains in the original controller so existing mobile
 * clients and the V1 API use the same authentication and token lifecycle.
 */
class AuthController extends \App\Http\Controllers\Api\AuthController {}
