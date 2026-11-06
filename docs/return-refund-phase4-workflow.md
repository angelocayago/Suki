# Phase 4 implementation and rollout boundaries

The existing financial service, exact variant identity, allocations, lifetime item
uniqueness, financial readiness, payout locks and append-only release contracts
are preserved. Both returns.backend_enabled and returns.earning_release_enabled
remain false. No shared Supabase connection or migration is used for development.

## Structured physical return

Eligible Order Details items link to the actual item submission page. Previously
requested items show View Request and their existing status, including final
requests; they never offer a second lifetime request. The form shows frozen item
identity, full quantity, original merchandise, eligible refund after discounts,
reason, description, optional private JPEG/PNG and an explicit confirmation.
Unavailable financial/schema state shows a read-only form and explanation.

New public submissions go through ReturnRefundWorkflowService, which calls the
existing purchase/financial validation and creates a linked workflow atomically.
Only physical return + refund is supported. There is no approved refund-only
exception policy. Return instructions are captured from the approved Seller's
owned configured pickup address, requiring complete structured location fields.
Missing destination blocks submission and explains that support/Seller setup is
needed; no fallback or invented address is created. Seller confirmation authorizes
proceeding with the displayed destination; no external address verification is
claimed by this phase.

Seller confirmation/dispute retains SELLER_RESPONDED + APPROVE/REJECT, preserving
existing filters and financial active-request holds. The workflow parcel state
separately distinguishes PENDING, SHIPPED and RECEIVED. Seller decline is visible
to Buyer and remains in Admin review; it does not silently authorize a forced
return or refund. No new appeal/lifetime resubmission policy is introduced.

After confirmation, Buyer can submit an encrypted GCash/bank destination and
either shipping-pending information or a courier/tracking reference. There is no
fabricated tracking number for an unshipped parcel. Track Return opens the recorded
parcel section; this is status/reference tracking, not an external courier API.
Waybill/inspection evidence uses the existing private evidence service and routes,
including new Seller/Admin upload routes with the same participant authorization.

Seller receipt records authenticated recipient, time, inspection and notes once.
No stock restoration, original purchase mutation or refund payment follows receipt.
Admin web approval additionally requires structured confirmation, destination,
tracking and Seller receipt/inspection. The existing financial service also checks
these prerequisites for workflow-linked requests under its original locks.
Legacy financial unit/service contracts remain preserved internally; legacy web
requests without structured workflow records require support review, not automatic
backfill or enabled approval. Admin-only final decision and all original financial
proof checks still apply. Rejection remains available; approval is disabled in UI
and backend until the required schema and processing activation are available.

## Private destination and payment boundary

The destination is encrypted with Laravel's encrypted-array cast, excluded from
model serialization, and displayed only in the owning Buyer's or authorized
Admin return-detail page with private/no-store responses. Seller pages, notifications,
events and ordinary chat never receive these account details. Validation does not
flash private destination inputs into session old input. Authorized nodes require
a stable, correctly managed application encryption key; changing APP_KEY without
an approved key-rotation strategy can make saved destinations unreadable. No key
is read, shared or changed by this implementation.

Admin approval creates REFUND_DUE only. The interface says Payment Pending and
shows an explicitly disabled payment-verification action with its blocker.
Actual transfer verification is NOT implemented: a disbursement source, proof of
an actual transfer, independent verification authority, maker/checker policy and
reconciliation semantics remain unresolved. A typed reference or uploaded image
cannot prove payment. No REFUND_PAID records or actual transfers are created.

## Existing chat photo infrastructure reused

MessageAttachmentService, message_attachments, authenticated participant routes,
private storage, content validation/re-encoding, thumbnails and history polling
already exist. No duplicate attachment table or storage provider is introduced.
The Buyer/Seller composers now expose one-photo selection, preview, change/remove,
photo-only or mixed message sending, disabled busy controls and upload errors.
Existing multipart fallback and text-only messages remain supported; JavaScript
adds preview/polling. Uncertain send failures do not automatically retry a message.

All old multi-image history and its lightbox remain supported. JPEG/PNG/WebP are
offered only when the existing image stack, PHP upload limits, private disk,
attachment schema and messaging feature/deployment gates report ready. Otherwise
Add Photo is visibly disabled with a reason; text remains available. Neither
messaging activation nor deployment attestation was enabled by this phase.
The existing backend enforces 5 MiB, dimensions/content, random paths, participant
authorization, cleanup after DB failure, and private/no-store image responses.
Storage must be durable before deployment; ephemeral local files on Render Free
are not promised to survive restarts.

## Migration graph and deployment

