# W5G: accepted-COD registry with an availability firewall

## Implemented scope

The new `custody_sources` registry records one validated internal accepted-COD
source per upstream remittance item, receipt, shipment and SellerOrder. This is
an academic accounting observation, **not a bank deposit, funded reserve or proof
of unencumbered money**. Registration is an explicit active-Admin service call;
there is no route, command, observer, automatic import or production execution.

The existing seven W5F files are preserved. Existing remittance, payout, refund,
earning-release and checkout behavior is unchanged. Only ReturnRefundService gains
a read-only collection/purchase snapshot method reusing its existing validation.

## Source trace and financial meaning

| Existing record/service | What it establishes | What it does not establish |
|---|---|---|
| Order / SellerOrder / purchased items | Parent identity, parcel ownership, integer price/discount/commission snapshots | Settled cash or source-level payment discharge |
| Shipment + COD_COLLECTED event | Recorded collection, amount, assigned Rider/provider and delivery evidence | Platform bank custody or available reserve |
| RiderCodRemittance | Recorded Rider-to-provider acceptance and amounts | Platform receipt or refund allocation |
| LogisticsCodRemittanceItem / accepted batch | Unique receipt-to-upstream link, matching batch totals, Admin acceptance audit | Unencumbered balance after all liabilities |
| RiderShippingFeeEarning | Internal 5,000-centavo delivery-fee entitlement | Actual Rider payout/discharge; no Rider payout ledger exists |
| SellerWalletService | Signed earnings, holds, request-scoped refund adjustments, payout reservations and payments | Which custody source funded each payout or recovery |
| ReturnRefundAllocationService | Exact full-quantity Buyer obligation, Seller debit, commission and discount components | Verified cash backing for those obligations |
| BuyerRefundFundingService | W5F obligation assessment and safe refusal | A positive exclusive allocation or consumed credit |
| MoneyReportService | Read-only aggregate custody/earnings/commission projections | A spendable source balance or reservation engine |

CustodySourceService re-reads Admin authority and locks Seller, parent, SellerOrder,
shipment/items and remittance records in the existing financial order. It requires
both accepted settlement stages, corresponding acceptance audits, purchase/variant
ownership, collection evidence, discounts, shipping, matching receipt amounts and
reconciled batch totals. User-entered bank/reference strings are not evidence.
The original accepted amount, actor/time, IDs, component snapshot and provenance
hash are retained. Retry returns the same record; altered evidence is rejected.

These checks rely on existing authenticated application records. They are not
independent bank verification and do not repair or strengthen unrelated legacy
table permissions. Newly created registry objects receive W5C protections.

## Conservation boundary

The desired shared equation is:

    verified source - consumed allocations - outstanding commitments
      - unpaid shipping/Rider liabilities - other attributable obligations
      = exclusively allocatable source

Paid payouts are consumption; outstanding payout reservations are commitments.
Do not deduct both a settled reservation and its completed payout. An approved
refund's Seller adjustment is an obligation/recovery entry, not new cash. Commission
reversal can reclassify a liability but cannot create custody. Existing paid payouts,
platform-funded discounts and recovery lack complete source attribution.

The current application cannot prove every term. The registry therefore enforces:

    accepted_minor = unreconciled_minor
    allocatable_minor = committed_minor = consumed_minor = 0

`unreconciled_minor` excludes the entire observation from availability. It is not
a fabricated reserve or a claim that actual liabilities equal that amount. No
opening zero-liability assumption is made, even if no payout row is found.
This is an **availability firewall**, not a positive shared conservation ledger.
Positive refund allocation, payout commitment and consumption tables/services are
deliberately not created until their attribution and coexistence contract is settled.

## Integration stopped at the unsafe dependency

Seller payout request/review/payment lock the Seller and reserve/deduct its existing
wallet projection. They do not reference custody sources. Held earning releases
also lock Seller before purchase, require accepted settlement and preserve refund
adjustments, but do not reconcile source-level obligations. Normal writers on older
application versions would bypass any new positive allocation ledger.

