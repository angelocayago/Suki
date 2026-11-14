# Buyer Suki Wallet foundation (W5A)

## Contract and rollout

All authoritative amounts are integer PHP centavos. No opening credit or top-up
exists. A Buyer without a materialized wallet/entries has a zero balance; reading
the page creates no account or ledger rows. Account rows are reserved for a future
verified posting path. The authenticated Buyer owns all page/history queries.

`config/suki_wallet.php` defaults every rollout gate to false:

- `enabled`: read-only Buyer page and Account dropdown entry.
- `refund_credit_enabled`: prerequisite only; cannot enable posting while the
  funding allocator is absent. The prepared service rejects attempts even if
  this flag is manually enabled.
- `checkout_enabled`: reserved for a future payment rollout. Checkout remains
  COD-only; the foundation service reports wallet checkout unavailable regardless
  of this flag until cancellation/reversal and prepaid accounting are implemented.

The existing Return/Refund activation configuration is unchanged. Earning release
remains independently disabled. No schema or activation deployment is performed
by this phase.

## Refund obligation versus funding

A new Seller approval retains the existing request status, history, validated
amount and exactly one Seller `REFUND_ADJUSTMENT`. If the new schema is deployed,
the same transaction also inserts one `buyer_refund_fundings` row:

- Primary/source identity: original `return_refund_request_id`, unique
  `BUYER-REFUND-RR-{id}`, and the unique existing Seller adjustment ID.
- Exact purchased-item allocation: merchandise, Seller and platform discounts,
  commission reversal, Seller adjustment and full Buyer refund amount.
- Initial status: `APPROVED_AWAITING_FUNDING`.
- Custody, reserve, verified recovery and total funded amounts: zero.
- Verifier, evidence source, verification time and consumption time: absent.

The schema also reserves `FUNDED_READY_TO_CREDIT` and
`CREDITED_TO_BUYER_WALLET`, but this phase provides no transition API or Admin
button for them. An actor, a reference or a manually changed status is not funding
evidence. `BuyerRefundFundingService::credit()` is deliberately non-posting.

For example, merchandise 20,000 minus Seller discount 101 and platform discount
201 means a Buyer obligation of 19,698. With commission 2,000 the existing Seller
adjustment is 17,899. Neither that adjustment nor the commission reversal proves
that 19,698 has been reserved for the Buyer.

Order PAID, Rider collection, accepted upstream COD, Seller available/held earnings
and recovery deficits do not by themselves prove an *unconsumed funding allocation*.
A future verifier must allocate custody/reserve/recovered funds, account for prior
payouts and shipping liabilities, and prevent their reuse across Buyers/payouts.
It must then atomically consume that allocation and post one Buyer credit, under
consistent Buyer/Seller/purchase locks. These guarantees are not yet implemented.

Existing refund accounting stays `REFUND_DUE`; the earning reconciler currently
expects that state. Any future discharge/credit-state integration must extend it
explicitly, not silently change statuses or count the Seller debit twice.

## History and compatibility

The ledger reserves signed `REFUND_CREDIT`, `ORDER_PAYMENT` and
`PAYMENT_REVERSAL` rows, with unique source identities, a single refund funding
reference, unique order/type pairs and a unique original-entry reversal link.
No public mutation route or financial posting method is exposed by the foundation.

There is no historical backfill. Terminal approval replay returns before the new
hook; historical Admin/financial approval paths are unchanged. Before migration,
the hook leaves the existing Seller approval behavior intact. Partial incompatible
wallet schema stops new funding recording rather than issuing money.

Seller Wallet presentation separates payout-eligible display, held earnings,
signed available ledger and recovery deficit. Calculations and payout authorization
remain in the existing Seller wallet service; presentation never clears a deficit.

The new migration must be reviewed/deployed separately. Do not enable the Buyer
page before migration, or credit/checkout gates before their remaining contracts
and real concurrency tests are complete. Rollback refuses existing wallet/funding
records. There is no external money transfer, Logistics, Chat or waybill change.

## Verification and remaining work

Verification used SQLite memory fixtures and the existing explicitly disposable
PostgreSQL 17.12 cluster at 127.0.0.1:55439, database `suki_return_refund_test`,
role `suki_rr_test`. No shared database was accessed. The new migration, all 12
foundation tests (190 assertions per engine), and 11 real two-process PostgreSQL
races (72 checks) passed. The races cover approval replay/conflicts, exactly one
pending funding record, no Buyer credit, request uniqueness and bounded lock waits.
They do not establish funded-credit or concurrent-spending safety.

Regression runs passed 209 tests / 3,633 assertions and 108 tests / 1,520
assertions; the final additional history fixture passed in both 12-test foundation
runs. Relevant JavaScript checks passed 35 tests. Changed Blade compilation,
generated PHP parsing, PHP syntax, route inspection, production build and diff
whitespace checks passed. Browser visual verification was not performed.

The history test seeds a clearly synthetic posted row to test display/privacy;
it is not evidence of verified funding or a functioning posting service.

Held-to-Available source review confirms existing release readiness requires its
own strict activation gate, authoritative purchase allocations and accepted
upstream remittance; active complaints/returns and unmatched refund obligations
block release. The existing signed adjustments and release ledger remain unchanged.
This release check is not an exclusive funding allocator for Buyer refunds.

Before any rollout, separately review/deploy only the additive migration with
appropriate lock timeouts and backups. The read-only page may then be separately
enabled. Keep credit, wallet checkout and earning releases disabled until the
trusted allocation/consumption and prepaid cancellation/reversal contracts have
their own approved implementation and PostgreSQL concurrency verification.