Existing users/orders/items/shipments/variants/COD/complaint/chat migrations
  -> 2026_10_28_000001_create_return_refund_requests.php
  -> 2026_11_06_000001_enforce_one_return_request_per_item.php
  -> 2026_11_06_000002_scope_refund_wallet_entries_to_requests.php
  -> 2026_11_06_000003_add_source_identity_to_earning_releases.php
  -> 2026_11_06_000004_create_return_refund_workflows.php.

Chat independently depends on 2026_11_01_000001_create_message_attachments_table.php
and its existing configuration/storage/limits; no new chat migration is needed.
The new workflow table has one unique restricted-delete request FK, encrypted
destination text, captured return address and parcel/receipt metadata. It backfills
nothing and refuses destructive rollback once workflow records exist. Earlier
three migration files and financial flags are unchanged.

Do not run unrestricted migrate or use the normal application connection for
tests. Shared schema execution, feature activation and actual payment are separate
approval decisions. Quiesce affected writers, coordinate all teammates/nodes,
verify PostgreSQL constraints and readiness, deploy one compatible writer version,
and only activate through separately reviewed configuration. A push alone does
not migrate Supabase. No PostgreSQL concurrency or production readiness is claimed.

## Visibility and verification map

| Role | Normal entry | Action and result |
| --- | --- | --- |
| Buyer | Account/mobile My Returns & Refunds; My Orders -> Order Details | Eligible item Return / Refund opens actual form; existing item View Request opens status |
| Buyer | Request Details after Seller confirmation | Submit Refund & Return Details stores encrypted destination and pending/shipped parcel; Track Return shows saved reference/status |
| Buyer | Request Details | Chat with Seller opens the existing order conversation |
| Seller | Sidebar Returns / Refunds — Requests | Pending review/parcel counts, View details, confirmation/dispute and parcel-confirmation link |
| Seller | Request Details | Chat with Buyer; Confirm Returned Parcel and private inspection evidence |
| Admin | Existing Admin Returns / Refunds navigation | Review request, structured evidence and private destination; gated Approve or Reject; Payment Pending remains distinct from paid |
| Buyer/Seller | Existing Messages -> Conversation | Add Photo/Change Photo, preview/remove, photo-only/mixed send, gallery and participant-only larger preview after reopen |

Authenticated HTTP journey tests follow the above routes and inspect their actual
rendered forms/actions, statuses, errors, privacy and financial effects. Node tests
exercise photo selection, validation, payload, removal and busy state. Existing
attachment tests cover malformed/oversized files, access denial and failed-write
cleanup. This is not a claim of a manual browser/device visual acceptance run.
An attempted browser smoke check was blocked because the browser access host was
unavailable. No temporary browser server was started and no normal application
connection was opened. Desktop/mobile visual acceptance remains outstanding.
Both financial flags and the existing messaging deployment gates still require
separate live rollout approval. Payment verification/actual transfers, refund-only
exceptions and live courier tracking integrations are intentionally unavailable.

Validation: 462 selected PHP tests passed with 7,049 assertions using verified
SQLite memory databases and temporary private storage. The broad run used PHP
memory_limit=512M, upload_max_filesize=6M and post_max_size=40M for isolated test
processes; an initial lower-memory run correctly hit the existing image processing
memory guard. No production guard or PHP configuration file was weakened.
182 JavaScript tests passed. Route audit verified 453 routes; 15 relevant Blade
views compiled and passed syntax checks. Production Vite build and Git whitespace
checks passed. One pre-existing PHP 8.5 implicit-nullable warning remains in
CodRemittanceService::submitRider. PostgreSQL execution remains unverified.

## Exact Phase 4 file inventory

Modified existing files (earlier pending changes retained):

- app/Http/Controllers/ReturnRefundController.php
- app/Http/Controllers/ReturnRefundUiController.php
- app/Services/ReturnRefundService.php
- routes/web.php
- resources/views/buyer/order-details.blade.php
- resources/views/buyer/returns/show.blade.php
- resources/views/seller/returns/index.blade.php
- resources/views/seller/returns/show.blade.php
- resources/views/admin/returns/show.blade.php
- resources/views/components/buyer-navbar.blade.php
- resources/views/layouts/seller.blade.php
- resources/views/messages/show.blade.php
- resources/views/seller/messages/partials/thread.blade.php
- resources/views/seller/messages/script.blade.php
- tests/Feature/ReturnRefundUiTest.php
- tests/Feature/SellerMessagesInboxTest.php
- docs/return-refund-phase2-deployment.md (already untracked before Phase 4)

New files:

- app/Models/ReturnRefundWorkflow.php
- app/Services/ReturnRefundWorkflowService.php
- database/migrations/2026_11_06_000004_create_return_refund_workflows.php
- resources/views/buyer/returns/create.blade.php
- resources/views/partials/return-journey.blade.php
- resources/views/messages/partials/photo-composer.blade.php
- tests/Feature/ReturnRefundJourneyTest.php
- tests/JavaScript/chat-photo-composer.test.cjs
- docs/return-refund-phase4-workflow.md

