<?php

return [
    // KWEK-KWEK. This value is ignored unless the application environment is local.
    // Set to 0 to disable the development exception without changing shop records.
    'local_pending_shop_id' => env('SELLER_MESSAGING_LOCAL_PENDING_SHOP_ID', 9),
];
