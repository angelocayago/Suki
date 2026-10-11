<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\Seller;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {

        /*
        |--------------------------------------------------------------------------
        | PLATFORM STATISTICS
        |--------------------------------------------------------------------------
        */


        $stats = [

            'users' => User::count(),


            'buyers' => User::whereHas('roles', function ($query) {

                $query->where('name', 'buyer');

            })->count(),


            'sellers' => Seller::count(),


            'pending_users' => User::where(
                'status',
                'pending'
            )->count(),


            'orders' => class_exists(Order::class)
                ? Order::count()
                : 0,

        ];







        /*
        |--------------------------------------------------------------------------
        | USER GROWTH LAST 7 DAYS
        |--------------------------------------------------------------------------
        */


        $userGrowth = [];


        for ($i = 6; $i >= 0; $i--) {


            $date = Carbon::now()
                ->subDays($i);



            $userGrowth[] = [

                'date' =>
                    $date->format('M d'),


                'count' =>
                    User::whereDate(
                        'created_at',
                        $date
                    )->count(),

            ];


        }








        /*
        |--------------------------------------------------------------------------
        | ORDER STATUS OVERVIEW
        |--------------------------------------------------------------------------
        */


        $orderOverview = [

            'pending' => 0,

            'processing' => 0,

            'delivered' => 0,

            'cancelled' => 0,

        ];



        if(class_exists(Order::class)){


            $orderOverview['pending'] =
                Order::whereIn(
                    'status',
                    [
                        'PLACED',
                        'PENDING'
                    ]
                )->count();



            $orderOverview['processing'] =
                Order::whereIn(
                    'status',
                    [
                        'CONFIRMED',
                        'PREPARING',
                        'READY_FOR_PICKUP',
                        'PICKED_UP',
                        'AT_SORTING_CENTER',
                        'SORTED',
                        'ASSIGNED_TO_RIDER',
                        'OUT_FOR_DELIVERY'
                    ]
                )->count();




            $orderOverview['delivered'] =
                Order::whereIn(
                    'status',
                    [
                        'DELIVERED',
                        'COMPLETED'
                    ]
                )->count();




            $orderOverview['cancelled'] =
                Order::where(
                    'status',
                    'CANCELLED'
                )->count();


        }







        /*
        |--------------------------------------------------------------------------
        | RECENT DATA
        |--------------------------------------------------------------------------
        */


        $recentUsers =
            User::latest()
                ->take(5)
                ->get();




        $recentOrders =
            class_exists(Order::class)
                ? Order::latest()
                    ->take(5)
                    ->get()
                : collect();







        return view(
            'superadmin.dashboard',
            compact(
                'stats',
                'userGrowth',
                'orderOverview',
                'recentUsers',
                'recentOrders'
            )
        );


    }
}