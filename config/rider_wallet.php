<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rider Delivery Pay
    |--------------------------------------------------------------------------
    |
    | BiliHub rider delivery pay is funded from the shipment/store shipping
    | fee, not from seller merchandise earnings and not from admin commission.
    |
    | 5000 = ₱50.00 per successful confirmed delivery.
    |
    | The actual credit is capped by the SellerOrder shipping fee, so the rider
    | can never be paid more than the shipping fee attached to that parcel.
    |
    | Example:
    | Shipping fee ₱65.00 -> Rider ₱50.00 -> Remaining ₱15.00 logistics/platform
    | Shipping fee ₱120.00 -> Rider ₱50.00 -> Remaining ₱70.00 logistics/platform
    |
    */
    'delivery_earning_minor' => 5000,
    'delivery_earning_source' => 'shipping_fee',

    // Minimum rider withdrawal, in centavos.
    'minimum_withdrawal_minor' => 10000,

    // Prevent a rider from creating another request while one is pending.
    'single_pending_withdrawal' => true,
];
