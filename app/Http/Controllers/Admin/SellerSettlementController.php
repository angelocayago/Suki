<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerSettlement;
use App\Services\SellerSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SellerSettlementController extends Controller
{
    public function index(Request $request)
    {
        $allowedStatuses = ['eligible', 'approved', 'paid'];
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
        SellerSettlementService $service
    ) {
        DB::transaction(function () use ($settlement, $service) {
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
                    'settlement' => 'A payout record already exists.',
                ]);
            }

            $locked->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        }, 3);

        return back()->with('success', 'Settlement approved. No money has been transferred.');
    }

    public function recordPayout(
        Request $request,
        SellerSettlement $settlement,
        SellerSettlementService $service
    ) {
        $data = $request->validate([
            'payment_method' => ['required', 'string', 'max:50'],
            'transaction_reference' => ['required', 'string', 'max:150'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payout_verified' => ['required', 'accepted'],
        ]);

        DB::transaction(function () use ($settlement, $service, $data) {
            $locked = SellerSettlement::query()
                ->whereKey($settlement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'approved' || !$locked->approved_at) {
                throw ValidationException::withMessages([
                    'settlement' => 'Settlement must be approved before recording payout.',
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
                    'settlement' => 'Financial amounts have changed. Payout recording blocked.',
                ]);
            }

            if (DB::table('seller_settlement_payouts')
                ->where('seller_settlement_id', $locked->id)
                ->exists()
            ) {
                throw ValidationException::withMessages([
                    'settlement' => 'Payout has already been recorded.',
                ]);
            }

            $reference = trim($data['transaction_reference']);

            if (DB::table('seller_settlement_payouts')
                ->where('transaction_reference', $reference)
                ->exists()
            ) {
                throw ValidationException::withMessages([
                    'transaction_reference' => 'This transaction reference is already used.',
                ]);
            }

            $paidAt = \Illuminate\Support\Carbon::parse($data['paid_at']);

            DB::table('seller_settlement_payouts')->insert([
                'seller_settlement_id' => $locked->id,
                'amount_minor' => $locked->net_minor,
                'payment_method' => $data['payment_method'],
                'transaction_reference' => $reference,
                'paid_at' => $paidAt,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $locked->update([
                'status' => 'paid',
                'paid_at' => $paidAt,
                'payout_reference' => $reference,
            ]);
        }, 3);

        return back()->with('success', 'Verified payout recorded successfully.');
    }
}
