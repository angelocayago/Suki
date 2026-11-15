# W5J-B: atomic simulation refund credit

Implemented and verified locally. No shared Supabase connection, live posting,
deployment or financial activation was performed. This extends W5F/G/I/J-A;
their three pending migration source files remain unchanged.

## Posting and authorization

`BuyerRefundFundingService::credit()` delegates enrolled requests to
`SimulationSettlementService::creditRefund()`. There is no public credit route,
balance editor, funding button, automatic historical backfill or background job.
An active, non-suspended Admin and both simulation and refund-credit gates are
required. Authorization is rechecked after waiting for the simulation mutex.

The transaction takes the existing simulation mutex before Seller, original
Order/SellerOrder and source-book locks, then request/item/funding/commitment and
Buyer/wallet locks. It reloads identities from the database rather than trusting
the supplied model's mutable ownership or amounts. It revalidates the accepted
COD custody source, frozen quote/hash, purchased-item allocation, SELLER_REVIEW
approval, issuer and full exclusive Seller/platform reservation. Unsupported
Admin/financial cohort approvals, partial funding, rejected requests, altered
provenance and historical refunds fail closed.

The transaction materializes an empty Buyer Wallet only when necessary, checks
ledger integer capacity, posts one owned POSTED REFUND_CREDIT, consumes the full
reservation, marks funding CREDITED_TO_BUYER_WALLET, records an immutable event
and inserts the exact backing link. All use one timestamp. A successful replay
returns the same entry without new writes; inconsistent replay fails without a
compensating credit. Failure rolls back wallet creation, ledger, funding, event,
backing and settlement counters together.

## Schema and accounting

The new additive migration is
`2026_11_15_000004_create_simulation_wallet_backings.php`. Apply it only after the
three pending custody/simulation/approval-guard migrations in a separately
reviewed deployment. It creates `simulation_wallet_backings` with a distinct
`BUYER_REFUND_BACKING` kind, PHP denomination, positive amount equal to Seller
plus platform components, exact source/parent commitment, unique funding
request, wallet entry, settlement event and source identity, Buyer owner,
credited-by actor and timestamp. Foreign keys use restrictive deletion; rows
are immutable and there is no new sequence.

The existing REFUND parent becomes WALLET_BACKED and retains its original
allocation provenance. Reserved totals count PARTIAL/RESERVED parents only;
consumed totals count paid Seller payouts **plus** Buyer refund backings. The
backing is a retained Buyer liability/claim, not a paid Seller payout or an
external cash disbursement. Available source claims cannot reuse it. For asset
reporting, subtract paid external payouts from issued funds; do not subtract
all consumed funds as if Buyer backing had left custody.

The migration extends the prior database CHECK and deferred predicates without
editing their source migrations. It retains J-A coverage and W5I payout math,
and adds inverse checks so no scoped credit, CREDITED funding or WALLET_BACKED
parent can exist without the matching complete backing. Twelve initially
deferred PostgreSQL hooks cover the related financial tables. Native ledger
guards require an open coordinator and exact reserved ownership/amounts.
Backed credits are immutable. Spending/reversal and supplemental unscoped
credits for a Buyer with simulation backing remain blocked until a future
coordinated contract exists.

W5C security is preserved: private RLS with no client policies; table/column and
function privileges revoked from PUBLIC/anon/authenticated; fixed function
search path; inherited privilege checks; transactional DDL. No unrelated default
privileges are changed. Rollback refuses existing posted backing or backed
parents. Installation refuses pre-existing scoped wallet credits rather than
inventing backing for them. SQLite uses application transaction checks;
PostgreSQL additionally enforces the deferred relational contract at commit.

The Seller adjustment and commission arithmetic are unchanged. The original
REFUND_DUE accounting row remains the Seller obligation memo, not the wallet
posting state. Canonical wallet success requires funding + owned posted entry
+ exact backing. No second Seller debit, commission reversal, deficit clearing,
earning release, notification or payout is created by credit posting.

## Positive isolated PostgreSQL evidence

Synthetic discounted purchase, amounts in centavos:

| Component | Before | After |
|---|---:|---:|
| Issued source | 24,698 | 24,698 |
| Seller/platform available | 0 | 0 |
| Protected outbound shipping | 5,000 | 5,000 |
| Refund reservation | 19,698 | 0 |
| Payout reservation | 0 | 0 |
| Consumed | 0 | 19,698 |
| Of consumed: paid payouts | 0 | 0 |
| Of consumed: Buyer backing | 0 | 19,698 |

