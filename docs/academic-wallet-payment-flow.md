# Academic Suki Wallet payment and earning flow

The complete functional flow is implemented and verified in disposable SQLite and PostgreSQL 17.12. The PostgreSQL adapters preserve the functional assertions and add client/RLS checks and real concurrent-process scenarios. No shared Supabase connection, migration, record or setting was changed in this phase.

## Working behavior

1. The preserved academic approval integration calls the existing W5J-B credit path for a new, eligible, fully and exclusively funded simulation refund. Seller adjustment and credit remain once-only. Partial funding stays Approved — Awaiting Funding; historical approvals are not backfilled.
2. Native cart/Buy Now checkout can select Suki Wallet when its flags and installed protections permit the purchase. The locked, server-priced order includes shipping, inventory, commission and voucher allocations. One immutable payment consumes exact links to existing refund backings; no new value is issued.
3. One SellerOrder and an initial HELD SALE_EARNING are created atomically with the order, ORDER_PAYMENT and native Payment. Shipment COD is zero, no collection/remittance is created, and the Rider UI already hides its COD checkbox. The wallet delivery success message now also explicitly says no COD collection.
4. Whole-order Buyer cancellation before processing reverses the exact original payment once, restores stock through the native cancellation service, voids that order's held earning and restores only its backing allocations. Reversed earnings do not inflate lifetime earned totals. Native voucher redemption/usage history is retained, matching existing COD cancellation policy.
5. Native delivery proof upload/validation, shipping-liability creation and Buyer receipt confirmation establish completion evidence. Eligible wallet settlement then appends one EARNING_RELEASE from the original HELD entry and an immutable settlement proof. Shipping and commission claims stay protected. Existing deficits, holds and payout reservations remain in the native Seller Wallet projection.

Checkout requires a stable native checkout reference. A committed retry returns the same owned wallet order; missing identity, insufficient all-in balance, unsupported cohort or a failed capture leaves no debit, new order or inventory loss. Payment and reversal entries are shown with existing Buyer ownership filters, PHP currency and Manila date formatting.

## Actual successful scenario on both database engines

| Saved value | Minor units | PHP |
| --- | ---: | ---: |
| Original exclusively backed refund credit | 19,698 | 196.98 |
| New item's saved merchandise price | 5,000 | 50.00 |
| New order's saved shipping | 5,000 | 50.00 |
| Exact ORDER_PAYMENT | 10,000 debit | 100.00 |
| Buyer balance after payment | 9,698 | 96.98 |
| New Seller net, initially HELD | 4,500 | 45.00 |
| Same Seller net AVAILABLE after eligible completion | 4,500 | 45.00 |
| Platform commission claim | 500 | 5.00 |
| Protected shipping/Rider claim | 5,000 | 50.00 |

These numbers come from the saved native order/item values and their existing commission calculation. The service does not hardcode a 45.00 Seller earning. A separate Seller/platform voucher test pays 96.98 total and conserves 43.99 Seller + 2.99 platform + 50.00 shipping, leaving 100.00 in the Buyer Wallet.

Cancellation of the untouched 100.00 purchase restores the Buyer to 196.98, stock from 9 to 10 and Seller held/available/lifetime earned for that cancelled purchase to zero. A second reversal has no effect. Completed, delivered/processing or settled purchases cannot use this cancellation path. An untouched cancelled shipment retains the valid legacy `unassigned` status and `PLACED` workflow; its parent Order and SellerOrder carry cancellation. The PostgreSQL shipment status constraint was preserved.

## Scope and protections

The cohort supports one shop, the current saved 50.00 shipping fee and a positive Seller earning. It excludes source-enrolled simulation Sellers as new destinations because their original scenario retains its exclusive one-order accounting domain. Multi-shop carts, zero-net Seller orders, unusual shipping and platform discounts requiring an extra subsidy are explicitly unavailable in the preview and rejected by the service. Partial cancellation and post-fulfillment wallet refunds/recovery are not implemented; those require support/review, not a fabricated credit or COD receipt.

`2026_11_15_000005_create_academic_wallet_payments.php` is the **only new migration**. It requires all five reviewed prerequisite migration records and refuses to adopt historical wallet orders automatically. Its four additive tables hold immutable payments, exact original-backing allocations, whole reversals and earning settlements. It preserves the original refund-credit guard body and adds only the coordinated payment/reversal branch, checked by the new graph. Backings and credits remain immutable.

