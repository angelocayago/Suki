<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerWithdrawal;
use App\Services\SellerWalletService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerWithdrawalController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $allowed = ['all', 'pending', 'paid', 'rejected'];

        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }

        $query = SellerWithdrawal::query()
            ->with(['seller', 'wallet'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $withdrawals = $query->paginate(20)->withQueryString();

        $stats = [
            'pending_count' => SellerWithdrawal::where('status', 'pending')->count(),
            'pending_minor' => (int) SellerWithdrawal::where('status', 'pending')->sum('amount_minor'),
            'paid_minor' => (int) SellerWithdrawal::where('status', 'paid')->sum('amount_minor'),
        ];

        return view(
            'admin.seller-withdrawals.index',
            compact('withdrawals', 'status', 'stats')
        );
    }

    public function markPaid(
        Request $request,
        SellerWithdrawal $withdrawal,
        SellerWalletService $walletService
    ) {
        $data = $request->validate([
            'transaction_reference' => [
                'required',
                'string',
                'max:150',
                Rule::unique('seller_withdrawals', 'transaction_reference')
                    ->ignore($withdrawal->id),
            ],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
            'payout_verified' => ['required', 'accepted'],
        ]);

        $walletService->markWithdrawalPaid(
            $withdrawal,
            (int) auth()->id(),
            trim($data['transaction_reference']),
            $data['admin_notes'] ?? null
        );

        return back()->with('success', 'Seller withdrawal marked as paid.');
    }

    public function reject(
        Request $request,
        SellerWithdrawal $withdrawal,
        SellerWalletService $walletService
    ) {
        $data = $request->validate([
            'admin_notes' => ['required', 'string', 'max:2000'],
        ]);

        $walletService->rejectWithdrawal(
            $withdrawal,
            (int) auth()->id(),
            $data['admin_notes']
        );

        return back()->with(
            'success',
            'Withdrawal rejected and the reserved amount was returned to the seller wallet.'
        );
    }
}
