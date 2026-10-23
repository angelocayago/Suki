<?php

namespace App\Services;

use App\Models\RiderWallet;
use App\Models\RiderWalletTransaction;
use App\Models\RiderWithdrawal;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RiderWalletService
{
    public function walletForRider(int $riderUserId): RiderWallet
    {
        return RiderWallet::firstOrCreate(
            ['rider_id' => $riderUserId],
            [
                'available_balance_minor' => 0,
                'pending_balance_minor' => 0,
            ]
        );
    }

    public function resolveRiderUser(Shipment $shipment): ?User
    {
        if (empty($shipment->rider_id)) {
            return null;
        }

        return User::find((int) $shipment->rider_id);
    }

    public function sellerOrderForShipment(Shipment $shipment): ?SellerOrder
    {
        if (empty($shipment->seller_order_id)) {
            return null;
        }

        return SellerOrder::query()
            ->with('order')
            ->find((int) $shipment->seller_order_id);
    }

    public function orderIsConfirmedReceived(Shipment $shipment): bool
    {
        $sellerOrder = $this->sellerOrderForShipment($shipment);

        return (bool) ($sellerOrder?->order?->confirmed_received_at);
    }

    /**
     * Rider pay is funded ONLY by the shipping fee allocated to this
     * seller/shipment. It does not touch seller merchandise subtotal,
     * seller settlement net, or platform/admin commission.
     */
    public function calculateDeliveryEarningMinor(Shipment $shipment): int
    {
        $sellerOrder = $this->sellerOrderForShipment($shipment);

        if (!$sellerOrder) {
            return 0;
        }

        $shippingMinor = max(0, (int) ($sellerOrder->shipping_minor ?? 0));
        $configuredRiderPay = max(
            0,
            (int) config('rider_wallet.delivery_earning_minor', 5000)
        );

        // Rider cannot receive more than the shipping fee collected/allocated
        // for this parcel.
        return min($configuredRiderPay, $shippingMinor);
    }

    public function creditDeliveredShipment(Shipment $shipment): ?RiderWalletTransaction
    {
        if (strtolower((string) $shipment->status) !== 'delivered') {
            return null;
        }

        // Final money rule:
        // Delivered alone is not enough. Buyer must Confirm Received,
        // either manually or through the 3-day auto-confirm process.
        if (!$this->orderIsConfirmedReceived($shipment)) {
            return null;
        }

        $rider = $this->resolveRiderUser($shipment);

        if (!$rider) {
            return null;
        }

        $sellerOrder = $this->sellerOrderForShipment($shipment);

        if (!$sellerOrder) {
            return null;
        }

        $shippingMinor = max(0, (int) ($sellerOrder->shipping_minor ?? 0));
        $earningMinor = $this->calculateDeliveryEarningMinor($shipment);

        if ($earningMinor <= 0) {
            return null;
        }

        return DB::transaction(function () use (
            $shipment,
            $rider,
            $sellerOrder,
            $shippingMinor,
            $earningMinor
        ) {
            $existing = RiderWalletTransaction::query()
                ->where('shipment_id', $shipment->id)
                ->where('type', 'delivery_credit')
                ->first();

            if ($existing) {
                return $existing;
            }

            $wallet = $this->walletForRider((int) $rider->id);

            $wallet = RiderWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = RiderWalletTransaction::query()
                ->where('shipment_id', $shipment->id)
                ->where('type', 'delivery_credit')
                ->first();

            if ($existing) {
                return $existing;
            }

            $wallet->available_balance_minor =
                (int) $wallet->available_balance_minor + $earningMinor;

            $wallet->save();

            return RiderWalletTransaction::create([
                'rider_wallet_id' => $wallet->id,
                'shipment_id' => $shipment->id,
                'type' => 'delivery_credit',
                'amount_minor' => $earningMinor,
                'balance_after_minor' => (int) $wallet->available_balance_minor,
                'reference' => 'DELIVERY-' . $shipment->id,
                'description' => 'Rider delivery earning funded from shipping fee for shipment '
                    . ($shipment->tracking_number ?: ('#' . $shipment->id)) . '.',
                'meta' => [
                    'tracking_number' => $shipment->tracking_number,
                    'seller_order_id' => $sellerOrder->id,
                    'rider_user_id' => $rider->id,
                    'shipping_fee_minor' => $shippingMinor,
                    'rider_earning_minor' => $earningMinor,
                    'logistics_platform_remainder_minor' => max(
                        0,
                        $shippingMinor - $earningMinor
                    ),
                    'funding_source' => 'shipping_fee',
                ],
            ]);
        });
    }

    public function requestWithdrawal(
        int $riderUserId,
        int $amountMinor,
        array $data
    ): RiderWithdrawal {
        return DB::transaction(function () use ($riderUserId, $amountMinor, $data) {
            if ($amountMinor <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Withdrawal amount must be greater than zero.',
                ]);
            }

            $minimum = (int) config('rider_wallet.minimum_withdrawal_minor', 10000);

            if ($amountMinor < $minimum) {
                throw ValidationException::withMessages([
                    'amount' => 'Minimum withdrawal is ₱'
                        . number_format($minimum / 100, 2)
                        . '.',
                ]);
            }

            $wallet = $this->walletForRider($riderUserId);

            $wallet = RiderWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (bool) config('rider_wallet.single_pending_withdrawal', true)
                && RiderWithdrawal::query()
                    ->where('rider_id', $riderUserId)
                    ->where('status', 'pending')
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'withdrawal' =>
                        'You already have a pending withdrawal. Wait until it is paid or rejected.',
                ]);
            }

            if ((int) $wallet->available_balance_minor < $amountMinor) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient rider wallet balance.',
                ]);
            }

            $wallet->available_balance_minor -= $amountMinor;
            $wallet->pending_balance_minor += $amountMinor;
            $wallet->save();

            $withdrawal = RiderWithdrawal::create([
                'rider_id' => $riderUserId,
                'rider_wallet_id' => $wallet->id,
                'amount_minor' => $amountMinor,
                'payment_method' => $data['payment_method'],
                'account_name' => $data['account_name'],
                'account_number' => $data['account_number'],
                'bank_name' => $data['bank_name'] ?? null,
                'status' => 'pending',
                'rider_notes' => $data['rider_notes'] ?? null,
                'requested_at' => now(),
            ]);

            RiderWalletTransaction::create([
                'rider_wallet_id' => $wallet->id,
                'type' => 'withdrawal_reserved',
                'amount_minor' => -$amountMinor,
                'balance_after_minor' => (int) $wallet->available_balance_minor,
                'reference' => 'RIDER-WITHDRAWAL-' . $withdrawal->id,
                'description' => 'Rider withdrawal request #' . $withdrawal->id
                    . ' reserved from available balance.',
                'meta' => [
                    'withdrawal_id' => $withdrawal->id,
                    'amount_minor' => $amountMinor,
                ],
            ]);

            return $withdrawal;
        });
    }

    public function markWithdrawalPaid(
        RiderWithdrawal $withdrawal,
        string $reference,
        ?string $adminNotes,
        ?int $adminUserId
    ): RiderWithdrawal {
        return DB::transaction(function () use ($withdrawal, $reference, $adminNotes, $adminUserId) {
            $withdrawal = RiderWithdrawal::query()
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($withdrawal->status === 'paid') {
                return $withdrawal;
            }

            if ($withdrawal->status !== 'pending') {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Only pending withdrawals can be marked paid.',
                ]);
            }

            $wallet = RiderWallet::query()
                ->whereKey($withdrawal->rider_wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->pending_balance_minor = max(
                0,
                (int) $wallet->pending_balance_minor - (int) $withdrawal->amount_minor
            );
            $wallet->save();

            $withdrawal->update([
                'status' => 'paid',
                'transaction_reference' => $reference,
                'admin_notes' => $adminNotes,
                'processed_at' => now(),
                'processed_by' => $adminUserId,
            ]);

            RiderWalletTransaction::create([
                'rider_wallet_id' => $wallet->id,
                'type' => 'withdrawal_paid',
                'amount_minor' => 0,
                'balance_after_minor' => (int) $wallet->available_balance_minor,
                'reference' => $reference,
                'description' => 'Rider withdrawal #' . $withdrawal->id . ' paid externally.',
                'meta' => [
                    'withdrawal_id' => $withdrawal->id,
                    'amount_minor' => (int) $withdrawal->amount_minor,
                ],
            ]);

            return $withdrawal->fresh();
        });
    }

    public function rejectWithdrawal(
        RiderWithdrawal $withdrawal,
        ?string $adminNotes,
        ?int $adminUserId
    ): RiderWithdrawal {
        return DB::transaction(function () use ($withdrawal, $adminNotes, $adminUserId) {
            $withdrawal = RiderWithdrawal::query()
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($withdrawal->status !== 'pending') {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Only pending withdrawals can be rejected.',
                ]);
            }

            $wallet = RiderWallet::query()
                ->whereKey($withdrawal->rider_wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (int) $withdrawal->amount_minor;

            $wallet->pending_balance_minor = max(
                0,
                (int) $wallet->pending_balance_minor - $amount
            );
            $wallet->available_balance_minor += $amount;
            $wallet->save();

            $withdrawal->update([
                'status' => 'rejected',
                'admin_notes' => $adminNotes,
                'processed_at' => now(),
                'processed_by' => $adminUserId,
            ]);

            RiderWalletTransaction::create([
                'rider_wallet_id' => $wallet->id,
                'type' => 'withdrawal_reversal',
                'amount_minor' => $amount,
                'balance_after_minor' => (int) $wallet->available_balance_minor,
                'reference' => 'RIDER-WITHDRAWAL-' . $withdrawal->id,
                'description' => 'Rejected rider withdrawal #' . $withdrawal->id
                    . ' returned to available balance.',
                'meta' => [
                    'withdrawal_id' => $withdrawal->id,
                    'amount_minor' => $amount,
                ],
            ]);

            return $withdrawal->fresh();
        });
    }
}
