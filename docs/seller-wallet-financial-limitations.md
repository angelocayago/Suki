# Seller Wallet: accounting fixes and remaining financial limitations

The Wallet & Payouts UI uses the canonical `SellerWalletService::summary()`. History filters are read-only and never change the balances. The narrowly authorized accounting fix changes only that summary and payout amount conversion, plus regression tests and this documentation. Ledger writes, payout transitions, settlement, commissions, COD, refund allocation, routes, UI, and Admin processing handlers remain unchanged.

## Corrected: paid payouts became available again

Previously, `SellerWalletService::summary()` subtracted only `PAYOUT_RESERVATION` entries with status `RESERVED` from available earnings/refund adjustments. `markPaid()` changes that reservation to `SETTLED` and creates a positive `PAYOUT_COMPLETED` entry. The old summary counted the completed entry toward paid out but did not subtract it from available funds.

The pre-fix isolated SQLite-memory check reproduced:

| Stage | Available | Reserved | Paid out |
| --- | ---: | ---: | ---: |
| Opening earning | PHP 1,000.00 | PHP 0.00 | PHP 0.00 |
| Request PHP 200.00 | PHP 800.00 | PHP 200.00 | PHP 0.00 |
| Approve request | PHP 800.00 | PHP 200.00 | PHP 0.00 |
| Mark paid | PHP 1,000.00 | PHP 0.00 | PHP 200.00 |

These are controlled test fixtures, never production transactions or displayed sample data.

The corrected formula is:

`Available = AVAILABLE sale earnings + signed AVAILABLE refund adjustments - RESERVED payout reservations - valid completed payouts`.

The same PHP 200.00 payment now leaves PHP 800.00 available, PHP 0.00 reserved, and PHP 200.00 paid out. A later request cannot reuse those paid funds. No negative payout debit was added.

Completed deductions require a positive `PAYOUT_COMPLETED` / `POSTED` ledger entry matching a `PAID` payout's ID, Seller, and amount. Matching rows are grouped by payout ID, so duplicate matching historical rows deduct that disbursement only once. Non-posted, orphaned, foreign-Seller, amount-mismatched, and non-PAID rows do not qualify as valid completed deductions. This is read-only classification, not historical repair.

One SQL statement computes the summary with standard CASE aggregates and a scalar grouped subquery, avoiding mixed snapshots across separate aggregates. Held earnings, total earnings, active reservations, and signed refunds retain their previous definitions. The paid-out reporting total deliberately retains its existing raw `PAYOUT_COMPLETED` ledger sum; corrupt or duplicate history can therefore cause it to disagree with valid completed deductions.

## Historical records remain unverified

No shared production records were inspected or changed. Existing valid historical completions are automatically deducted by the corrected query, without backfills or additional debits. Missing ledger entries (including a PAID payout without a matching POSTED entry), duplicate rows, wrong classifications, and mismatched amounts require separate reconciliation. Excluding invalid rows does not establish that the remaining available balance is correct for an inconsistent historical ledger. Do not infer production reconciliation or recover past overpayments from this change.

## Request deduplication

The new confirmation disables repeated submissions in the current page. The existing service locks the Seller and reserves each accepted request, preventing spending more than the reported available balance. It has no request idempotency token: separate requests can still be accepted when sufficient available balance remains. No new backend deduplication behavior is claimed.

## Corrected: oversized amount input

The controller previously multiplied an unchecked peso integer by 100, causing a TypeError on overflow. It now validates decimal syntax (at most two fractional digits), constructs centavos as a string, removes leading zeros, rejects zero, and compares the string against `PHP_INT_MAX` before casting. No floating-point conversion or arbitrary business limit is introduced. Overflow and malformed inputs return Laravel amount validation errors without creating payouts or reservations. The service continues to enforce the current available balance after locking the Seller row.

## Separate risks remain outside this fix

- Durable request idempotency still requires a separately approved design; identical amounts are not treated as duplicate requests.
- Request Seller-row locks, review/payment payout-row locks, transaction boundaries, and retry counts are preserved. The single-statement summary does not establish full cross-operation serializability; real PostgreSQL concurrent request/payment/refund behavior remains unverified.
- `reconcileSellerOrder()` still does not recheck COD reconciliation when releasing a hold after complaint closure.
- New complaints/returns do not re-hold existing available earnings; return finalization does not explicitly reconcile an existing held earning.
- The migration's unique `(seller_id, seller_order_id, type)` constraint can conflict with multiple item-level refund adjustments for one order. Actual deployed constraints were not inspected.
- Refund adjustments can reduce funds after a reservation or payment. No new shortfall/disbursement policy is introduced.

## Verification boundaries

Wallet-page and Reports tests use explicitly guarded SQLite `:memory:` tables. Legacy wallet hardening tests use application migrations only in disposable SQLite memory storage. Regression coverage includes payout lifecycle snapshots, sequential paid payouts, rejected releases, historical completion classification/deduplication, signed refunds, held funds, integer boundaries, normal and invalid requests, Seller isolation, and Reports integration. Historical sales metrics and CSV output must remain unchanged by payouts. No migrations, payout requests, or financial writes were performed against shared Supabase. No schema changes or historical corrections are included.
