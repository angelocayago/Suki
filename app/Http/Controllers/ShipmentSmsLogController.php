<?php

namespace App\Http\Controllers;

use App\Models\{LogisticsProvider, ShipmentSmsNotification};
use App\Services\ShipmentSmsService;
use Illuminate\Http\Request;

class ShipmentSmsLogController extends Controller
{
    public function admin(Request $request)
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'superadmin']), 403);
        return view('admin.sms-notifications.index', ['notifications' => $this->filtered($request, ShipmentSmsNotification::query())]);
    }

    public function logistics(Request $request)
    {
        $provider = LogisticsProvider::where('user_id', $request->user()->id)->firstOrFail();
        return view('logistics.sms-notifications.index', [
            'provider' => $provider,
            'notifications' => $this->filtered($request, ShipmentSmsNotification::where('logistics_provider_id', $provider->id)),
        ]);
    }

    public function retry(Request $request, ShipmentSmsNotification $notification, ShipmentSmsService $sms)
    {
        $provider = LogisticsProvider::where('user_id', $request->user()->id)->firstOrFail();
        abort_unless((int) $notification->logistics_provider_id === (int) $provider->id, 403);
        $request->validate(['confirm' => ['required', 'accepted']]);
        $sms->retry($notification, $request->user());
        return back()->with('success', 'The failed transactional notice was queued for retry.');
    }

    private function filtered(Request $request, $query)
    {
        $request->validate(['status' => ['nullable', 'in:PENDING,PROCESSING,SENT,FAILED,CANCELLED'], 'event' => ['nullable', 'in:DELIVERY_ACCEPTED,OUT_FOR_DELIVERY,DELIVERY_ATTEMPT_FAILED,DELIVERED'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        if ($request->filled('status')) $query->where('status', (string) $request->input('status'));
        if ($request->filled('event')) $query->where('event', (string) $request->input('event'));
        if ($request->filled('from')) $query->whereDate('created_at', '>=', $request->date('from'));
        if ($request->filled('to')) $query->whereDate('created_at', '<=', $request->date('to'));
        return $query->with(['shipment.sellerOrder.order'])->latest()->paginate(30)->withQueryString();
    }
}