Changing those paths without historical attribution and compatible-writer rollout
would silently change eligibility or permit double commitments. No such changes
are made. Registration never makes money available, so it can coexist with current
payouts without claiming to reserve their source. No second Seller debit or deficit
clearance occurs. `REFUND_DUE` remains unchanged. Buyer credits, wallet checkout and
earning release flags remain disabled; the read-only wallet page remains enabled.

## Additive schema and security

`2026_11_15_000001_create_unreconciled_custody_sources.php` creates only the registry,
its sequence, indexes, restrictive FKs, unique identities and fail-closed monetary
shape. Currency is PHP and the sole state is UNRECONCILED. SQLite enforces integer
minor units with triggers; PostgreSQL uses bigint/CHECK constraints. New object
creation, constraints and security share a transaction.

PostgreSQL revokes table/column/sequence privileges from PUBLIC, anon and
authenticated, enables RLS without policies, rejects inherited privileged access,
and retains the Laravel owner. Global defaults and existing objects are untouched.
Rollback refuses recorded provenance. No shared database migration is performed.

## Required next reconciliation phase

1. Establish trusted custody/settlement provenance and an explicit simulation policy.
   Review legacy remittances, external/manual payments and already paid payouts.
2. Attribute each outstanding/paid payout, Seller earning/recovery, shipping/Rider
   entitlement/discharge, platform discount and refund to specific source amounts.
   Unattributable legacy sources must stay unavailable. The historical 299-peso
   refund retains its adjustment/display and receives no automatic allocation.
3. Define shared commitment identities and transitions. Replace an original payable
   commitment with a refund commitment atomically; never count both or mint a new
   source from a debit. Approve how commission and discount shortfalls are funded.
4. Integrate every payout request/review/payment and earning/recovery writer with
   the same source locks and guarded conservation invariant. Arrange compatible
   application rollout before enabling any positive source state.
5. Only then add source commitments/consumptions and atomic Buyer credit, with
   source, refund and ledger uniqueness, rollback and real PostgreSQL race tests.

Future wallet spending requires a unique debit/purchase intent, a locked Buyer
ledger balance, exact parent/per-Seller and fee allocations, atomic inventory and
voucher/order/payment creation, non-COD shipment routing and exactly-once partial
or full cancellation reversals. Legitimate credits must not be counted as both free
platform custody and Buyer wallet liabilities. No spending path is added here.

## Test interpretation

Applicable: source registration/identity, invalid/missing evidence, exact component
snapshots, zero capacity despite historical payouts/refunds, retry, rollback,
concurrent registration, unchanged Seller accounting/payout behavior, privacy,
source RLS/permission denial and disabled financial gates.

Not applicable: positive unencumbered balance; competing successful refund/payout
allocations; successful allocation retry; consumption exactly once; positive Buyer
credit or its posting rollback. Those services do not exist and must not be
reported as passed. Concurrent registration is not concurrent funded allocation.

## W5G verification results

- SQLite regression run: 259 tests, 3,806 assertions passed (including existing
  Seller payout/recovery, refund, COD/checkout, Buyer privacy and waybill tests).
- Disposable PostgreSQL 17.12 wallet/custody run: 22 tests, 338 assertions passed.
- Real two-process PostgreSQL concurrency: 14 races, 87 checks passed, including
  one-identity source registration and source replay alongside an existing payout
  reservation. Source availability remained zero throughout.
- New custody permissions/RLS/migration-rollback suite: 57 checks passed.
- Existing Buyer wallet permissions/RLS suite: 131 checks passed.
- JavaScript: 35 tests passed. Blade/generated PHP parsing, PHP syntax, route
  inspection, Vite production build and diff whitespace checks passed.

PostgreSQL was the pinned existing disposable cluster at 127.0.0.1:55439, database
`suki_return_refund_test`, role `suki_rr_test`; synthetic mail/notification/storage
fixtures only. Shared Supabase was not accessed or modified. No production
migration or financial operation ran. No browser interaction was performed.
