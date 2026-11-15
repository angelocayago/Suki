# W5I bounded academic simulation settlement

This is a source-only, disabled milestone. It is not bank/GCash custody, a real
payment system, a deployed migration, or authorization to enable financial gates.
No route, command, button or automatic remittance hook can create/issue scenarios.

## Prospective admission boundary

`SimulationSettlementService::createScenario()` requires a freshly re-read active
Admin, an active Buyer, an approved Seller with an active Seller owner, and one
Seller's valid Cart. The first cohort admits **one newly created native COD order
per previously unused Seller**. Sellers with any previous SellerOrder, wallet
entry, payout or refund are refused. Existing orders cannot be tagged after the
fact. The method uses existing CheckoutService prices, stock, voucher, commission,
shipping and order creation, inside its own atomic transaction. It takes no money
argument. Unsupported quotes roll back checkout, inventory and Cart cleanup.

The immutable scenario key, original ownership graph, exact quote and SHA-256 hash
identify the purchase. A trusted finite `max_scenario_minor` bounds its total;
the checked-in cap is **0** and `enabled` is **false**. A repeated scenario identity
returns the original owned scenario. It never creates another purchase. This is
not a general multi-order Seller settlement engine. An additional ordinary order
for the enrolled Seller freezes further simulation operations for review rather
than mixing its earnings into the book. It does not change legacy checkout rules.

## Explicit reverse-shipping policy

Only these new scenarios carry
`ACADEMIC_SIMULATION_V1_NO_REVERSE_CHARGE` with `reverse_shipping_minor = 0` and
`reverse_shipping_payer = SELLER`. Seller remains the designated Return Waybill
payer. No reverse fee is charged, reserved, debited or posted. This does not price
legacy returns, alter the waybill implementation, or change outbound shipping,
Rider earnings or Logistics. Missing historical fee information is not zero.

## Complete source and claim partition

All values are integer centavos, derived from saved native purchased-item data:

    R = gross - Seller discount - platform discount
    E = gross - Seller discount - commission
    Q = commission - platform discount
    T = R + outbound shipping = E + Q + outbound shipping

The admission check requires commission <= gross - Seller discount and platform
discount <= commission on every item. Unfunded promotional subsidies and zero
merchandise-refund scenarios are not admitted. Commission is the saved actual
commission, not an assumed fixed percentage. Example:

    gross 20,000; Seller discount 101; platform discount 201; commission 2,000
    T 24,698 = E 17,899 + Q 1,799 + outbound shipping 5,000
    full refund 19,698 = Seller 17,899 + platform 1,799

`issue()` requires the exact accepted, unmixed COD remittance item, all existing
W5G acceptance audits/purchase evidence, completed purchase and an already
AVAILABLE native earning equal to E, without prior refund/adjustment/payout
obligations. HELD earnings are refused, never released. The original W5G custody
row stays wholly UNRECONCILED and is linked uniquely as provenance; it is not
silently promoted or counted as another financial asset. The new source has one
unique scenario/receipt identity, immutable cap, issuance actor and timestamp.

## Shared commitment protocol

The database invariant for each issued source is:

    issued = Seller available + platform available + protected outbound shipping
             + refund reserved + payout reserved + consumed

Every amount is nonnegative. Source sums also reconcile to relational commitments
and native payout status/amount/ledger identity. Shipping is pinned at 5,000 after
issuance; this phase cannot spend it. Existing Rider entitlements stay unchanged.

All three native payout writers (`request`, `review`, `markPaid`) enter the shared
coordinator before their existing locks. When installed, even legacy payout
writers acquire the mutex, preventing enrollment races; their native financial
behavior remains unchanged. Enrolled Sellers never fall through to legacy funding
when a simulation gate or prerequisite is missing. No cancellation writer exists;
rejection releases the existing reservation.

- Request: native reservation and exclusive Seller source reservation commit once.
- Approve: status reclassification only; no additional debit/reservation.
- Reject: release reservation once, then allocate released funds to pending refunds.
- Pay: preserve native authorization, active-refund and negative-balance checks;
  consume the reserved claim once. Only manual **simulated** completion is allowed;
  bank/e-wallet methods are refused. Receipts explicitly say SIMULATION ONLY.
  Duplicate payment remains rejected by the native contract, with no second debit.
- Seller refund approval: preserve the existing single negative adjustment and
  pending funding row, then exclusively reserve remaining E and Q claims. A prior
  payout reservation/consumption or protected shipping can never fill a shortfall.
  Partial source commitments remain separate; the existing funding row stays
  AWAITING with zero verified funding until its full refund is reserved. Only then
  is it READY, with a simulation label. No Buyer wallet entry is created.

