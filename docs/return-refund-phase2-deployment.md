# Return/refund backend Phase 2 — review required

## Disabled by default

Phase 4's additive request/parcel UI and existing chat-photo integration are
documented in [return-refund-phase4-workflow.md](return-refund-phase4-workflow.md).
The earlier phase checkpoints below retain their historical scope; both financial
flags remain false and shared PostgreSQL rollout is still unverified.

`config/returns.php` deliberately sets `backend_enabled` to false. No environment
switch enables it. New submission and approval fail with validation feedback;
existing read pages, seller recommendations, evidence access and rejection remain.
No Buyer submission UI or new route has been added.

Even with an explicitly reviewed configuration change, the service checks the new
index identities and request FK column before accepting new financial work.
Only the boolean value true enables processing; missing, null, string, numeric
or other ambiguous configuration values fail closed. The committed configuration
remains false; isolated tests override it only in their disposable application.

Phase 2.9 also defaults `earning_release_enabled` to false. It is independent of
the refund-processing flag. Neither flag may be enabled by merely applying schema.

## Conservative policies

- One lifetime request per original OrderItem, full quantity only. All terminal
  requests block another submission. No appeal, partial refund or restock.
- Validate exact historical item/variant ownership, delivered/completed workflow,
  voucher allocations, commission, collected COD and existing custody records.
  The relevant parcel can be paid while another Seller parcel remains unpaid.
- Approval records REFUND_DUE, never refund disbursement. All money snapshots,
  COD custody, voucher usage, inventory and already-paid payouts remain unchanged.
- A Seller-wide payout pause applies while any SUBMITTED, SELLER_RESPONDED or
  UNDER_REVIEW request exists, including pre-existing requests. Payout rejection
  remains possible. Creation, approval and payment serialize on the Seller row.
- Payment rechecks the exact owned reservation and current coverage without
  subtracting that payout's own reservation twice. Deficits require review.

## Phase 2.5 append-only held-earning reconciliation

The original HELD SALE_EARNING is never updated. One POSTED EARNING_RELEASE
references it through a unique nullable source_wallet_entry_id. Seller, Seller
Order and full original earning amount must match; no refund-request identity is
allowed on a release. Database constraints enforce identity/row shape; service
validation enforces cross-row financial and settlement evidence.

Release requires a completed, noncancelled purchase, exact historical financial
snapshots, verified collected COD and accepted upstream remittance, no active
linked return or complaint, and valid approved-request adjustments. Missing or
ambiguous historical proof retains the hold; no backfill or inferred settlement.
Refund approval can record a separate negative adjustment and REFUND_DUE while
the earning remains held. The final refund decision, complaint closure or COD
acceptance calls the same reconciler. It now checks schema and separate release
activation before any ledger write. None records REFUND_PAID.

Lifetime = original SALE_EARNING only. Held = original HELD earnings without a
valid release. Available = AVAILABLE original earnings + valid releases + signed
AVAILABLE refund adjustments - active reservations - verified completed payouts.
A source already changed to AVAILABLE invalidates its release for projection,
preventing double credit. Release report rows have zero sales/commission/discount
totals. A recovery deficit remains visible rather than receiving a synthetic credit.

Active financial complaints linked through a Seller Order also pause that Seller's
new payout requests, approvals and payment. Unrelated orders can still release
their eligible earnings. Existing balances and reservations remain unchanged;
payout rejection remains possible. The wallet page explains the restriction.

## Separate authorized deployment preflight

This implementation never connects to shared Supabase. Before any shared action,
obtain separate approval for read-only schema inspection and migration execution.

1. Verify intended database identity without printing credentials; inspect actual
   migration status, schema, PostgreSQL version, table/column types and constraints.
2. Inspect `return_refund_requests` for duplicate `order_item_id` values across ALL
   statuses. Stop on duplicates; do not merge/delete/backfill historical requests.
3. Verify the original wallet unique constraint is named
   `seller_wallet_entries_seller_id_seller_order_id_type_unique` and covers exactly
   seller_id, seller_order_id, type. Stop on unexpected schema or an existing new FK.
4. Verify FK targets, nullable compatibility, signed negative adjustment support,
   and absence of conflicting indexes. Review legacy unlinked adjustments, paid
   payouts, held earnings and allocation inconsistencies without modifying them.
5. Prove ordinary and concurrent behavior in disposable PostgreSQL first. SQLite
   validates isolated migrations/data behavior, not PostgreSQL races or lock waits.
6. Back up using the project's approved operational procedure. Quiesce refund and
   payout writers and workers; plan constraint/index creation lock time.
7. Confirm no pre-existing source column or EARNING_RELEASE history. Verify both
   Phase 2 unique indexes, including the legacy index's exact WHERE predicate.
   Stop on unexpected history or schema rather than silently adapting it.
