<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Commission;
use App\Models\CodRemittance;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class FinancialReconciliationController extends Controller
{
    public function index()
    {
        // Payment totals: count each payment once.
        $totalCollectedMinor = (int) Payment::query()
            ->where('status', 'paid')
            ->sum('amount_minor');

        // Count only confirmed COD remittance allocations.
        // DISTINCT payment_id protects against repeated counting.
        $confirmedPaymentIds = DB::table('cod_remittance_payments')
            ->join(
                'cod_remittances',
                'cod_remittances.id',
                '=',
                'cod_remittance_payments.cod_remittance_id'
            )
            ->where('cod_remittances.status', 'confirmed')
            ->distinct()
            ->pluck('cod_remittance_payments.payment_id');

        $confirmedCodMinor = (int) Payment::query()
            ->where('status', 'paid')
            ->whereIn('id', $confirmedPaymentIds)
            ->sum('amount_minor');

        // Existing commission records.
        $totalCommissionMinor = (int) Commission::sum('amount_minor');

        $pendingCommissionMinor = (int) Commission::query()
            ->where('status', 'pending')
            ->sum('amount_minor');

        $paidCommissionMinor = (int) Commission::query()
            ->where('status', 'paid')
            ->sum('amount_minor');

        $recentRemittances = CodRemittance::query()
            ->with(['rider', 'logistic'])
            ->latest()
            ->limit(15)
            ->get();

        $recentPayments = Payment::query()
            ->with(['order', 'shipment'])
            ->latest()
            ->limit(20)
            ->get();

        // Read-only order-level financial breakdown.
        // Uses stored amounts, preserving historical checkout fees.
        // Recent orders using their actual stored financial amounts.
        $recentOrders = Order::query()
            ->with('user')
            ->latest()
            ->limit(20)
            ->get();

        // Commission totals grouped by order.
        // No commissions() relationship is required.
        $commissionsByOrder = Commission::query()
            ->whereIn('order_id', $recentOrders->pluck('id'))
            ->selectRaw('order_id, SUM(amount_minor) AS commission_total_minor')
            ->groupBy('order_id')
            ->pluck('commission_total_minor', 'order_id');

        return view('admin.financial-reconciliation', compact(
            'totalCollectedMinor',
            'confirmedCodMinor',
            'totalCommissionMinor',
            'pendingCommissionMinor',
            'paidCommissionMinor',
            'recentRemittances',
            'recentPayments',
            'recentOrders',
            'commissionsByOrder'
        ));
    }
}
