<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Services\SellerWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    public function index(SellerWalletService $walletService)
    {
        $seller = $this->currentSeller();
        $wallet = $walletService->walletForSeller((int) $seller->id);

        $transactions = $wallet->transactions()
            ->latest()
            ->paginate(20);

        $withdrawals = $wallet->withdrawals()
            ->latest()
            ->limit(10)
            ->get();

        $totalEarnedMinor = (int) $wallet->transactions()
            ->where('type', 'settlement_credit')
            ->sum('amount_minor');

        $totalWithdrawnMinor = (int) $wallet->withdrawals()
            ->where('status', 'paid')
            ->sum('amount_minor');

        $hasPendingWithdrawal = $wallet->withdrawals()
            ->where('status', 'pending')
            ->exists();

        $minimumWithdrawalMinor = (int) config(
            'seller_wallet.minimum_withdrawal_minor',
            10000
        );

        return view('seller.wallet.index', compact(
            'seller',
            'wallet',
            'transactions',
            'withdrawals',
            'totalEarnedMinor',
            'totalWithdrawnMinor',
            'hasPendingWithdrawal',
            'minimumWithdrawalMinor'
        ));
    }

    public function withdraw(Request $request, SellerWalletService $walletService)
    {
        $seller = $this->currentSeller();

        $data = $request->validate([
            'amount' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'payment_method' => ['required', 'in:gcash,maya,bank_transfer'],
            'account_name' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'max:150'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'seller_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['payment_method'] === 'bank_transfer' && empty($data['bank_name'])) {
            throw ValidationException::withMessages([
                'bank_name' => 'Bank name is required for bank transfer.',
            ]);
        }

        $amountMinor = $this->pesosToMinor($data['amount']);
        $minimumWithdrawalMinor = (int) config(
            'seller_wallet.minimum_withdrawal_minor',
            10000
        );

        if ($amountMinor < $minimumWithdrawalMinor) {
            throw ValidationException::withMessages([
                'amount' => 'Minimum withdrawal is ₱'
                    . number_format($minimumWithdrawalMinor / 100, 2)
                    . '.',
            ]);
        }

        $walletService->requestWithdrawal(
            (int) $seller->id,
            $amountMinor,
            $data
        );

        return back()->with(
            'success',
            'Withdrawal request submitted. The amount is reserved while admin processes the payout.'
        );
    }

    private function currentSeller(): Seller
    {
        $seller = Seller::query()
            ->where('user_id', Auth::id())
            ->first();

        abort_unless($seller, 403, 'Seller profile not found.');

        return $seller;
    }

    private function pesosToMinor(string $value): int
    {
        $value = trim(str_replace(',', '', $value));

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                'amount' => 'Enter a valid amount with up to 2 decimal places.',
            ]);
        }

        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '');
        $decimal = str_pad($decimal, 2, '0');

        return ((int) $whole * 100) + (int) substr($decimal, 0, 2);
    }
}
