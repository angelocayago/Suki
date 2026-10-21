<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerSettlement;
use App\Services\SellerSettlementService;
use App\Services\SellerWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerSettlementController extends Controller
{
    public function index(Request $request)
    {
        $allowedStatuses = ['eligible', 'approved', 'wallet_credited', 'paid'];
        $status = $request->query('status', 'all');

        if ($status !== 'all' && !in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $query = SellerSettlement::query()->with([
            'seller',
            'sellerOrder.order',
        ]);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $settlements = $query->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'eligible_count' => SellerSettlement::where('status', 'eligible')->count(),
            'eligible_minor' => SellerSettlement::where('status', 'eligible')->sum('net_minor'),
            'approved_minor' => SellerSettlement::where('status', 'approved')->sum('net_minor'),
            'wallet_credited_minor' => SellerSettlement::where('status', 'wallet_credited')->sum('net_minor'),
            'paid_minor' => SellerSettlement::where('status', 'paid')->sum('net_minor'),
        ];

        $payouts = DB::table('seller_settlement_payouts')
            ->whereIn('seller_settlement_id', $settlements->getCollection()->pluck('id'))
            ->get()
            ->keyBy('seller_settlement_id');

        return view(
            'admin.seller-settlements.index',
            compact('settlements', 'stats', 'status', 'payouts')
        );
    }

    public function approve(
        SellerSettlement $settlement,
        SellerSettlementService $service,
        SellerWalletService $walletService
    ) {
        DB::transaction(function () use ($settlement, $service, $walletService) {
            $locked = SellerSettlement::query()
                ->whereKey($settlement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'eligible') {
                throw ValidationException::withMessages([
                    'settlement' => 'Only eligible settlements can be approved.',
                ]);
            }

            if (!$locked->sellerOrder) {
                throw ValidationException::withMessages([
                    'settlement' => 'Seller order is missing.',
                ]);
            }

            $assessment = $service->assess($locked->sellerOrder);

            if (!$assessment['eligible']) {
                throw ValidationException::withMessages([
                    'settlement' => implode(' ', $assessment['reasons']),
                ]);
            }

            if (
                (int) $locked->seller_id !== (int) $assessment['seller_id'] ||
                (int) $locked->subtotal_minor !== (int) $assessment['subtotal_minor'] ||
                (int) $locked->commission_minor !== (int) $assessment['commission_minor'] ||
                (int) $locked->net_minor !== (int) $assessment['net_minor'] ||
                (int) $locked->net_minor <= 0
            ) {
                throw ValidationException::withMessages([
                    'settlement' => 'Settlement amounts or seller have changed. Approval blocked.',
                ]);
            }

            if (DB::table('seller_settlement_payouts')
                ->where('seller_settlement_id', $locked->id)
                ->exists()
            ) {
                throw ValidationException::withMessages([
                    'settlement' => 'A legacy payout record already exists.',
                ]);
            }

            $locked->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Internal wallet credit only. This does not send GCash/bank funds.
            $walletService->creditApprovedSettlement($locked);
        }, 3);

        return back()->with(
            'success',
            'Settlement approved and credited to the seller wallet.'
        );
    }

    /**
     * Legacy direct-payout endpoint.
     *
     * New flow:
     * settlement approval -> seller wallet -> withdrawal request -> actual payout.
     */
    public function recordPayout(
        Request $request,
        SellerSettlement $settlement,
        SellerSettlementService $service
    ) {
        throw ValidationException::withMessages([
            'settlement' =>
                'Direct settlement payout is disabled. Approved funds are credited to the seller wallet and paid through seller withdrawal requests.',
        ]);
    }
}
