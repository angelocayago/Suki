<?php

namespace App\Observers;

use App\Models\SellerVoucher;
use App\Support\SellerNotifications;

class SellerVoucherNotificationObserver
{
    public function created(SellerVoucher $voucher): void
    {
        SellerNotifications::voucherCreated($voucher);
    }
}
