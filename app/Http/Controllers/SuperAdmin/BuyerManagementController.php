<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AccountStatusService;
use Illuminate\Http\Request;

class BuyerManagementController extends Controller
{
    public function index(Request $request)
    {
        $buyersQuery = User::query()
            ->whereHas('roles', function ($query) {

                $query->where(
                    'name',
                    'buyer'
                );

            })
            ->withCount('buyerOrders')
            ->withSum(
                'buyerOrders',
                'total_amount'
            );



        /*
        |--------------------------------------------------------------------------
        | SEARCH FILTER
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {

            $search = $request->search;


            $buyersQuery->where(function ($query) use ($search) {

                $query
                    ->where(
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







        $buyers = $buyersQuery
            ->latest()
            ->paginate(10)
            ->withQueryString();








        /*
        |--------------------------------------------------------------------------
        | BUYER SUMMARY
        |--------------------------------------------------------------------------
        */

        $stats = [


            'total' => User::whereHas(
                'roles',
                function ($query) {

                    $query->where(
                        'name',
                        'buyer'
                    );

                }
            )->count(),





            'active' => User::whereHas(
                'roles',
                function ($query) {

                    $query->where(
                        'name',
                        'buyer'
                    );

                }
            )
            ->where(
                'status',
                'active'
            )
            ->count(),





            'suspended' => User::whereHas(
                'roles',
                function ($query) {

                    $query->where(
                        'name',
                        'buyer'
                    );

                }
            )
            ->where(
                'is_suspended',
                true
            )
            ->count(),





            'with_orders' => User::whereHas(
                'roles',
                function ($query) {

                    $query->where(
                        'name',
                        'buyer'
                    );

                }
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
        User $buyer,
        AccountStatusService $accountStatusService
    )
    {

        $request->validate([

            'status' => [
                'required',
                'in:active,suspended,inactive'
            ],
            'reason' => 'nullable|required_if:status,suspended,inactive|string|max:1000',

        ]);



        abort_unless($buyer->isBuyer(), 404);

        $accountStatusService->changeStatus(
            $buyer,
            $request->string('status')->toString(),
            $request->user(),
            $request->input('reason')
        );



        return back()->with(
            'success',
            'Buyer status updated successfully.'
        );

    }









    /*
    |--------------------------------------------------------------------------
    | LIVE SEARCH
    |--------------------------------------------------------------------------
    */

    public function search(Request $request)
    {

        $search = $request->search;



        $buyersQuery = User::query()
            ->whereHas('roles', function ($query) {

                $query->where(
                    'name',
                    'buyer'
                );

            })
            ->withCount('buyerOrders')
            ->withSum(
                'buyerOrders',
                'total_amount'
            );







        if (!empty($search)) {


            $buyersQuery->where(function ($query) use ($search) {


                $query
                    ->where(
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







        $buyers = $buyersQuery
            ->latest()
            ->get();







        return view(
            'superadmin.buyers.partials.table',
            compact('buyers')
        );

    }

}
