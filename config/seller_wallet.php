<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Minimum seller withdrawal
    |--------------------------------------------------------------------------
    |
    | Stored in centavos/minor units. Default: ₱100.00.
    |
    */
    'minimum_withdrawal_minor' => 10000,

    /*
    |--------------------------------------------------------------------------
    | Pending withdrawal policy
    |--------------------------------------------------------------------------
    |
    | When true, a seller must wait for the current withdrawal request to be
    | paid or rejected before submitting another withdrawal.
    |
    */
    'single_pending_withdrawal' => true,
];
