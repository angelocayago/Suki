<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AccountStatusService;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{

    public function index(Request $request)
    {

        $query = User::query();



        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($request->search) {

            $search = $request->search;


            $query->where(function ($q) use ($search) {

                $q->where(
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
        | FILTER ROLE
        |--------------------------------------------------------------------------
        */

        if ($request->role) {

            $query->where(
                'role',
                $request->role
            );

        }





        /*
        |--------------------------------------------------------------------------
        | FILTER STATUS
        |--------------------------------------------------------------------------
        */

        if ($request->status) {

            $query->where(
                'status',
                $request->status
            );

        }





        $users = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();








        /*
        |--------------------------------------------------------------------------
        | USER STATISTICS
        |--------------------------------------------------------------------------
        */

        $stats = [

            'total' => User::count(),


            'buyers' => User::where(
                'role',
                'buyer'
            )->count(),


            'sellers' => User::where(
                'role',
                'seller'
            )->count(),


            'suspended' => User::where(
                'status',
                'suspended'
            )->count(),

        ];








        return view(
            'admin.users.index',
            compact(
                'users',
                'stats'
            )
        );

    }









    /*
    |--------------------------------------------------------------------------
    | LIVE SEARCH
    |--------------------------------------------------------------------------
    */

    public function search(Request $request)
    {

        $query = User::query();





        if ($request->search) {

            $search = $request->search;


            $query->where(function ($q) use ($search) {

                $q->where(
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







        if ($request->role) {

            $query->where(
                'role',
                $request->role
            );

        }







        if ($request->status) {

            $query->where(
                'status',
                $request->status
            );

        }







        $users = $query
            ->latest()
            ->get();







        return view(
            'admin.users.partials.table',
            compact('users')
        );

    }









    /*
    |--------------------------------------------------------------------------
    | UPDATE USER STATUS
    |--------------------------------------------------------------------------
    */

    public function updateStatus(
        Request $request,
        User $user,
        AccountStatusService $accountStatusService
    )
    {

        $validated = $request->validate([

            'status' =>
                'required|in:active,suspended,inactive',

            'reason' => 'nullable|string|max:1000',

            'reason_code' => 'nullable|required_if:status,suspended,inactive|in:policy_violation,suspicious_activity,fraud_misleading_activity,repeated_complaints,verification_issue,prohibited_activity,terms_violation,other',

            'custom_reason' => 'nullable|required_if:reason_code,other|string|max:1000',

        ]);





        $accountStatusService->changeStatus(
            $user,
            $request->string('status')->toString(),
            $request->user(),
            ($validated['reason_code'] ?? null) === 'other'
                ? $validated['custom_reason']
                : ($validated['reason_code'] ?? $request->input('reason'))
        );





        return back()
            ->with(
                'success',
                'User status updated successfully.'
            );

    }









    /*
    |--------------------------------------------------------------------------
    | USER PROFILE
    |--------------------------------------------------------------------------
    */

    public function show(User $user)
    {
        $user->load([
            'sellers',
            'accountStatusEvents' => function ($query) {
                $query->with('actor')->latest()->limit(25);
            },
        ]);

        return view(
            'admin.users.show',
            compact('user')
        );

    }


}
