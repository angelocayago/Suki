<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use Illuminate\Http\Request;


class SellerManagementController extends Controller
{


    /*
    |--------------------------------------------------------------------------
    | SELLER LIST
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {

        $query = Seller::with('owner');



        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($request->search) {

            $search = $request->search;


            $query->where(function ($q) use ($search) {

                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('owner', function ($user) use ($search) {

                        $user->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");

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





        $sellers = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();






        /*
        |--------------------------------------------------------------------------
        | SELLER STATISTICS
        |--------------------------------------------------------------------------
        */

        $stats = [

            'total' => Seller::count(),


            'active' => Seller::where(
                'status',
                'active'
            )->count(),


            'pending' => Seller::where(
                'status',
                'pending'
            )->count(),


            'suspended' => Seller::where(
                'status',
                'suspended'
            )->count(),

        ];






        return view(
            'superadmin.sellers.index',
            compact(
                'sellers',
                'stats'
            )
        );

    }







    /*
    |--------------------------------------------------------------------------
    | SELLER PROFILE
    |--------------------------------------------------------------------------
    */

    public function show(Seller $seller)
    {

        $seller->load([
            'owner',
            'products',
            'orders'
        ]);



        return view(
            'superadmin.sellers.show',
            compact('seller')
        );

    }







    /*
    |--------------------------------------------------------------------------
    | UPDATE SELLER STATUS
    |--------------------------------------------------------------------------
    */

    public function updateStatus(
        Request $request,
        Seller $seller
    ) {


        $request->validate([

            'status'
                => 'required|in:active,pending,suspended'

        ]);



        $seller->update([

            'status'
                => $request->status

        ]);



        return back()
            ->with(
                'success',
                'Seller status updated successfully.'
            );

    }



}