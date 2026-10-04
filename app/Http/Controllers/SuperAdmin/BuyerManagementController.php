<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class BuyerManagementController extends Controller
{
    public function index(Request $request)
    {

        /*
        |--------------------------------------------------------------------------
        | BUYERS QUERY
        |--------------------------------------------------------------------------
        */

        $buyersQuery = User::query()
            ->where('role', 'buyer')
            ->withCount('buyerOrders')
            ->withSum(
                'buyerOrders',
                'total_amount'
            );





        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {

            $search = $request->search;


            $buyersQuery->where(function ($query) use ($search) {

                $query->where(
                    'name',
                    'like',
                    "%{$search}%"
                )
                ->orWhere(
                    'email',
                    'like',
                    "%{$search}%"
                )
                ->orWhere(
                    'phone',
                    'like',
                    "%{$search}%"
                );

            });

        }







        /*
        |--------------------------------------------------------------------------
        | STATUS FILTER
        |--------------------------------------------------------------------------
        */

        if ($request->filled('status')) {

            $buyersQuery->where(
                'status',
                $request->status
            );

        }







        $buyers =
            $buyersQuery
            ->latest()
            ->paginate(10)
            ->withQueryString();








        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */


        $stats = [

            'total' => User::where(
                'role',
                'buyer'
            )->count(),


            'active' => User::where(
                'role',
                'buyer'
            )
            ->where(
                'status',
                'active'
            )
            ->count(),


            'suspended' => User::where(
                'role',
                'buyer'
            )
            ->where(
                'is_suspended',
                true
            )
            ->count(),



            'with_orders' => User::where(
                'role',
                'buyer'
            )
            ->whereHas(
                'buyerOrders'
            )
            ->count(),

        ];







        return view(
            'superadmin.buyers.index',
            compact(
                'buyers',
                'stats'
            )
        );

    }







    public function updateStatus(
        Request $request,
        User $buyer
    )
    {

        $request->validate([

            'status' => [
                'required',
                'in:active,suspended,inactive'
            ]

        ]);



        $buyer->update([

            'status' =>
                $request->status,


            'is_suspended' =>
                $request->status === 'suspended'

        ]);



        return back()
            ->with(
                'success',
                'Buyer status updated successfully.'
            );

    }
}