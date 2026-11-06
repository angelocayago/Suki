# GitHub handoff: SUKI SHOP Return/Refund and chat photos

This handoff covers the existing Laravel application on `skeleton`, baseline
`4dbeb48`. It is not a replacement demo or deployment authorization. All application
work remains unstaged. The file inventory below must be reviewed before the user
manually chooses what to commit; no Git operation is performed by this document.

## Application behavior and visible entry points

| Role | Normal entry | Working application action | Activation boundary |
| --- | --- | --- | --- |
| Buyer | My Orders -> Order Details | Eligible item Return / Refund opens the actual submission form | Submission requires financial/schema readiness |
| Buyer | Account/mobile My Returns & Refunds; item View Request | Owned request status, evidence, Seller response | History remains available |
| Seller | Sidebar Returns / Refunds - Requests | Pending review counts, View details, Confirm willingness or Dispute/Decline | Seller response is an official event, not a chat inference |
| Buyer | Request Details after Seller confirmation | Encrypted refund destination and pending/shipped parcel form | Schema and processing gate required |
| Seller | Shipped request -> Confirm Returned Parcel | One-time receipt, inspection and private evidence | Does not pay a refund or restore stock |
| Admin | Returns / Refunds -> Review | Explicit Select Decision, authorized final approval/rejection | Approval rechecks original purchase and structured return proof |
| Buyer/Seller | Request Details | Chat with Seller / Chat with Buyer | Existing authorized order conversation |
| Buyer/Seller | Messages -> Conversation | Add/Change/Remove Photo, image-only or captioned sends, history thumbnails and larger viewer | Separate messaging/storage gates |

Full-quantity physical returns use exact historical OrderItem/variant identity and
one lifetime request per item. Partial returns, second requests/appeals and
refund-only exceptions have no approved policy. Seller disputes remain visible for
Admin review. UNDER_REVIEW is finalizable through the existing Admin endpoint,
with the same financial and structured-workflow requirements. Historical requests
without that structure require support review rather than automatic conversion.

Pending requests show Requested Refund / Awaiting Decision. Admin approval creates
REFUND_DUE only, with the existing request-scoped negative wallet adjustment.
Original order/stock/voucher/COD records are not rewritten. Seller holds, payout
serialization/reservation checks, append-only releases and settlement proof remain.
Track Return displays stored parcel status and courier reference; it is not a live
carrier API.

## Separate unimplemented feature: actual refund payment

Manual refund disbursement verification is NOT implemented. There is no proven
payment record, bank/GCash transfer, REFUND_PAID transition or refund-completed
claim. The Admin payment-verification control is explicitly disabled. A separately
approved funding source, maker/checker authority, proof verification, idempotent
disbursement identity and reconciliation design are required before adding it.
A typed reference or uploaded image alone must never mark a refund paid.

## Flags and deployment configuration

`config/returns.php` contains two literal false booleans:

- `returns.backend_enabled`: new submission, destination/parcel actions and approval.
- `returns.earning_release_enabled`: separate automatic earning-release permission.

There is no Return/Refund environment-variable shortcut. Changing either requires
separate approval after schema and financial validation; neither migration nor a
GitHub push enables them. Seller recommendations, history/evidence and justified
Admin rejection are not represented as financial payment activation.

Messaging uses default-false environment settings:

- `MESSAGE_ATTACHMENTS_ENABLED` -> `messaging.attachments_enabled`
- `MESSAGE_ATTACHMENTS_DEPLOYMENT_VERIFIED` -> `messaging.deployment_verified`

Local development uses the private `message_attachments` disk. Optional deployment
selection `CHAT_PHOTOS_STORAGE_DRIVER=s3` requires:

- `CHAT_PHOTOS_S3_ENDPOINT`, `CHAT_PHOTOS_S3_REGION`, `CHAT_PHOTOS_S3_BUCKET`
- `CHAT_PHOTOS_S3_ACCESS_KEY_ID`, `CHAT_PHOTOS_S3_SECRET_ACCESS_KEY` (server secrets)
- `CHAT_PHOTOS_S3_PRIVATE_BUCKET_VERIFIED` (default false, operator attestation)

These names require deployment values, never secrets committed to GitHub. The
Supabase bucket must be private and anonymously inaccessible. Server keys bypass
Storage RLS: Laravel conversation authorization remains mandatory. The dedicated
official S3 adapter removes unsupported ACL headers, rejects public grants and
returns only Laravel-authorized attachment URLs. Real provider integration is not
verified by mocked HTTP tests. No live bucket or credentials were provisioned.

Container GD now requests JPEG/PNG/WebP support. `docker/php/chat-uploads.ini`
provides 6M per-file, 40M POST and 20 upload slots. Verify the actual container,
web PHP/proxy limits and memory budget; no Docker build was executed locally.
Local Windows CLI defaults are still 2M/8M and are overridden only for tests.

