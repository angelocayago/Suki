# W5J-A: simulation approval eligibility and shared commitment coverage

Scope: local source implementation and isolated testing only. No shared Supabase
connection, production migration, activation or Buyer credit is performed.

## Root cause and affected paths

W5J's external SQLite reproduction (`C:\suki ulet\w5j-credit-preflight-results.json`)
showed a 20,000-centavo Admin-review refund and -18,000 Seller adjustment while
the issued book retained 18,000 Seller availability and zero refund reservation.
The old `decide()` bypassed the coordinator and the old database predicate checked
existing commitments only; it did not require every obligation to have one.

The application approval writers are:

| Entry | Effect | New treatment for enrolled purchases |
|---|---|---|
| `ReturnRefundService::decide()` ADMIN_REVIEW branch | Admin decision, adjustment, REFUND_DUE | Unsupported; fail before writes/replay |
| `decide()` FINANCIAL branch | Historical financial approval/adjustment | Unsupported even if its gate is enabled in an isolated test |
| `sellerDecide()` / private native body | Owning Seller decision, adjustment, pending funding | Mandatory existing coordinator, readiness and coverage |
| Private `recordRefundAdjustment()` | Shared native obligation and debit writer | Rechecks cohort mode and the open shared transaction |

`submitPurchase()` rejects unsupported cohort modes before request creation.
`submitForAdmin`, `submit`, and the physical financial workflow's `submit` all
reach that common check. The HTTP Buyer submission continues to use
`submitForSeller`; Admin/Seller decision routes remain unchanged and authorized.
Evidence uploads, physical-return details/receipt and waybill issuance do not
create approval adjustments. No Job/Console approval writer was found.

## Application behavior

`SimulationRefundApprovalGuard` is a small shared validator, not a new funding or
approval service. It resolves the existing order/SellerOrder enrollment, validates
ownership and SELLER_REVIEW mode, and requires the scope registry, coordination
schema and installed database guards. PostgreSQL readiness additionally requires
all eight enabled, initially-deferred coverage hooks. Supported approvals cannot
fall back to native uncoordinated posting when the coordinator/schema is absent.

The existing Seller-first purchase locks and original approval body remain intact;
the simulation mutex is acquired before those locks. The coordinator verifies
reverse obligation coverage before closing. Issuance and enrolled payout actions
also require the approval safety infrastructure. Non-enrolled historical Admin
and financial behavior remains unchanged, including the financial activation gate.

Existing full reservations remain exactly once. A shortfall retains an approved
request, the original single Seller adjustment, AWAITING funding and an explicit
PARTIAL commitment, even when the currently reserved claim is zero. Paid/reserved
payouts cannot be reassigned as refund funds. Rejection can release a payout's
reservation through the existing coordinator and complete the pending refund.
No Seller debit is repeated and no recovery deficit is cleared.

## New additive migration (not deployed)

`2026_11_15_000003_enforce_simulation_refund_approval.php` follows the unchanged,
pending W5G and W5I migrations. It creates:

- `simulation_refund_scopes`: one immutable request-to-book/item identity, with
  restrictive FKs and a book index. No monetary fields or sequence are added.
- Scoped native request/accounting/refund-adjustment/funding write guards.
- PostgreSQL deferred reverse-coverage enforcement across requests, accounting,
  Seller entries, funding, commitments, scope, mutex and source rows.
- Private trigger functions, revoked from PUBLIC/anon/authenticated; pinned
  `pg_catalog,public,pg_temp` search paths. RLS has no client policies. Table and
  column privileges are explicitly revoked and unsafe inherited roles abort DDL.

Creation and security are atomic. Existing object ACLs/RLS and global defaults are
unchanged. Existing enrolled requests cause migration refusal rather than an
automatic backfill. Destructive rollback refuses recorded scope/history.

PostgreSQL enforces actual original mode and identity and rejects unsupported
approvals even with an open marker. Merely setting the marker and recording a
partial approval/debit is insufficient: every obligation must have exact owned
accounting, its single debit, funding and a coherent full/partial commitment at
commit. Missing claims cannot coexist with unreserved matching source availability.
These checks supplement, rather than change, W5I's monetary conservation checks.

SQLite guards coordinated entry and records the required cohort mode in scope.
Actual mode and cross-row coverage are verified by the coordinator. Its persistent
triggers avoid depending on the workflow column that historical SQLite migration
tests intentionally remove. SQLite does not have PostgreSQL deferred commit-time
enforcement; production safety conclusions rely on the real PostgreSQL tests.

## Isolated verification

Existing test assertions were not modified. The new
`SimulationRefundApprovalGuardTest` covers unsupported submissions and staged
Admin/financial decisions, unchanged failed-attempt snapshots (including history
and notification outbox), full/zero/partial claims, coordinator/schema absence,
the private writer, independent SQL writes, reverse coverage, rollback and legacy
Admin behavior. Existing waybill, wallet, payouts, COD, Buy Now/Again and the
synthetic historical 299-peso wallet regression are included in the focused suite.

Recorded results (2026-11-15):

- Focused SQLite suite: 336 tests / 5,168 assertions passed.
- Disposable PostgreSQL 17.12 on 127.0.0.1:55439, database
  `suki_return_refund_test`, role `suki_rr_test`: 21 W5I/W5J-A feature tests /
  245 assertions passed. External adapters preserve assertions, use disposable
  storage, force deferred constraints, and roll back test fixtures.
- Existing PostgreSQL W5F/W5G checkpoint: 22 tests / 338 assertions passed.
- Eleven actual two-process races / 107 checks passed, including unsupported
  Admin versus Seller approval, payout versus submit/approve, duplicate Seller
  approval, competing refunds, source/payout replays, and bounded lock timeouts.
- 105 PostgreSQL security/independent-writer checks passed: actual client denial,
  RLS after accidental grants, private functions/search paths, no new sequence,
  unchanged existing security/default privileges, unsafe role rejection and atomic
  security failure. Incomplete marker-open approvals/accounting/debits failed
  deferred validation and rolled back; valid server approval succeeded.
- 35 relevant JavaScript tests passed. Four relevant Blade templates compiled and
  their generated PHP parsed. Five changed/new PHP files passed syntax checks.
  Production Vite build and `git diff --check` passed.
- The known `ReturnRefundJourneyTest` Chat Photo assertion was run separately:
  one test / 10 assertions, one unchanged failure for `name="images[]"`. It is
  excluded from the green focused suite. No Chat source/test was changed.
- No real browser verification is claimed. Test harness/results are outside Git
  under `C:\suki ulet\wallet-w5ja`; no shared Supabase access occurred.

## Remaining credit work and rollout

No positive Buyer Wallet credit is implemented. `BuyerRefundFundingService::credit`
still refuses posting, and existing W5I consumption counts paid payouts only.
W5J still needs typed, exactly-once refund consumption/Buyer-entry linkage,
consistent conservation/backing semantics, atomic wallet-row/entry/funding/audit
updates, successful replay validation, verified credited labels and real concurrent
credit/rollback/security tests. REFUND_DUE is unchanged by this approval phase.
Do not repurpose payout consumption as external cash discharge for a wallet claim.

The new guard deliberately rejects future CREDITED transitions until that next
phase extends the state/relational contract; this phase is readiness for credit
implementation, not financial activation or deployment authorization.

All rollout settings are unchanged: wallet presentation true, refund credits false,
wallet checkout false; financial returns false, simplified Seller review true,
earning release false; simulation false, scenario cap zero. All three pending
simulation/custody/approval migrations remain undeployed to shared Supabase.
