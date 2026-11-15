<?php

return [
    // Read-only presentation; the secured foundation schema has been deployed.
    'enabled' => true,
    // No trusted custody/reserve allocator exists yet. These do not authorize
    // funding from a Seller debit, a PAID order, or an accepted remittance alone.
    'refund_credit_enabled' => false,
    'checkout_enabled' => false,
];
