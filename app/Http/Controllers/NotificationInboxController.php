<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationInboxController extends Controller
{
    public function page(Request $request): View
    {
        $layout = match ($request->user()->role) {
            'seller' => 'layouts.seller',
            'logistics' => 'layouts.logistics',
            'rider' => 'layouts.rider',
            'admin', 'superadmin' => 'admin.layout',
            default => 'layouts.app',
        };
        return view('notifications.index', [
            'layout' => $layout,
            'notifications' => $request->user()->notifications()->latest()->paginate(25),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'notifications' => $request->user()->notifications()->latest()->paginate(25),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function markRead(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return $request->expectsJson() ? response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]) : redirect()->route('notifications.page');
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $request->expectsJson() ? response()->json(['unread_count' => 0]) : redirect()->route('notifications.page');
    }
}
