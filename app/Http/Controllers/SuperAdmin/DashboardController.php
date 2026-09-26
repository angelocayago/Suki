<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\Seller;

class DashboardController extends Controller
{
    public function index()
    {

        $stats = [

            // Total registered users
            'users' => User::count(),


            // Buyers through roles table
            'buyers' => User::whereHas('roles', function ($query) {

                $query->where('name', 'buyer');

            })->count(),


            // Sellers
            'sellers' => Seller::count(),


            // Pending accounts
            'pending_users' => User::where('status', 'pending')
                ->count(),


            // Orders
            'orders' => class_exists(Order::class)
                ? Order::count()
                : 0,

        ];


        return view(
            'superadmin.dashboard',
            compact('stats')
        );
    }
}