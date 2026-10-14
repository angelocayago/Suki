<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Notifications\ApplicationDecisionNotification;
use App\Services\Admin\AccountStatusService;
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








    public function approve(
        Application $application,
        AccountStatusService $accountStatusService
    )
    {
        $application = $accountStatusService->approveApplication(
            $application,
            auth()->user()
        );

        $application->user->notify(new ApplicationDecisionNotification($application));


        return back()
            ->with(
                'success',
                'Application approved successfully.'
            );

    }








    public function reject(
        Request $request,
        Application $application,
        AccountStatusService $accountStatusService
    )
    {


        $request->validate([

            'rejection_reason'
                => 'required|string|max:500',

            'custom_reason'
                => 'nullable|required_if:rejection_reason,Other|string|max:500',

        ]);



        $reason = $request->rejection_reason === 'Other'
            ? $request->custom_reason
            : $request->rejection_reason;

        $application = $accountStatusService->rejectApplication(
            $application,
            auth()->user(),
            $reason
        );

        $application->user->notify(new ApplicationDecisionNotification($application));



        return back()
            ->with(
                'success',
                'Application rejected successfully.'
            );

    }


}