8. After explicit migration authorization, apply only the three reviewed new files,
   in the order below. Verify PostgreSQL source FK RESTRICT, unique source index,
   seller_wallet_release_shape CHECK, request FK/index and legacy partial index.
   Compare historical row counts and monetary/status values with the preflight.
9. Leave both flags false until isolated PostgreSQL concurrency tests cover
   duplicate releases, simultaneous refund decisions, complaint creation/closure,
   payout requests/payment and multi-Seller COD batches. Review lock waits and
   rollback behavior, then obtain separate financial rollout approval. Migration
   execution alone never authorizes either activation or actual refund payments.

## Migrations

1. `2026_11_06_000001_enforce_one_return_request_per_item.php` adds a lifetime item
   unique index after a duplicate preflight. The older item/status constraint stays.
2. `2026_11_06_000002_scope_refund_wallet_entries_to_requests.php` adds a nullable,
   restricted-delete request FK and unique request identity; replaces the conflicting
   original unique constraint with a partial legacy index where the FK is NULL.
   No historical wallet row is backfilled. PostgreSQL and SQLite only.
3. `2026_11_06_000003_add_source_identity_to_earning_releases.php` adds the nullable
   restricted-delete self FK, unique source identity and release-shape CHECK
   (SQLite AFTER insert/update triggers, so generated IDs are available). It
   verifies prerequisite index columns as well as names/uniqueness, refuses
   incompatible schema/history and
   preserves the Phase 2 partial index predicate across SQLite FK table rebuilds.
   It does not change either earlier migration or historical records.

New approvals insert a request-scoped REFUND_ADJUSTMENT. Legacy NULL identities
retain their previous uniqueness. Adjustments cannot exceed original Seller net
earnings, including canonical zero-clamping for fully voucher-funded items.

## Rollback

Disable processing and stop all refund, complaint, completion, COD and payout
writers first. Retain expanded schema and financial records if any request-scoped
or release entries exist. Both wallet migrations refuse destructive rollback of
linked financial records. Before any entries exist, rollback must reverse the
three-file dependency order. Restoring the old in-place reconciliation code or
constraints is unsafe once release entries or multiple item adjustments exist.

## Evidence and locking

Refund operations: Seller -> Parent Order -> Seller Order -> Shipment -> ordered
OrderItems -> request -> accounting/wallet. Reconciliation uses the same ordered
purchase boundary. Financial complaint creation/closure acquires Seller first.
COD acceptance locks affected Sellers and Parent Orders in sorted ID order before
the remittance batch, then revalidates membership. Payout decisions lock Seller
before payout. Existing completion retains its Parent/Seller Order boundary;
creditCompleted does not insert a nested Seller lock after an order lock.
Custody records are read, never locked in reverse order or modified by reconciliation.
Evidence upload locks only the request, rechecks final status and writes private
JPEG/PNG <= 5 MiB. Failed metadata insertion deletes the newly created file;
cleanup failure is logged for operational review. Upload does not retry file writes.

No automatic transfers, bank integration, physical reverse logistics, inventory
restoration, voucher restoration or new Buyer submission controls are included.

## Phase 2.6 verification (2026-11-06)

Repository: skeleton at 4dbeb48. Verification used SQLite :memory:, explicit
test-only environment overrides and temporary storage/caches. No application
.env connection or shared Supabase connection was used. No live records were read.

Four defects were reproduced with failing isolated regressions before correction:

1. Truthy strings/numbers could enable the processing gate. Require boolean true;
   disabled submissions and approvals leave requests, events, accounting, wallet
   entries and notification intents unchanged.
2. Settlement lookup accepted a receipt naming the same Seller Order but another
   shipment. Completion credit and held reconciliation now require the exact
   shipment and accepted Rider receipt; unrelated upstream settlement cannot fund
   this earning. Existing detailed collection/custody/amount validation remains.
3. SQLite BEFORE INSERT saw an unassigned auto-increment ID, admitting a
   self-reference. AFTER triggers see the final ID and RAISE(ABORT) rejects the
   entire offending statement. PostgreSQL uses its existing row CHECK unchanged.
4. Release migration preflight accepted a unique prerequisite index with the right
   name but wrong columns. It now checks the exact ordered columns before DDL;
   incompatible schema fails without altering historical entries.

Migration order remains 000001 -> 000002 -> 000003. Static PostgreSQL review found
the unique constraints, nullable restricted-delete FKs, partial index and CHECK
consistent with the existing Laravel schema. This is not PostgreSQL execution
proof. The earlier two migration files were not edited during Phase 2.6.

Static lock order (arrows describe acquisition, not a concurrency test):