### Two separate private-storage requirements

Chat S3 storage does NOT make Return/Refund evidence durable. Refund evidence still
uses the existing private `local` disk rooted at `storage/app/private`.
Before enabling returns, provide verified durable private storage for that disk,
for example a correctly owned persistent mount covering the evidence directory.
Otherwise a separately approved evidence-storage change is needed. Do not claim
Render's ephemeral filesystem preserves evidence across deployments.

Before changing chat backends, separately assess existing attachment metadata and
files. Changing the disk alias does not copy old normalized images or thumbnails.
Any authorized transfer must preserve relative keys, verify hashes and retain old
copies until acceptance. Do not drop attachment records or silently fall back to
another backend. Keep APP_KEY stable and backed up for encrypted destinations;
do not generate a new key as part of deployment.

## Migration order and dependencies

Existing base schema (users, products/variants, orders/items, shipments, COD,
wallet, payouts, vouchers, complaints and messaging) is required first.

Return/Refund sequence:

1. Already committed `2026_10_28_000001_create_return_refund_requests.php`.
2. Pending `2026_11_06_000001_enforce_one_return_request_per_item.php`.
3. Pending `2026_11_06_000002_scope_refund_wallet_entries_to_requests.php`.
4. Pending `2026_11_06_000003_add_source_identity_to_earning_releases.php`.
5. Pending `2026_11_06_000004_create_return_refund_workflows.php`.

Independent chat sequence:

- Existing conversations/users -> `2026_10_12_000020_create_messages_table.php`
  and participant schema -> committed
  `2026_11_01_000001_create_message_attachments_table.php`.
- Chat photos do not require the four pending Return/Refund migrations.

All four pending migrations and the committed chat migration must be retained.
Do not assume shared migration status. Separately authorize a read-only schema
preflight and any later exact-path execution. Inspect duplicate lifetime item
requests, existing wallet identity indexes, foreign keys, release history and
CHECK/partial-index definitions. Stop on conflicts; do not delete/backfill/merge
history to force migration success. Quiesce relevant writers and coordinate
compatible app versions. Never run unrestricted migrate or Composer's setup script
against the shared configuration. A GitHub commit or push does not apply schema.

## Reproducible isolated checks

```powershell
php -d memory_limit=512M -d upload_max_filesize=6M -d post_max_size=40M vendor/phpunit/phpunit/phpunit --configuration phpunit.handoff.xml
node --test tests/JavaScript/*.test.cjs
```

The handoff test configuration reuses the test bootstrap that forces SQLite
`:memory:` and a unique private temporary directory before Laravel boots. It now
also supplies explicit temporary roots required by older Seller-return and
complaint fixtures. Tests may create/migrate schemas only inside SQLite memory;
no normal application connection or shared Supabase is used. Fake contacts,
temporary files and RAM-only S3 transport are used. Normal flags remain disabled;
test-only overrides exercise enabled behavior and are discarded with the process.

The smaller `phpunit.chat-photos.xml` still provides the focused photo suite.
The handoff suite also includes typed product/order chat entry-point tests. The
Seller order action renders `Message`; its older `Message Buyer` label assertion
was corrected to verify the actual visible submit button, retaining the route,
ownership, duplicate-conversation and session-fallback checks.
See `chat-photo-local-verification-and-handoff.md` for its commands and teammate
checklist. See `return-refund-phase4-workflow.md` for workflow details and
`message-attachments-deployment.md` for transport/rollback requirements.

Outstanding deployment acceptance: isolated PostgreSQL DDL and genuinely parallel
financial transactions, actual S3 operations/private-bucket verification, actual
Docker runtime, and desktop/mobile browser journeys. SQLite and JS DOM tests do
not establish those results. No browser success is claimed. The previous Composer
audit reported advisories in unchanged existing dependencies; review separately
before live activation rather than broadening this handoff into an unreviewed
framework upgrade.

## Safe deployment sequence for teammate

Verification recorded for this handoff (2026-11-06):

- Combined isolated PHP suite: **479 tests, 7,575 assertions passed**.
- JavaScript regressions: **187 tests passed**.
- Route audit: **453 routes**, with unique names/endpoints and defined controller actions.
- **26 relevant Blade views** compiled and their generated PHP passed syntax checks.
- **42 pending non-Blade PHP files** passed syntax checks, including the navigation test.
- Composer validation and staged/unstaged whitespace checks passed.
- No PostgreSQL, live S3, Docker runtime or actual browser acceptance was performed.

This handoff changed only the disposable fixture bootstrap, the outdated Seller
navigation assertion, this document and `phpunit.handoff.xml`. Existing application
implementation and protected configuration files were preserved.

1. Review the exact file inventory, dependency lock and all pending financial code.
   Do not omit untracked models, services, partials, configuration or migrations.