PostgreSQL uses initially deferred conservation/ownership triggers, immediate append-only/history guards, RLS without client policies, revoked table/column/sequence/function privileges and inherited-client-access checks. These protections were executed and verified in the disposable database, including actual denied reads under `anon` and `authenticated`. SQLite applies the same graph at coordination close. Connection-local SQLite guard functions fail closed when absent or invalid; registration in the application provider and migration lets existing native table-rebuild/financial-schema compatibility tests keep working without removing protections.

The shared financial mutex serializes wallet charges, reversals and releases with existing simulation/refund operations. Checkout additionally locks the Buyer, wallet and original backings. Each allocation can consume only the original Buyer's posted backing; active allocations cannot exceed it. The order-specific claim partitions the debit exactly across Seller, platform and shipping. Reversal restores an allocation by appending its linked reversal, never by rewriting consumption history.

All posting gates remain disabled in source: `suki_wallet.refund_credit_enabled=false`, `suki_wallet.checkout_enabled=false`, `simulation_settlement.enabled=false`, `max_scenario_minor=0`, `returns.earning_release_enabled=false`, and the preserved `academic_wallet.seller_approval_credit_enabled=false`. Existing read-only wallet/admin-review settings retain their previous values. Isolated fixtures explicitly enable the needed flags only against marked synthetic databases.

## Verification evidence

- Final disposable SQLite regression run: **251 tests / 4,101 assertions passed**. It covered the refund/settlement contracts, wallet payments, cart/Buy Now/reconfirmation, native COD, Rider/Logistics, waybill, earning releases/payouts and Chat attachments/private storage. PHP process limits were memory_limit=512M, upload_max_filesize=8M and post_max_size=32M; application checks were not relaxed.
- A targeted wallet/cart lifecycle/selective-checkout run also passed 38 tests / 583 assertions. The final combined regression result is retained at `C:\suki ulet\academic-wallet-complete\final-sqlite-results.xml`.
- Two real concurrent PHP processes were released at a barrier for identical checkout references and for competing purchases whose combined total exceeded the backed balance. Each case committed one payment, rejected the competing SQLite writer with a lock error, retained a 96.98 balance and passed the contract. Retrying the same reference returned its existing order; retrying the competing purchase failed sufficiency checks. This does not claim every concurrent SQLite request succeeds without retry.
- Cancellation/release replays, missing proof, active complaint and disabled release gates, failed capture/reversal rollback, immutable history, raw unallocated debits, voucher/commission allocation, insufficient shipping-inclusive balance, Buyer privacy and Buyer/Seller page rendering are covered by the new tests.
- `end-to-end-results.json` records an actual isolated run through native proof upload and receipt confirmation: one payment, one settlement, no wallet-order COD remittance and no duplicate release.
- Final disposable PostgreSQL run: **24 tests / 309 assertions passed**, recorded at `C:\suki ulet\academic-wallet-complete\postgres-results.xml`. The suite covers the same 8 approval and 14 payment cases plus 2 PostgreSQL security cases. Local-only `anon` and `authenticated` fixtures received broad default privileges before migrations; the migration's scoped revocations still denied protected reads and function access. Native `orders` remained readable in that privilege fixture, proving the test did not simply revoke everything.
- Real PostgreSQL workers raced from a barrier: identical checkout references both returned order 39 with only one debit; competing 100.00 purchases against 196.98 committed one and rejected the other for insufficient funds; simultaneous cancellation appended one reversal and restored 196.98; simultaneous eligible release appended one settlement and made exactly 45.00 AVAILABLE. Each case passed the conservation contract. Evidence is in `pg-race-same` and `pg-race-spend` under the external test directory.

## Prepared browser demonstration

A NEW fixture is prepared at `C:\suki ulet\academic-wallet-complete\browser\database.sqlite`. Earlier `academic-wallet` demo/test files were preserved. The new browser fixture contains a submitted refund, not a preinserted Buyer credit. Its local wrapper forces that SQLite database, isolated file sessions/storage/cache, virtual runtime gates and fake outbound services. No `.env` change or public arbitrary top-up is involved.

1. Start the isolated browser router manually:

   ```powershell
   php -S 127.0.0.1:8013 -t 'C:\suki ulet\Suki-group-work\public' 'C:\suki ulet\academic-wallet-complete\router.php'
   ```

