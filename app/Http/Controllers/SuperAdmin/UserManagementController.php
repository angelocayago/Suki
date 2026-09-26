<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{

    public function index(Request $request)
    {


        $query = User::query();



        if($request->search){


            $search = $request->search;


            $query->where(function($q) use($search){


                $q->where('name','like',"%{$search}%")
                ->orWhere('email','like',"%{$search}%")
                ->orWhere('phone','like',"%{$search}%");


            });


        }





        if($request->role){


            $query->where(
                'role',
                $request->role
            );


        }






        if($request->status){


            $query->where(
                'status',
                $request->status
            );


        }






        $users = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();







        $stats = [


            'total'=>User::count(),


            'buyers'=>User::where(
                'role',
                'buyer'
            )->count(),


            'sellers'=>User::where(
                'role',
                'seller'
            )->count(),


            'suspended'=>User::where(
                'status',
                'suspended'
            )->count(),

        ];







        return view(
            'superadmin.users.index',
            compact(
                'users',
                'stats'
            )
        );


    }







    public function updateStatus(
        Request $request,
        User $user
    )
    {


        $request->validate([

            'status'=>
            'required|in:active,suspended,inactive'

        ]);




        $user->update([


            'status'=>$request->status,


            'is_suspended'=>
            $request->status === 'suspended'


        ]);




        return back()->with(
            'success',
            'User status updated successfully.'
        );


    }


}