2. Complete isolated PostgreSQL and browser acceptance and dependency review.
3. Separately authorize shared read-only schema/storage inspection, then any
   specifically scoped migration/provisioning or existing-file transfer.
4. Deploy the complete compatible code and reviewed dependency lock with all
   processing/activation gates still off. Verify stable encryption key and both
   private-storage paths. Do not run `composer setup` or generate a new APP_KEY.
5. Verify schema constraints, codecs, upload limits and restart/redeploy persistence
   using separately authorized synthetic test data and accounts.
6. Activate chat only after its separate approval. Returns and earning releases
   each need their own schema/financial rollout approval. Actual refund payment
   remains a separate unimplemented feature.
7. Roll back by disabling writers/flags and retaining metadata, financial history
   and private files. Do not use destructive schema rollback where records exist.

## Exact pending-file review inventory

The following inventory is generated from current tracked modifications and
non-ignored untracked files. These are the combined earlier-phase implementation,
tests and documentation, not only this handoff's changes. Committed prerequisites
above are already in Git history and must remain there. No credentials, .env,
landing.js, vendor files, uploads or mobile-branch files belong in this inventory.

```text
Dockerfile
app/Filesystem/PrivateChatS3Filesystem.php
app/Http/Controllers/Messaging/ConversationController.php
app/Http/Controllers/Messaging/MessageAttachmentController.php
app/Http/Controllers/PayoutController.php
app/Http/Controllers/ReturnRefundController.php
app/Http/Controllers/ReturnRefundUiController.php
app/Models/ReturnRefundRequest.php
app/Models/ReturnRefundWorkflow.php
app/Models/SellerWalletEntry.php
app/Providers/AppServiceProvider.php
app/Services/Admin/ComplaintService.php
app/Services/CodRemittanceService.php
app/Services/FinancialSchemaReadiness.php
app/Services/MessageAttachmentService.php
app/Services/MessagingService.php
app/Services/MoneyReportService.php
app/Services/ReturnRefundAllocationService.php
app/Services/ReturnRefundService.php
app/Services/ReturnRefundWorkflowService.php
app/Services/SellerWalletService.php
composer.json
composer.lock
config/filesystems.php
config/returns.php
database/migrations/2026_11_06_000001_enforce_one_return_request_per_item.php
database/migrations/2026_11_06_000002_scope_refund_wallet_entries_to_requests.php
database/migrations/2026_11_06_000003_add_source_identity_to_earning_releases.php
database/migrations/2026_11_06_000004_create_return_refund_workflows.php
docker/php/chat-uploads.ini
docs/chat-photo-local-verification-and-handoff.md
docs/github-handoff-return-refund-chat.md
docs/message-attachments-deployment.md
docs/return-refund-phase2-deployment.md
docs/return-refund-phase4-workflow.md
phpunit.chat-photos.xml
phpunit.handoff.xml
resources/views/admin/financial-report.blade.php
resources/views/admin/returns/show.blade.php
resources/views/buyer/order-details.blade.php
resources/views/buyer/returns/create.blade.php
resources/views/buyer/returns/show.blade.php
resources/views/components/buyer-navbar.blade.php
resources/views/layouts/seller.blade.php
resources/views/messages/partials/attachment-style.blade.php
resources/views/messages/partials/photo-composer.blade.php
resources/views/messages/show.blade.php
resources/views/partials/return-journey.blade.php
resources/views/seller/messages/partials/thread.blade.php
resources/views/seller/messages/script.blade.php
resources/views/seller/messages/style.blade.php
resources/views/seller/returns/index.blade.php
resources/views/seller/returns/show.blade.php
resources/views/seller/settlements.blade.php
resources/views/seller/wallet/index.blade.php
routes/web.php
tests/Feature/CodRemittanceTest.php
tests/Feature/HeldEarningReconciliationTest.php
tests/Feature/MessageAttachmentsTest.php
tests/Feature/PhaseFourWalletHardeningTest.php
tests/Feature/PhaseThreeMessagingContextEntryPointTest.php
tests/Feature/PrivateChatS3StorageTest.php
tests/Feature/ReturnRefundIntegrityTest.php
tests/Feature/ReturnRefundJourneyTest.php
tests/Feature/ReturnRefundTest.php
tests/Feature/ReturnRefundUiTest.php
tests/Feature/SellerMessagesInboxTest.php
tests/Feature/SellerReturnsManagementTest.php
tests/Feature/SellerWalletPageTest.php
tests/JavaScript/chat-photo-composer.test.cjs
tests/JavaScript/message-images.test.cjs
tests/JavaScript/seller-inbox.test.cjs
tests/Support/InMemoryChatS3Transport.php
tests/Support/chat-photo-bootstrap.php
```

Inventory: 48 modified tracked files and 26 untracked files; nothing staged.