| Operation | Financial lock order |
| --- | --- |
| Refund submission | Seller -> Parent Order -> Seller Order -> Shipment -> ordered items -> new request |
| Admin decision | Seller -> Parent Order -> Seller Order -> Shipment -> ordered items -> request -> adjustment/accounting |
| Earning reconciliation | Seller -> Parent Order -> Seller Order -> Shipment -> ordered items -> source/adjustments -> release |
| Complaint creation | sorted Sellers -> Parent Order -> Seller Order -> Shipment -> new complaint |
| Complaint closure | Seller -> complaint -> common reconciliation purchase/ledger locks |
| COD upstream review | sorted Sellers -> sorted Parent Orders -> batch -> common reconciliation purchase/ledger locks |
| Payout request/review/payment | Seller -> payout -> reservation/ledger as needed |
| Seller Order completion | Parent Order -> Seller Order -> shipment update -> original earning; no later Seller lock |
| Evidence upload | request only; no Seller or order lock acquired afterward |

Seller serialization coordinates the financial paths with different subordinate
locks. Completion retains its established order boundary and does not introduce
a reverse Seller acquisition. Static review found no new opposing lock order in
these paths, but runtime lock behavior remains unverified on PostgreSQL.

415 selected PHP tests passed (5,740 assertions), including refund integrity,
held releases, payouts, cancellation, checkout/vouchers, Rider/Logistics, complaints,
inventory and financial reports. All 179 JavaScript tests passed. Route audit
verified 448 routes; eight relevant Blade views compiled and passed syntax checks.
All pending PHP files passed syntax checks, with one pre-existing PHP 8.5 implicit
nullable warning in CodRemittanceService::submitRider. Git whitespace checks passed.
Vite was not rerun: this phase changes no frontend assets or build configuration.

The projection preserves the existing raw paid_out_minor ledger reporting
definition. Actual balance deductions independently require matching owned PAID
payouts and deduplicate their identities; historical raw ledger anomalies are not
repaired or represented as proof of real bank transfers. Refund Due remains an
obligation, not evidence of disbursement. No new Buyer submission UI was added.

PostgreSQL verification is BLOCKED: PostgreSQL 16 binaries exist under Program
Files, but no running postgres process/service or listener on local ports 5432/5433
was discovered, and no independently verified test database/credentials were
supplied. Docker is unavailable. No server was started, installed or provisioned.
No shared credentials were reused. Before live rollout, prove duplicate submissions,
simultaneous decisions, multiple item approvals, concurrent releases, refund vs
payout completion, complaint closure vs COD acceptance, and multi-Seller COD batches
in a separately verified disposable PostgreSQL instance, including failure rollback
and migration constraint/index inspection.

Readiness: suitable for manual review of a code-only commit with processing still
disabled and this PostgreSQL blocker recorded; not approved or verified for live
schema deployment, earning-release rollout or refund activation. All repository
changes remain unstaged/uncommitted. No real refunds, payouts, stock restoration,
historical money rewrites or feature-flag activation occurred.

## Phase 2.9: compatibility, reporting and release rollout

This section supersedes earlier readiness observations. Both configuration flags
are false. No migration file was changed for Phase 2.9. PostgreSQL execution still
has NOT passed: Phase 2.7 found PostgreSQL 16.14 binaries but the installation's
share directory, postgres.bki and postgresql.conf.sample were missing. initdb also
reported a restricted-token error. No local cluster was created; do not substitute
shared Supabase for an independent test database.

### Exact runtime behavior

- Fully pre-migration: existing Wallet, Dashboard, Settlements and Admin balance
  queries avoid new identity columns. COD acceptance and complaint closure retain
  their own statuses and authorization. Reconciliation returns SCHEMA_UNAVAILABLE,
  logs an operational notice with order ID/status, and retains HELD unchanged.
  Seller Wallet/Settlements and Admin Financial Report explain retained earnings.
  This is conservative operation, not equivalent earning availability to old code.
- Partial/inconsistent schema: missing either column, required unique identities,
  restricted-delete FKs, the legacy partial-index predicate, or release-shape
  enforcement is not READY. Release-dependent balance reads fail closed, retaining
  original held earnings rather than crediting unverified release rows.
- Fully reviewed schema with release gate false: reconciliation returns
  ACTIVATION_DISABLED; no release is inserted. Existing valid posted releases
  remain part of read-only balances even while new release creation is disabled.
- Fully reviewed schema with release gate boolean true: the service checks the
  existing purchase, COD, active complaint/request and adjustment prerequisites
  under the established Seller-first locks. A new row yields RELEASED; a replay or
  still-ineligible purchase yields NO_RELEASE and does not duplicate a credit.
- Refund backend false independently rejects new submissions/approvals. Rejections,
  evidence/history and Seller recommendations remain available. A final rejection
  can call reconciliation but cannot bypass its separate schema/activation gate.