2. Read the generated synthetic accounts locally from `C:\suki ulet\academic-wallet-complete\browser\accounts.txt`. Do not publish credentials or use these test accounts on Supabase.
3. Sign in at `/login` as `refund.seller`. Open `/seller/return-refunds/1`, approve with a reason. In a separate browser profile sign in as `buyer`; `/suki-wallet` now shows one 196.98 Refund Credit.
4. Open `/cart`, select the prepared 50.00 item and proceed through native Checkout using the prepared address. Choose Suki Wallet. The displayed all-in total is 100.00. Place the order, then verify Suki Wallet — Paid and one Order Payment, with 96.98 remaining.
5. In a third profile sign in as `payment.seller`. Open the native Seller Wallet: the order's 45.00 earning is HELD, unavailable for payout.
6. For this synthetic demonstration only, prepare the fulfillment/delivery fixture:

   ```powershell
   php 'C:\suki ulet\academic-wallet-complete\deliver-demo.php'
   ```

   The helper refuses other databases/cases and uses synthetic out-for-delivery assignment followed by the actual DeliveryCompletionService, assigned Rider checks, generated private proof photo and shipping-fee service. It does not fabricate COD or claim real-world delivery. Full role-by-role transport assignment remains covered by the unchanged native workflow regressions.
7. As Buyer, use **My Purchases → Confirm receipt**. Refresh the payment Seller's Wallet: the 45.00 becomes AVAILABLE once. Reopening receipt/reconciliation cannot issue a second release. Shipping remains protected.
8. To demonstrate reversal instead, cancel the wallet order before step 6. It must still be PLACED and wholly unprocessed. Verify 196.98 restored and one Payment Reversal. Re-add the item through its product page to create a new checkout if continuing the completion demonstration. Partial/processed/completed cancellations are rejected.
9. Stop the router with Ctrl+C. It was not automatically launched by the agent. Fixture preparation refuses existing databases rather than resetting them.

## PostgreSQL isolation and deployment requirements

After the operator reauthorized the accidentally declined startup, verification used the marked local PostgreSQL 17.12 cluster at `127.0.0.1:55439`. The creator checked fixed host/port/role/data-directory identity and refused existing databases. Repository migrations ran only in NEW `academic_wallet_complete_verified`; concurrency cases used separate new clones `academic_wallet_concurrent_same` and `academic_wallet_concurrent_spend`. Adapters retain assertions, change only isolation/bootstrap/transaction lifecycle and injected-failure SQL, and force deferred constraints immediate before rollback. The original failed disposable test database and earlier backup artifacts were preserved. The test cluster was stopped after verification, and `pg_ctl status` confirmed no server running.

All six financial migrations remain unapplied by this task to shared Supabase: the five reviewed files are unchanged, and the additive sixth needs review and explicit deployment approval. Production rollout and financial-gate activation are separate, unperformed actions. The earlier five-path deployment wrapper does not whitelist the new migration and was not silently extended. The implementation is ready for the isolated browser demonstration; shared-environment use still requires its scoped deployment preflight and approval.

## Exact repository file inventory

New in this phase:

- `app/Services/AcademicWalletPaymentContract.php`
- `app/Services/AcademicWalletPaymentService.php`
- `database/migrations/2026_11_15_000005_create_academic_wallet_payments.php`
- `tests/Feature/AcademicWalletPaymentTest.php`
- `docs/academic-wallet-payment-flow.md`

Existing files extended in this phase:

- `app/Http/Controllers/Buyer/CheckoutController.php`
- `app/Http/Controllers/Rider/FulfillmentController.php` (wallet delivery message only)
- `app/Providers/AppServiceProvider.php` (SQLite guard registration only)
- `app/Services/BuyerWalletService.php`
- `app/Services/CheckoutService.php`
- `app/Services/OrderCancellationService.php`
- `app/Services/OrderWorkflowService.php`
- `app/Services/SellerWalletService.php`
- `app/Services/SimulationSettlementService.php`
- `resources/views/buyer/checkout.blade.php`
- `resources/views/buyer/order-details.blade.php`
- `resources/views/buyer/order-success.blade.php`
- `resources/views/buyer/wallet/index.blade.php`
- `tests/Feature/AcademicWalletApprovalTest.php` (the missing-identity rejection/snapshot assertions remain; availability now reflects the implemented gated contract)
- `docs/academic-wallet-demo.md` (a superseding-guide note; historical report retained)

Preserved prior uncommitted work also includes `app/Http/Controllers/Buyer/WalletController.php`, `app/Models/BuyerRefundFunding.php` and `config/academic_wallet.php`. Those files were not replaced. The existing five migration files, `.env`, certificates, default financial gate files, Chat, Product and Seller Registration code were not changed.

Branch/HEAD remain `skeleton` / `597f48828fec4378c7332b1dcc41b3e44371c10a`. All changes remain unstaged and uncommitted. No stage, commit, push, fetch, pull, merge, reset, restore, stash, clean or branch switch occurred.

**ACADEMIC SUKI WALLET END-TO-END FLOW VERIFIED IN ISOLATION**
