<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const POLICY_KEYS = [
        'terms_of_service',
        'privacy_policy',
        'shipping_policy',
        'returns_refund_policy',
    ];

    public function index()
    {
        $settings = Setting::query()
            ->pluck('value', 'key');


        $announcements = Announcement::query()
            ->latest()
            ->get();


        return view(
            'admin.settings.index',
            compact(
                'settings',
                'announcements'
            )
        );
    }





    public function updatePlatform(Request $request)
    {
        $validated = $request->validate([

            'platform_name' => [
                'required',
                'string',
                'max:255',
            ],

            'support_email' => [
                'required',
                'email',
            ],

            'contact_number' => [
                'nullable',
                'string',
                'max:50',
            ],

        ]);



        foreach ($validated as $key => $value) {

            Setting::updateOrCreate(

                [
                    'key' => $key,
                ],

                [
                    'value' => $value,
                    'type' => 'text',
                ]

            );

        }



        return back()
            ->with(
                'success',
                'Platform information updated.'
            );
    }
    public function updatePolicies(Request $request)
    {
        $validated = $request->validate(array_fill_keys(self::POLICY_KEYS, [
            'nullable',
            'string',
            'max:50000',
        ]));



        foreach ($validated as $key => $value) {

            Setting::updateOrCreate(

                [
                    'key' => $key,
                ],

                [
                    'value' => $value,
                    'type' => 'text',
                ]

            );

        }



        return back()
            ->with(
                'success',
                'Policies updated.'
            );
    }





    public function storeAnnouncement(Request $request)
    {
        $validated = $request->validate([

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'message' => [
                'required',
                'string',
            ],

        ]);



        Announcement::create([

            'title' => $validated['title'],

            'message' => $validated['message'],

            'status' => 'active',

        ]);



        return back()
            ->with(
                'success',
                'Announcement created.'
            );
    }





    public function updateAnnouncement(
        Request $request,
        Announcement $announcement
    )
    {
        $validated = $request->validate([

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'message' => [
                'required',
                'string',
            ],

            'status' => [
                'required',
                'in:active,inactive',
            ],

        ]);



        $announcement->update(
            $validated
        );



        return back()
            ->with(
                'success',
                'Announcement updated.'
            );
    }





    public function deactivateAnnouncement(
        Announcement $announcement
    )
    {
        $announcement->update(['status' => 'inactive']);


        return back()
            ->with(
                'success',
                'Announcement deactivated.'
            );
    }
}
