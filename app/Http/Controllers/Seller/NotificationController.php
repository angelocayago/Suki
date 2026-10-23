<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Support\SellerAccount;
use App\Support\SellerNotifications;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('seller.notifications', SellerNotifications::page(SellerAccount::current(), $request));
    }

    public function read(string $notification)
    {
        SellerNotifications::read(SellerAccount::current(), $notification, true);

        return redirect()->route('seller.notifications.index')->with('success', 'Notification marked as read.');
    }

    public function unread(string $notification)
    {
        SellerNotifications::read(SellerAccount::current(), $notification, false);

        return redirect()->route('seller.notifications.index')->with('success', 'Notification marked as unread.');
    }

    public function readAll()
    {
        $seller = SellerAccount::current();
        SellerNotifications::requireStorage();
        SellerNotifications::query($seller)->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);

        return redirect()->route('seller.notifications.index')->with('success', 'All your notifications marked as read.');
    }
}
