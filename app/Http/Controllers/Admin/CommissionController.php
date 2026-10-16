<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Order;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    protected float $defaultRate = 10;

    public function ensureCommissionsExist(): void
    {
        $service = app(CommissionService::class);

        Order::query()
            ->whereIn('status', ['delivered', 'completed'])
            ->with(['sellerOrders.seller', 'sellerOrders.items'])
            ->chunkById(100, function ($orders) use ($service) {
                foreach ($orders as $order) {
                    $service->generateForOrder($order);
                }
            });
    }

    public function index(Request $request)
    {
        $this->ensureCommissionsExist();

        $query = Commission::with(['order', 'seller.seller']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('seller')) {
            $query->where('seller_id', $request->seller);
        }

        $commissions = $query->latest()->paginate(20);

        // Sum integer centavos, then convert to pesos only for display.
        $totalEarned = Commission::sum('amount_minor') / 100;
        $totalPending = Commission::where('status', 'pending')->sum('amount_minor') / 100;
        $totalPaid = Commission::where('status', 'paid')->sum('amount_minor') / 100;

        $sellers = User::whereHas('roles', fn ($q) => $q->where('name', 'seller'))
    ->with('seller')
    ->get();

        return view('admin.commissions', compact(
            'commissions',
            'sellers',
            'totalEarned',
            'totalPending',
            'totalPaid'
        ));
    }

    public function markPaid(Commission $commission)
    {
        $commission->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return back()->with('success', 'Commission marked as paid.');
    }
}


