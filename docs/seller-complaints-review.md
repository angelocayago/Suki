# Seller Complaints integration review

## Root cause and authorization

The Seller sidebar linked to `complaints.mine.index` (`GET /complaints`). `ParticipantComplaintController::index()` intentionally renders a standalone, personal complaint history for Buyer/Seller/Rider/Logistics complainants. That route was not a Seller management page.

The canonical `ComplaintService::ensureCanAccess()` permits active non-Admins to access only complaints they personally submitted. Store ownership is not respondent access. Evidence additionally requires the same uploader. Existing participant history excludes internal event notes, assignments and other users' evidence; these restrictions remain intact.

The Seller page therefore lists only personally submitted complaints related to the Seller's store or SellerOrders. A Buyer complaint about that store remains inaccessible to the Seller. An inconsistent or foreign SellerOrder fails the new page's ownership checks. Personally filed cases without any store/order association remain available through unchanged generic history.

Existing states are `OPEN`, `UNDER_REVIEW`, `WAITING_RESPONSE`, `RESOLVED`, `DISMISSED`. Summary cards count all accessible complaints: total, open, in progress (under review/waiting response), closed (resolved/dismissed). Counts are independent of the filtered table.

## Implementation

- Added `GET /seller/complaints` (`seller.complaints.index`) and `GET /seller/complaints/{complaint}` (`seller.complaints.show`) inside the existing Seller middleware group: web/auth/not_suspended/approved/role:seller.
- Changed only the Complaints navigation tuple in the global Seller layout. Active matching includes existing Seller complaint creation routes.
- Added newest-first pagination, literal case-insensitive reference/order search, enum-derived status filters, Manila calendar date filters, URL persistence, active indicators and Clear All. Filters use backend queries and stay collapsed by default.
- Seller detail shows escaped descriptions, relevant context, existing public event fields and closure date/status. Internal notes, resolution notes, assignments, snapshot contents and private contact information are not displayed.
- Own evidence viewing/download/upload reuses unchanged protected complaint evidence routes. Uploads close on terminal complaints. Assigned Seller complainants retain the existing CSRF-protected Message Admin action through `complaints.messages.context.store`.
- No Seller status/decision/refund/delete endpoints were added. Free-form complaint responses have no separate canonical endpoint; existing evidence descriptions and assigned-Admin conversations remain available.
- Existing Buyer submission/history, Admin/Superadmin decisions, Rider/Logistics submission, evidence services, notification delivery and all financial services remain unchanged. No new financial issue was identified by this focused UI review; it is not a financial audit.

## Exact files

Modified:

- `routes/web.php`
- `resources/views/layouts/seller.blade.php`

Added:

- `app/Http/Controllers/Seller/ComplaintController.php`
- `resources/views/seller/complaints/index.blade.php`
- `resources/views/seller/complaints/show.blade.php`
- `resources/views/seller/complaints/style.blade.php`
- `tests/Feature/SellerComplaintsTest.php`
- `tests/Support/UsesIsolatedComplaintSchema.php`
- `tests/Feature/IsolatedComplaintFoundationTest.php`
- `tests/Feature/IsolatedComplaintAdminInterfaceTest.php`
- `tests/Feature/IsolatedComplaintEvidenceSubmissionTest.php`
- `docs/seller-complaints-review.md`

The page reuses the existing Returns presentation stylesheet without editing Returns files.

## Verification

41 PHP tests / 643 assertions passed: 8 new Seller cases, all 23 existing complaint foundation/Admin/evidence cases executed through isolated subclasses, and all 10 Returns management cases. The subclasses override RefreshDatabase with an explicitly guarded SQLite `:memory:` schema; no migration code executes. Filesystem fakes are rooted in uniquely named directories under a verified system-temp location. Original complaint test classes were not edited.

Coverage includes Seller navigation/layout/active state, ownership, unrelated case exclusion, all summary groups, search/status/date combinations, literal wildcard handling, Manila boundaries, pagination, empty states, details escaping/private notes, evidence delivery/upload closure, existing Buyer creation/history, Admin transitions and notification behavior, Rider/Logistics submissions and role middleware. Returns interaction tests also pass (4 JavaScript tests).

PHP lint, compiled Blade lint, rendered inline JavaScript parsing, route registration and `git diff --check` passed. PostgreSQL concurrency, schema constraints and collation behavior were not dynamically tested by the SQLite fixture. The existing Message Admin backend was reused without modification; a live conversation was not created during browser testing.

Browser discovery reported `The browser access host is unavailable`. Desktop/mobile visual verification remains outstanding; automated rendering is not claimed as visual verification.

The pre-task file hashes confirm `resources/js/landing.js`, Seller Returns / Refunds, Logistics Hubs, original Buyer/Admin complaint files and canonical services remain unchanged. No shared Supabase records were read or written, no migration or schema change was executed on the application database, no `.env` edit occurred, and no commit/push was performed.