A paid-out Seller's recovery deficit stays negative in the native wallet. It is an
obligation, not collected recovery money. No recovery receipt, Buyer credit, wallet
payment, earning release or shipping-discharge operation is implemented here.

Lock order: global simulation mutex -> Seller -> parent/purchase -> existing native
locks/source/commitments. Seller decisions enter before their original locks.
Legacy delivery/completion needs no new source mutation: issuance is explicit and
only admits an already AVAILABLE earning. Releases remain disabled. The global
mutex is deliberately conservative for this small cohort, not a high-volume design.

## Additive schema and security

New migration: `2026_11_15_000002_create_simulation_settlement.php`, after the
undeployed W5G custody migration. New objects:

- `simulation_settlement_mutex`: singleton coordination row, no money.
- `simulation_settlements`: immutable policy/purchase/cap and conserved source state.
- `simulation_commitments`: unique native payout or refund identity and owned claims.
- `simulation_settlement_events`: append-only operation snapshots with unique keys.

All FKs restrict deletion. CHECKs enforce shapes and conservation. PostgreSQL uses
deferred cross-row constraints, immutable policy/issuance/history triggers, and a
payout write guard rejecting uncoordinated enrolled-Seller mutations. Table and
column privileges and sequences are revoked from PUBLIC/anon/authenticated; RLS
has no client policies. Inherited unsafe roles/privileges abort atomic DDL. Trigger
functions are not client-executable. Existing tables' ACL/RLS and global default
privileges are unchanged. Rollback refuses to destroy recorded scenarios.

SQLite uses shape/immutability triggers and a guarded operation-close conservation
check. Native refund/ledger linkage is checked by the coordinator there, allowing
existing SQLite historical migration/rebuild tests to remain valid. SQLite does
not provide PostgreSQL's deferred commit-time enforcement; PostgreSQL is the
authoritative deployment target. Keep financial gates off for unsupported writers.

## Verification and rollout limits

Isolated checks use synthetic SQLite data and the pre-existing disposable local
PostgreSQL cluster, never Supabase. PostgreSQL probes include hostile default ACLs,
RLS, actual client-role denial, inherited BYPASSRLS membership rejection, atomic
security failure and real two-process source/payout/refund races. No actual bank
transfer, physical return, production refund, live source or Buyer credit is tested.
Browser verification is not claimed. An existing Chat Photo assertion is unrelated
and is not changed.

Before any rollout: review this finite fresh-Seller boundary, deploy both reviewed
migrations separately with schema/security checks, choose a finite trusted cap and
explicit scenario, and review simulation-specific Seller/Admin presentation and
operator actions. Do not enable this config merely because tests pass. Before
Buyer refund credits, implement an atomic claim-to-Buyer transfer with unique
posting identity and PostgreSQL concurrency/security tests; a READY label alone
cannot authorize money creation. Before wallet checkout, implement source-backed
payment, cancellation/reversal and downstream lifecycle integration separately.

Existing rollout settings are retained: wallet presentation on, refund credits
off, wallet checkout off, FINANCIAL returns off, Seller review on, earning release
off. This milestone performs no shared database migration, backfill or activation.

### Recorded isolated verification (2026-11-15)

- Final focused SQLite regressions: 326 tests / 5,060 assertions passed. The
  previously known `ReturnRefundJourneyTest` Chat Photo assertion is explicitly
  excluded from this focused run. An earlier broader run including it had 326
  tests / 5,059 assertions, one failure and zero errors; it expects the existing
  Chat composer to contain `name="images[]"`. No Chat file/assertion was changed.
- PostgreSQL 17.12, pinned disposable `127.0.0.1:55439`,
  `suki_return_refund_test` / `suki_rr_test`: 11 new tests / 137 assertions and
  22 existing W5F/W5G tests / 338 assertions passed. External adapters retain
  assertions and force deferred constraints before rolling back each test.
- Seven actual two-process races / 64 checks passed: duplicate issuance,
  oversubscribed payouts, competing refunds after consumption, payout rejection
  versus refund approval, duplicate payout payment, two fully backed payouts,
  and a bounded lock-timeout failure without partial reservations.
- 112 PostgreSQL security checks passed; temporary roles/default grants and
  security probes rolled back. Atomic migration failure left no partial objects.
- 35 JavaScript tests passed. Four relevant Blade templates compiled and their
  generated PHP parsed. Nine changed/new PHP files passed syntax validation.
  Production Vite build and `git diff --check` passed. No real browser was used.
- Harness/results remain outside Git under `C:\suki ulet\wallet-w5i` and the
  existing `return-refund-pg-r5b` test directory. Shared Supabase was not accessed.
