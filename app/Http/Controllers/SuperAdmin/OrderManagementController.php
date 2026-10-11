<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;


class OrderManagementController extends Controller
{


    /*
    |--------------------------------------------------------------------------
    | ORDER LIST
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {

        $query = Order::with([
            'buyer',
            'seller'
        ]);




        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($request->search) {

            $search = $request->search;


            $query->where(function ($q) use ($search) {


                $q->where(
                    'order_number',
                    'like',
                    "%{$search}%"
                )

                ->orWhereHas('buyer', function ($buyer) use ($search) {

                    $buyer->where(
                        'name',
                        'like',
                        "%{$search}%"
                    );

                })


                ->orWhereHas('seller', function ($seller) use ($search) {

                    $seller->where(
                        'name',
                        'like',
                        "%{$search}%"
                    );

                });


            });

        }







        /*
        |--------------------------------------------------------------------------
        | STATUS FILTER
        |--------------------------------------------------------------------------
        */

        if ($request->status) {

            $query->where(
                'status',
                $request->status
            );

        }







        $orders = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();







        /*
        |--------------------------------------------------------------------------
        | ORDER STATISTICS
        |--------------------------------------------------------------------------
        */

        $stats = [

    'total' => Order::count(),


    'placed' => Order::where(
        'status',
        'PLACED'
    )->count(),



    'processing' => Order::whereIn(
        'status',
        [
            'CONFIRMED',
            'PREPARING'
        ]
    )->count(),



    'completed' => Order::whereIn(
        'status',
        [
            'DELIVERED',
            'COMPLETED'
        ]
    )->count(),

];







        return view(
            'superadmin.orders.index',
            compact(
                'orders',
                'stats'
            )
        );

    }









    /*
    |--------------------------------------------------------------------------
    | ORDER DETAILS
    |--------------------------------------------------------------------------
    */

    public function show(Order $order)
    {


        $order->load([

            'buyer',

            'seller',

            'items',

            'payments'

        ]);



        return view(
            'superadmin.orders.show',
            compact('order')
        );


    }






}