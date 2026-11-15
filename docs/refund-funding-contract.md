# W5F: obligation validation, not funded credit

The read-only Buyer Wallet page remains enabled. Refund-credit and checkout gates
remain false, and earning releases remain false. No new schema, funding source,
posting endpoint, reserve, production data reconciliation or deployment is added.

## Accounting contract

An approved request, exact purchased-item allocations, request-scoped Seller
REFUND_ADJUSTMENT, REFUND_DUE accounting, and pending Buyer funding record describe
an obligation. They do not describe spendable cash. All authoritative amounts use
integer centavos through the existing ReturnRefundAllocationService.

For a 10,000-centavo Buyer refund with a 9,000 Seller adjustment and 1,000 commission
reversal, 10,000 centavos of independently verified, unencumbered custody must be
exclusively allocated. Adding those two accounting components is not verification.
Discounted obligations can differ further: 20,000 merchandise - 101 Seller-funded
discount - 201 platform-funded discount = 19,698 Buyer refund, whereas a 2,000
commission gives a 17,899 Seller adjustment. Seller deficits remain signed liabilities.

CodRemittanceService records collection, provider acceptance and Admin upstream
acceptance. Its unique receipt/batch linkage prevents resubmitting a receipt;
it does not prevent spending the accepted funds on another liability. Payment
status/reference alone adds no exclusive source balance. MoneyReportService is
a projection, not a custody reservation ledger. Commission reversal is accounting,
not a funded platform reserve.

SellerWalletService reserves payouts against the Seller's signed ledger under a
Seller lock. It does not consume custody allocations tied to refund funding.
Held releases require verified purchase/settlement evidence and retained refund
adjustments; they remain disabled. Accepted COD may also include shipping/rider
liabilities, discounts, historical payouts and other obligations. No source currently
reconciles and exclusively allocates those commitments across refunds and payouts.

Therefore the **verified allocatable amount reported by this implementation is zero**.
This is not a claim that platform cash custody is zero; its unencumbered portion is
not provable by the current contract. No accepted remittance, balance, user-entered
reference, manual READY status or commission reversal authorizes posting.

## Implemented safe interfaces

BuyerRefundFundingService::assess re-reads the active Admin, locks Seller before
parent purchase, SellerOrder, item, request, existing adjustment/accounting and
funding, and validates ownership, approval/reviewer, full purchased quantity,
integer components, REFUND_DUE, pending state and absence of a Buyer credit. It
returns a read-only MISSING_EXCLUSIVE_FUNDING_SOURCE assessment, never a reservation.
It never materializes a Buyer wallet or backfills old requests.

allocate validates the obligation and rejects allocation. credit validates the
same obligation and rejects posting, even if its rollout flag is manually set.
There is no HTTP funding/credit endpoint. Repeated refusals leave every ledger and
funding row unchanged. These are not successful allocation/credit idempotency guarantees.
There are **no implemented transitions to READY or CREDITED** in W5F.

New Seller approvals retain their existing atomic single adjustment/pending funding
behavior. Approval arithmetic, payout rules, REFUND_DUE and earning reconciliation
are unchanged. Invalid or incomplete records are rejected, not repaired silently.

## Historical approvals and presentation

The Buyer page shows owned, approved REFUND_DUE requests lacking funding records
in a separate read-only section. It labels them "not credited here" and excludes
them from balances. This can represent an old 299-peso approval without creating
funding evidence, guessing its settlement history, or assuming its approval date.
No shared database was queried to locate or validate the specific historical record.
Any financial reconciliation or backfill still needs separate authorization.

New and historical lists are independently paginated, Buyer-scoped and escaped.
Funding labels distinguish Awaiting Funding, Processing Wallet Credit and Credited.
Existing posted history keeps the return link, owned original order reference and
Manila timestamp. Synthetic display fixtures are not proof of real funding/posting.
The page reports a refund as credited only when its funding record has a matching
Buyer-owned POSTED REFUND_CREDIT entry for the full amount. A status field alone
cannot produce that success label.

## Narrow next implementation phase

1. Establish an authoritative custody receipt/reconciliation source and exact
   ownership/amount/currency/integrity guarantees. Approve the funding policy for
   shipping, discounts, commission, prior payouts and recovery; do not infer it.
2. Add secured source/commitment/consumption records with unique source identities,
   restricted foreign keys, integer bounds, W5C RLS/revocations and atomic migration.
   Source availability must subtract *all* outstanding liabilities, refund allocations,
   payout reservations and prior consumptions. A unique reference alone is insufficient.
3. Make refund allocation, payout reservation/payment and earning/recovery changes
   use the same source locks and conservation invariant. Review legacy liabilities
   explicitly before declaring an opening unencumbered balance.
4. In one credit transaction, revalidate authority and purchase ownership, lock the
   exclusive allocation and Buyer wallet, consume once, insert one REFUND_CREDIT and
   mark funding CREDITED. Replay must return the existing result. Extend the existing
   REFUND_DUE/release contract explicitly before discharging obligations.
5. Verify different-refund/source and payout races, duplicate credit, rollback and
   double-consumption on disposable PostgreSQL before enabling any posting gate.

Future wallet checkout needs a unique purchase/debit identity, server-authoritative
selected totals, a locked Buyer ledger balance, atomic stock/voucher/order/payment
creation and debit, and exactly-once failed/cancelled-order reversal. Partial Seller
cancellations must reconcile the parent payment. Prepaid shipments must never enter
COD collection. None of this spending/reversal path is implemented here.

## Test interpretation

Applicable: existing single Seller adjustment/pending obligation, safe allocation
refusal, accepted-COD rejection, authorization, tampered ownership/components/state,
no backfill, no mutation on retry, Buyer privacy, existing COD/payout/waybill,
earning gates, PostgreSQL security, and concurrent approval/refusal behavior.

Not applicable because posting is not implemented: verified-source allocation,
cross-refund successful source consumption, successful refund-vs-payout funding,
exactly-once positive Buyer credit, concurrent positive credits, and posting-failure
rollback after a credit/consumption write. Do not report these as passed.

W5F verification: isolated SQLite regression runs passed 179 tests / 2,692
assertions and 75 tests / 1,038 assertions. The PostgreSQL 17.12 wallet suite passed
17 tests / 262 assertions. Real two-process PostgreSQL races passed 12 scenarios /
78 checks, including simultaneous allocation/credit refusals. The existing wallet
privilege/RLS suite passed 131 checks. Relevant JavaScript tests passed 35 tests;
Blade/generated PHP parsing, PHP syntax, route inspection, Vite build and diff
whitespace checks passed. PostgreSQL used only the pinned disposable loopback
database. No shared Supabase connection or writes were performed in W5F. Browser
visual testing was not performed.
