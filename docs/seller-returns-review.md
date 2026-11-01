# Seller Returns / Refunds review

The Seller queue now uses owned request and SellerOrder records, eager-loaded item/buyer/order snapshots, newest-first pagination, literal case-insensitive search, canonical statuses and Manila calendar-day filters. Summary cards count all owned requests; the table and matching count reflect filters. No synthetic records or monetary totals are used.

Existing states: `SUBMITTED`, `SELLER_RESPONDED`, `UNDER_REVIEW`, `ADMIN_APPROVED`, `ADMIN_REJECTED`. Only `SUBMITTED` permits a Seller recommendation. The existing PATCH endpoint requires `APPROVE`/`REJECT` and an explanation. Its canonical transaction/lock prevents duplicate responses. Admin alone finalizes decisions. Evidence continues through the existing participant-authorized download route. Timeline entries come from recorded events; missing history is shown as unavailable.

The Seller detail form adds confirmation, inline validation, pending protection and success feedback. Buyer/Admin controller methods, templates, routes, middleware, schemas and all financial services remain unchanged.

## Financial observations outside this UI change

- Approval persists a refund **due** and a Seller wallet adjustment. It does not prove the buyer has been paid. The Seller page deliberately displays only the stored refund amount after approval, and does not offer payment or finalization actions.
- Existing `SellerWalletService::hasActiveReturn()` recognizes `SUBMITTED` and `SELLER_RESPONDED`; `ReturnRefundService::submit()` also recognizes `UNDER_REVIEW` when checking active requests. This pre-existing difference was left unchanged. The current recommendation/decision service does not itself transition into `UNDER_REVIEW`.
- COD reconciliation, payout reservations, release of held earnings and repeated/partial-refund accounting remain governed by existing services. This work is not a financial correctness audit or reconciliation of historical records.

## Verification

`SellerReturnsManagementTest`: 10 tests / 187 assertions against verified SQLite `:memory:` fixtures constructed directly, with private disposable evidence storage under a unique system-temp directory. No migrations run. Covers real-query summaries, search/status/date combinations, Manila boundaries, pagination, empty states, details, private evidence, ownership, recommendation validation/duplicates, real CSRF enforcement, Buyer submission, Admin decisions and unchanged refund allocation/wallet summaries. No shared Supabase reads or writes were needed.

`seller-returns.test.cjs`: 4 passing interaction tests cover confirmation/cancel/focus, CSRF PATCH submission, pending protection, validation and ambiguous network failures without automatic retries.

Browser verification was attempted, but Computer Use stopped because it could not reliably identify the browser URL for access-policy enforcement. Desktop/mobile visual verification remains outstanding. PHP/Blade and JavaScript syntax checks and `git diff --check` passed. Existing `landing.js` is preserved byte-for-byte. No commit or push.