No newly implemented endpoint lacks its corresponding visible form/action.
Disabled live processing, payment verification, refund-only exceptions, external
courier tracking and legacy-request workflow conversion are limitations rather
than hidden completed functionality.

## Phase 4.2 corrections

The Buyer composer forced a non-wrapping horizontal row, and the Seller composer
also used a horizontal flex row. Adding the photo/help panel as a third sibling
let that panel consume the available width and collapse the text input. Both
portals now use the same explicit grid: the photo/help panel spans the full first
row; the full-width text input and Send occupy the next row. At 540px and below,
the controls stack. Preview size is bounded and the former conflicting flex rules
are removed. Existing send handlers, polling, history galleries and lightbox are
preserved. Structural regression checks do not establish browser visual acceptance.

BANK destinations require at least four alphanumeric account characters,
including a digit, with optional spaces/hyphens and the existing 34-character cap.
Numeric accounts retain leading zeroes; formatted accounts and alphanumeric
international formats remain strings. Punctuation-only account/holder/bank values
are rejected. GCASH retains its exact 11-digit 09-prefix rule. Validation does not
prove ownership or authorize payment. Encryption, access limits and no private
session old input remain unchanged.

Admin can finalize UNDER_REVIEW through the existing endpoint and lock sequence.
Public approval still requires the structured return workflow, Seller confirmation,
receipt/inspection, financial proof, schema readiness and activation. A historical
request without that evidence explicitly requires support review; Admin may
record a justified rejection. Final decisions remain idempotent and conflicting
decisions are rejected. The decision form starts with Select Decision, never an
implicit rejection. Pending Buyer/Seller/Admin pages say Requested Refund and
Awaiting Decision; absent accounting is not presented as a zero refund obligation.

Failed request submission cleans only its newly uploaded evidence. A failed or
throwing cleanup logs a generic warning without file paths, destination data or
exception details and preserves the original submission exception. The database
transaction still rolls back. No existing evidence is removed on retry.

### Photo availability and rollout

The local project has no MESSAGE_ATTACHMENTS_ENABLED or
MESSAGE_ATTACHMENTS_DEPLOYMENT_VERIFIED setting, no corresponding CLI process
override, and no bootstrap/cache/config.php. Both settings therefore default OFF.
This is a deployment gate, not a reason to weaken attachment authorization.
The composer now explains the first blocking condition rather than listing all
possible causes as if they had each failed.

Local CLI GD/fileinfo are available, but upload_max_filesize=2M and post_max_size=8M
are below the service's unchanged requirements: upload_max_filesize at least 5M,
post_max_size at least 32M (or unlimited), max_file_uploads at least 5. CLI limits
do not independently establish the web server's configuration. No PHP settings,
feature flags or live configuration were changed.

The current shared attachment table and live web process settings are UNVERIFIED;
Supabase was not accessed. Before coordinated activation, separately authorize a
schema inspection, verify the existing message_attachments migration and private
durable storage, complete isolated PostgreSQL/security and browser acceptance,
configure sufficient web upload limits, and explicitly approve both messaging
gates. An existing table alone does not satisfy those other checks. No new chat
migration is needed and no successful live photo sending is claimed.

### Phase 4.2 exact file inventory

All are modifications of existing working-tree files; no new file was added:

- app/Http/Controllers/ReturnRefundController.php
- app/Http/Controllers/Messaging/ConversationController.php
- app/Services/ReturnRefundService.php
- app/Services/MessageAttachmentService.php
- resources/views/buyer/returns/show.blade.php
- resources/views/admin/returns/show.blade.php
- resources/views/partials/return-journey.blade.php
- resources/views/messages/show.blade.php
- resources/views/messages/partials/photo-composer.blade.php
- resources/views/messages/partials/attachment-style.blade.php
- resources/views/seller/messages/partials/thread.blade.php
- resources/views/seller/messages/style.blade.php
- tests/Feature/ReturnRefundJourneyTest.php
- tests/Feature/MessageAttachmentsTest.php
- tests/JavaScript/chat-photo-composer.test.cjs
- docs/return-refund-phase4-workflow.md

Phase 4.2 validation: 468 selected PHP tests passed, 7,197 assertions, using
verified SQLite memory databases and temporary storage. 183 JavaScript tests
passed. Route audit verified 453 routes; relevant Blade compilation, PHP syntax,
Vite build and whitespace checks passed. Test-process overrides were limited to
memory_limit=512M, upload_max_filesize=6M and post_max_size=40M. A fresh browser
automation availability check again returned an unavailable browser access host;
no browser visual or mobile-device success is claimed. PostgreSQL concurrency and
live schema execution remain unverified. All four pending migrations, both
financial flags, .env and landing.js remain unchanged.
