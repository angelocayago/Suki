<?php

namespace App\Services;

use App\Models\SellerSettlement;
use App\Models\SellerWallet;
use App\Models\SellerWalletTransaction;
use App\Models\SellerWithdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerWalletService
{
    public function walletForSeller(int $sellerId): SellerWallet
    {
        return SellerWallet::firstOrCreate(
            ['seller_id' => $sellerId],
            [
                'available_balance_minor' => 0,
                'pending_balance_minor' => 0,
            ]
        );
    }

    /**
     * Credit one approved settlement exactly once.
     *
     * The unique (seller_settlement_id, type) index plus row locks make this
     * idempotent even when an approval request is retried.
     */
    public function creditApprovedSettlement(SellerSettlement $settlement): SellerWalletTransaction
    {
        return DB::transaction(function () use ($settlement) {
            $lockedSettlement = SellerSettlement::query()
                ->whereKey($settlement->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($lockedSettlement->status, ['approved', 'wallet_credited'], true)) {
                throw ValidationException::withMessages([
                    'settlement' => 'Only an approved settlement can be credited to a seller wallet.',
                ]);
            }

            if ((int) $lockedSettlement->net_minor <= 0) {
                throw ValidationException::withMessages([
                    'settlement' => 'Settlement net amount must be greater than zero.',
                ]);
            }

            $existing = SellerWalletTransaction::query()
                ->where('seller_settlement_id', $lockedSettlement->id)
                ->where('type', 'settlement_credit')
                ->first();

            if ($existing) {
                if ($lockedSettlement->status === 'approved') {
                    $lockedSettlement->update(['status' => 'wallet_credited']);
                }

                return $existing;
            }

            $wallet = $this->walletForSeller((int) $lockedSettlement->seller_id);

            $wallet = SellerWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newBalance = (int) $wallet->available_balance_minor
                + (int) $lockedSettlement->net_minor;

            $wallet->update([
                'available_balance_minor' => $newBalance,
            ]);

            $transaction = SellerWalletTransaction::create([
                'seller_wallet_id' => $wallet->id,
                'seller_settlement_id' => $lockedSettlement->id,
                'type' => 'settlement_credit',
                'amount_minor' => (int) $lockedSettlement->net_minor,
                'balance_after_minor' => $newBalance,
                'reference' => 'SETTLEMENT-' . $lockedSettlement->id,
                'description' => 'Seller settlement #' . $lockedSettlement->id . ' credited to wallet.',
                'meta' => [
                    'seller_order_id' => $lockedSettlement->seller_order_id,
                    'commission_minor' => (int) $lockedSettlement->commission_minor,
                    'subtotal_minor' => (int) $lockedSettlement->subtotal_minor,
                ],
            ]);

            $lockedSettlement->update([
                'status' => 'wallet_credited',
            ]);

            return $transaction;
        }, 3);
    }

    public function requestWithdrawal(
        int $sellerId,
        int $amountMinor,
        array $payoutDetails
    ): SellerWithdrawal {
        return DB::transaction(function () use ($sellerId, $amountMinor, $payoutDetails) {
            if ($amountMinor <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Withdrawal amount must be greater than zero.',
                ]);
            }

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

            $wallet = $this->walletForSeller($sellerId);

            $wallet = SellerWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (bool) config('seller_wallet.single_pending_withdrawal', true)
                && SellerWithdrawal::query()
                    ->where('seller_id', $sellerId)
                    ->where('status', 'pending')
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'withdrawal' =>
                        'You already have a pending withdrawal. Wait until it is paid or rejected before requesting another.',
                ]);
            }

            if ((int) $wallet->available_balance_minor < $amountMinor) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient available wallet balance.',
                ]);
            }

            $newAvailable = (int) $wallet->available_balance_minor - $amountMinor;
            $newPending = (int) $wallet->pending_balance_minor + $amountMinor;

            $wallet->update([
                'available_balance_minor' => $newAvailable,
                'pending_balance_minor' => $newPending,
            ]);

            $withdrawal = SellerWithdrawal::create([
                'seller_wallet_id' => $wallet->id,
                'seller_id' => $sellerId,
                'amount_minor' => $amountMinor,
                'payment_method' => $payoutDetails['payment_method'],
                'account_name' => $payoutDetails['account_name'],
                'account_number' => $payoutDetails['account_number'],
                'bank_name' => $payoutDetails['bank_name'] ?? null,
                'status' => 'pending',
                'seller_notes' => $payoutDetails['seller_notes'] ?? null,
                'requested_at' => now(),
            ]);

            SellerWalletTransaction::create([
                'seller_wallet_id' => $wallet->id,
                'seller_settlement_id' => null,
                'type' => 'withdrawal_hold',
                'amount_minor' => -$amountMinor,
                'balance_after_minor' => $newAvailable,
                'reference' => 'WITHDRAWAL-' . $withdrawal->id,
                'description' => 'Withdrawal request #' . $withdrawal->id . ' reserved from available balance.',
                'meta' => [
                    'withdrawal_id' => $withdrawal->id,
                ],
            ]);

            return $withdrawal;
        }, 3);
    }

    public function markWithdrawalPaid(
        SellerWithdrawal $withdrawal,
        int $adminUserId,
        string $transactionReference,
        ?string $adminNotes = null
    ): SellerWithdrawal {
        return DB::transaction(function () use (
            $withdrawal,
            $adminUserId,
            $transactionReference,
            $adminNotes
        ) {
            $locked = SellerWithdrawal::query()
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Only pending withdrawals can be marked paid.',
                ]);
            }

            $wallet = SellerWallet::query()
                ->whereKey($locked->seller_wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $wallet->pending_balance_minor < (int) $locked->amount_minor) {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Wallet pending balance is inconsistent.',
                ]);
            }

            $wallet->update([
                'pending_balance_minor' =>
                    (int) $wallet->pending_balance_minor - (int) $locked->amount_minor,
            ]);

            $locked->update([
                'status' => 'paid',
                'transaction_reference' => $transactionReference,
                'admin_notes' => $adminNotes,
                'processed_at' => now(),
                'processed_by' => $adminUserId,
            ]);

            SellerWalletTransaction::create([
                'seller_wallet_id' => $wallet->id,
                'seller_settlement_id' => null,
                'type' => 'withdrawal_paid',
                'amount_minor' => 0,
                'balance_after_minor' => (int) $wallet->available_balance_minor,
                'reference' => $transactionReference,
                'description' => 'Withdrawal #' . $locked->id . ' paid externally.',
                'meta' => [
                    'withdrawal_id' => $locked->id,
                    'amount_minor' => (int) $locked->amount_minor,
                    'payment_method' => $locked->payment_method,
                ],
            ]);

            return $locked->fresh();
        }, 3);
    }

    public function rejectWithdrawal(
        SellerWithdrawal $withdrawal,
        int $adminUserId,
        string $adminNotes
    ): SellerWithdrawal {
        return DB::transaction(function () use ($withdrawal, $adminUserId, $adminNotes) {
            $locked = SellerWithdrawal::query()
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Only pending withdrawals can be rejected.',
                ]);
            }

            $wallet = SellerWallet::query()
                ->whereKey($locked->seller_wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $wallet->pending_balance_minor < (int) $locked->amount_minor) {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Wallet pending balance is inconsistent.',
                ]);
            }

            $newAvailable = (int) $wallet->available_balance_minor
                + (int) $locked->amount_minor;

            $wallet->update([
                'available_balance_minor' => $newAvailable,
                'pending_balance_minor' =>
                    (int) $wallet->pending_balance_minor - (int) $locked->amount_minor,
            ]);

            $locked->update([
                'status' => 'rejected',
                'admin_notes' => $adminNotes,
                'processed_at' => now(),
                'processed_by' => $adminUserId,
            ]);

            SellerWalletTransaction::create([
                'seller_wallet_id' => $wallet->id,
                'seller_settlement_id' => null,
                'type' => 'withdrawal_reversal',
                'amount_minor' => (int) $locked->amount_minor,
                'balance_after_minor' => $newAvailable,
                'reference' => 'WITHDRAWAL-REJECTED-' . $locked->id,
                'description' => 'Rejected withdrawal #' . $locked->id . ' returned to available balance.',
                'meta' => [
                    'withdrawal_id' => $locked->id,
                ],
            ]);

            return $locked->fresh();
        }, 3);
    }
}