- Normal original SALE_EARNING creation on order completion remains supported;
  the release gate does not disable all sales earnings or payouts. Completion
  still retains holds when COD/dispute evidence requires them. Payout hold and
  exact-reservation funding protections remain active independently of both flags.

All three callers (COD acceptance, complaint closure and refund decisions) use
`reconcileSellerOrderResult`. Its status is the operational distinction; a boolean
false alone is not evidence that all financial work was unnecessary. The old
boolean method remains a compatibility wrapper. No "earning available" COD
notification is emitted unless the result is RELEASED.

`FinancialSchemaReadiness` reads actual metadata, rather than trusting migration
tracking alone: expected index columns/uniqueness, both restricted-delete FKs,
the legacy WHERE predicate, and PostgreSQL validated CHECK or SQLite AFTER
insert/update triggers. Ordered migration tracking remains Laravel's responsibility;
the readiness service neither migrates nor repairs schema. Runtime metadata must
be readable by the application role. Recheck after worker restart and never change
DDL concurrently with active financial writers.

### Canonical Admin/Seller balance semantics

Admin available, held and reserved balances reuse SellerWalletService's aggregate
projection; they are no longer raw status sums that ignore releases. Valid releases
are counted once, original sources remain unchanged, malformed sources are ignored,
and an AVAILABLE original source cannot also credit its release. Lifetime earnings
count only original sales. Current Seller payable is available + held + reserved:
outstanding net entitlement after verified completed payouts, including funds not
currently withdrawable. A recovery deficit is not replaced with artificial credit.

Admin verified payouts use matched owned PAID payout identities; the historical
raw paid_out_minor field retains its existing ledger-reporting definition. The
Admin refund-adjustment activity and commission/COD activity still obey report
date filters. Wallet balance cards are current balances for the selected Seller
(or all Sellers), explicitly independent of activity dates, provider and remittance
status. They are not period sales, release movements or actual new bank transfers.
The existing CSV remains a filtered provider remittance export.

To verify consistency, compare the same Seller's Wallet available/held/reserved
values with Admin Financial Report, then check a valid release, a refund debit,
reservation and verified payout. Vary report dates: current wallet balances stay
unchanged while period activity changes. Never infer Refund Paid from Refund Due.

### Reviewed retries and coordinated deployment

1. Finish independent PostgreSQL migration/locking tests and review code. A local
   snapshot commit is separate from permission for teammates to run the code.
2. Obtain separate approval for shared read-only schema/history preflight, backup
   and the three narrowly scoped migration executions. Inspect all pending migration
   statuses; do not run unrestricted migrate or assume only these files are pending.
3. Coordinate every teammate, deployed node and worker. Quiesce refund, complaint,
   COD, completion and payout writers; prevent old/new financial writer overlap.
4. Apply 000001 -> 000002 -> 000003 only after approval. Verify actual constraints,
   unchanged historical values and a READY schema result. Deploy the reviewed code
   consistently while writers remain stopped. Restart workers/config caches using
   the approved deployment procedure; do not change production flags automatically.
5. Obtain separate earning-release activation approval after PostgreSQL proof.
   Enable only earning_release_enabled in reviewed deployment configuration. Keep
   backend_enabled false. Resume writers only with explicit acceptance of any
   retained-earning period and verified reports.
6. Review retained HELD original sale rows and their order/parcel evidence. Missing
   schema/disabled results do not persist an automatic release queue. No page read
   or migration sweeps historical balances. An authorized operator can retry one
   reviewed Seller Order through reconcileSellerOrderResult; record the returned
   status and verify exactly one release, unchanged source, and matching reports.
   READY is not proof a particular order is eligible. NO_RELEASE may mean an
   existing release or still-invalid evidence; investigate instead of overriding.
   A repeated retry must not insert another release. Never update HELD to AVAILABLE
   manually or fabricate settlement evidence to clear a hold.
7. Refund-submission/approval activation requires its own later approval and
   complete workflow verification. Actual Buyer refund payments are a separate,
   unimplemented disbursement workflow and are never authorized by either flag.

No guaranteed seamless code-first rollout is claimed: pre-schema/disabled releases
retain legitimate earnings and require reviewed retries. Pushing files to GitHub
does not itself execute them, but a shared skeleton push is not safe for ordinary
uncoordinated teammate use. Live migration, release activation, refund activation
and actual payment are separate decisions. Do not restore the unsafe historical
in-place release as a compatibility fallback.

Phase 2.9 validation: 431 selected isolated SQLite PHP tests passed (6,117
assertions); 179 JavaScript regressions passed. Nine relevant Blade views compiled,
448 routes passed the route audit, Vite production build passed, PHP syntax and
Git whitespace checks passed. One existing PHP 8.5 implicit-nullable deprecation
remains in submitRider. No PostgreSQL execution or shared-database readiness is
claimed. All three migration files remain byte-for-byte unchanged for this phase.