Seller component 17,899 plus platform component 1,799 equals refund 19,698.
Both sides satisfy issued = available + shipping + reservations + consumed.
One empty wallet became one wallet with one POSTED REFUND_CREDIT of PHP 196.98,
one backing and funding CREDITED_TO_BUYER_WALLET. Balance/available were 19,698;
replay returned the same entry. Seller ledger and REFUND_DUE memo were unchanged.

Evidence is in the external local testing directory
`C:\suki ulet\wallet-w5jb`; it contains synthetic data only. The disposable
PostgreSQL identity was 127.0.0.1:55439, database `suki_return_refund_test`, role
`suki_rr_test`, PostgreSQL 17.12. Fixed identity/marker checks precede test writes.
No credentials or shared connection are used in this report.

## Verification

| Check | Result |
|---|---|
| Pre-edit SQLite guard/payout baseline | 26 tests, 313 assertions passed |
| Focused SQLite regressions, including new credit cases | 350 tests, 5,331 assertions passed |
| New credit cases, included above | 14 tests, 163 assertions passed |
| PostgreSQL settlement/approval/credit checkpoint | 35 tests, 408 assertions passed |
| PostgreSQL earlier custody/funding regressions | 22 tests, 338 assertions passed |
| Actual two-process PostgreSQL concurrency | 21 races, 229 checks passed |
| PostgreSQL credit security and incomplete-state probes | 145 checks passed |
| Relevant JavaScript | 35 tests passed |
| Four relevant Blade views / generated PHP | Passed |
| Changed/new PHP syntax, route inspection, Vite build, diff whitespace | Passed |

Concurrency covers duplicate same-request credit, distinct refunds sharing a
source, paid/reserved payout competition, rejection, concurrent valid payout
payment, replayed approval, injected entry/backing failure and lock timeouts.
Deferred tests reject incomplete credits and wrong ownership. Security probes
verify client denial, RLS after accidental grants, immutable backing/entries,
unsafe role inheritance rejection, atomic DDL failure and rollback restrictions.

The PostgreSQL adapters are outside the repository. They retain original test
assertions and replace SQLite-specific failure triggers with equivalent native
PostgreSQL triggers. Two historical missing-schema fixtures first roll back
the empty new backing schema using its real `down()` before dropping wallet
parent tables, preserving dependency order. No old repository test or constraint
was weakened for that setup compatibility.

The separate known unrelated Chat Photo test still fails (1 test, 10 assertions):
`ReturnRefundJourneyTest::test_existing_order_chat_photo_only_and_mixed_replies_are_visible_after_reopening`
expects `name="images[]"`. It is excluded from the 350-pass checkpoint and was
not repaired. No browser visual or live application credit verification occurred.

## Display and rollout restrictions

The existing wallet page/navigation/layout remain intact. Buyer-owned eager
loading verifies backing and posted entry before displaying "Credited to Suki
Wallet". Isolated HTTP rendering covers the amount, request, Philippine time,
history, empty-wallet creation behavior and Buyer privacy. Pending/partial
funding remains non-spendable. Historical unclassified records are not rewritten
or credited, including the historical PHP 299 example.

Prospective fresh-Seller/one native COD order eligibility, finite scenario cap,
unique source issuance and explicit simulation-only zero reverse-shipping charge
are unchanged. Seller stays the designated reverse-shipping payer. No reverse
fee is charged/reserved/deducted; legacy missing fees are not treated as zero.
Outbound shipping and Rider liabilities remain protected.

Effective source settings remain wallet enabled=true, refund credit=false,
wallet checkout=false; financial returns backend=false, Seller review=true,
earning release=false; simulation enabled=false, scenario cap=0. Tests override
gates only for disposable fixtures. Ordinary COD/returns/waybill/payout behavior
remains covered by regressions.

Before rollout, separately review and commit the complete compatible pending
source/migration set; verify PostgreSQL identity, secure TLS, prerequisite schema,
permissions, constraints, legacy-data compatibility and deployment window.
Deploy in dependency order only with explicit authorization. Keep gates off
until a bounded prospective cohort and internal operator procedure are approved.
Wallet spending, reversal, financial activation and live positive verification
are separate future work; no public mutation endpoint is introduced here.
