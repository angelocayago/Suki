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
        if ($request->user()->isBuyer()) {
            $this->authorizeBuyer($request);
            $filter = $this->buyerFilter($request);
            $query = $request->user()->notifications()->reorder()->orderByDesc('created_at')->orderByDesc('id');
            if ($filter === 'unread') $query->whereNull('read_at');
            $notifications = $query->paginate(25)->withQueryString();
            return view('buyer.notifications', [
                'notifications' => $notifications,
                'rows' => app(\App\Support\BuyerNotifications::class)->present($request->user(), $notifications->getCollection()),
                'filter' => $filter,
                'unreadCount' => $request->user()->unreadNotifications()->count(),
            ]);
        }
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
        $this->authorizeBuyer($request);
        $query = $request->user()->notifications()->latest();
        if ($request->user()->isBuyer() && $this->buyerFilter($request) === 'unread') $query->whereNull('read_at');
        return response()->json([
            'notifications' => $query->paginate(25),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $this->authorizeBuyer($request);
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function markRead(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        $this->authorizeBuyer($request);
        $criteria = $request->user()->isBuyer() && $this->buyerFilter($request) === 'unread' ? ['filter' => 'unread'] : [];
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return $request->expectsJson() ? response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]) : redirect()->route('notifications.page', $criteria);
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeBuyer($request);
        $criteria = $request->user()->isBuyer() && $this->buyerFilter($request) === 'unread' ? ['filter' => 'unread'] : [];
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $request->expectsJson() ? response()->json(['unread_count' => 0]) : redirect()->route('notifications.page', $criteria);
    }

    public function preview(Request $request): JsonResponse
    {
        $presentation = app(\App\Support\BuyerNotifications::class);
        return response()->json([
            'notifications' => $presentation->present($request->user(), $presentation->recent($request->user())),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $row = app(\App\Support\BuyerNotifications::class)->present($request->user(), collect([$record]))[0];
        $record->markAsRead();
        return redirect()->to($row['destination'] ?? route('notifications.page'));
    }

    private function buyerFilter(Request $request): string
    {
        $values = $request->validate(['filter' => ['nullable', \Illuminate\Validation\Rule::in(['all', 'unread'])]]);
        return $values['filter'] ?? 'all';
    }

    /** Keep the existing other-role inbox behavior; apply Buyer portal access rules to Buyers. */
    private function authorizeBuyer(Request $request): void
    {
        if ($request->user()->isBuyer()) {
            abort_unless($request->user()->status === 'active' && ! $request->user()->is_suspended && $request->user()->hasVerifiedContacts(), 403);
        }
    }
}
