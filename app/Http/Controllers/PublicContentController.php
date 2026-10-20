<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Setting;
use Illuminate\View\View;

class PublicContentController extends Controller
{
    private const POLICIES = [
        'terms-of-service' => ['terms_of_service', 'Terms of Service'],
        'privacy-policy' => ['privacy_policy', 'Privacy Policy'],
        'shipping-policy' => ['shipping_policy', 'Shipping Policy'],
        'returns-refund-policy' => ['returns_refund_policy', 'Returns & Refund Policy'],
    ];

    public function policy(string $policy): View
    {
        [$key, $title] = self::POLICIES[$policy];

        return view('policies.show', [
            'title' => $title,
            'content' => Setting::query()->where('key', $key)->value('value'),
        ]);
    }

    public function buyerHelp(): View
    {
        return view('buyer.help', [
            'announcements' => Announcement::query()
                ->where('status', 'active')
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
