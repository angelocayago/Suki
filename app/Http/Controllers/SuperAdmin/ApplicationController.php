<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;


class ApplicationController extends Controller
{


    public function index(Request $request)
    {

        $query = Application::with([
            'user',
            'user.sellers'
        ]);



        if ($request->status) {

            $query->where(
                'status',
                $request->status
            );

        }



        if ($request->application_type) {

            $query->where(
                'application_type',
                $request->application_type
            );

        }



        $applications = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();




        $stats = [

            'total' => Application::count(),

            'pending' => Application::where(
                'status',
                'pending'
            )->count(),


            'approved' => Application::where(
                'status',
                'approved'
            )->count(),


            'rejected' => Application::where(
                'status',
                'rejected'
            )->count(),

        ];



        return view(
            'superadmin.applications.index',
            compact(
                'applications',
                'stats'
            )
        );

    }







    public function show(Application $application)
    {

        $application->load([
            'user',
            'user.sellers'
        ]);


        return view(
            'superadmin.applications.show',
            compact('application')
        );

    }








    public function approve(Application $application)
    {

        $application->update([

            'status' => 'approved',

            'reviewed_at' => now(),

        ]);


        return back()
            ->with(
                'success',
                'Application approved successfully.'
            );

    }








    public function reject(
        Request $request,
        Application $application
    )
    {


        $request->validate([

            'rejection_reason'
                => 'required|string|max:500'

        ]);



        $application->update([

            'status' => 'rejected',

            'rejection_reason'
        => $request->rejection_reason === 'Other'
            ? $request->custom_reason
            : $request->rejection_reason,

            'reviewed_at'
                => now(),

        ]);



        return back()
            ->with(
                'success',
                'Application rejected successfully.'
            );

    }


}