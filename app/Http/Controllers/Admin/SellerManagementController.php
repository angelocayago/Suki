<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Seller;
use App\Services\Admin\AccountStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;


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
                'approved'
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
            'admin.sellers.index',
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
            'orders',
            'category',
            'allowedCategories',
        ])->loadCount([
            'violations',
            'warnings',
            'violations as open_violations_count' => fn ($query) => $query->where('status', 'OPEN'),
        ]);

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();



        return view(
            'admin.sellers.show',
            compact('seller', 'categories')
        );

    }

    public function updateAllowedCategories(Request $request, Seller $seller)
    {
        $validated = $request->validate([
            'categories' => ['nullable', 'array', 'max:1'],
            'categories.*' => [
                'integer',
                'distinct',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);

        DB::transaction(function () use ($request, $seller, $validated) {
            $now = now();
            $assignments = collect($validated['categories'] ?? [])->mapWithKeys(fn ($categoryId) => [
                (int) $categoryId => [
                    'assigned_by' => $request->user()->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ])->all();

            $seller->update([
                'category_id' => array_key_first($assignments),
            ]);
            $seller->allowedCategories()->sync($assignments);
        });

        return back()->with('success', 'Seller assigned category updated.');
    }







    /*
    |--------------------------------------------------------------------------
    | UPDATE SELLER STATUS
    |--------------------------------------------------------------------------
    */

    public function updateStatus(
        Request $request,
        Seller $seller,
        AccountStatusService $accountStatusService
    ) {


        $request->validate([

            'status'
                => 'required|in:approved,suspended,inactive',

            'reason'
                => 'nullable|required_if:status,suspended,inactive|string|max:1000',

        ]);



        $accountStatusService->changeStatus(
            $seller->owner,
            $request->status === 'approved' ? 'active' : $request->status,
            $request->user(),
            $request->input('reason'),
            'seller_management'
        );



        return back()
            ->with(
                'success',
                'Seller status updated successfully.'
            );

    }

public function search(Request $request)
{

    $query = Seller::with('owner');



    if ($request->search) {

        $search = $request->search;


        $query->where(function ($q) use ($search) {


            $q->where(
                'name',
                'like',
                "%{$search}%"
            )
            ->orWhereHas(
                'owner',
                function ($user) use ($search) {


                    $user->where(
                        'name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'email',
                        'like',
                        "%{$search}%"
                    );


                }
            );


        });


    }






    if ($request->status) {

        $query->where(
            'status',
            $request->status
        );

    }






    $sellers = $query
        ->latest()
        ->get();






    return view(
        'admin.sellers.partials.table',
        compact('sellers')
    );

}

}
