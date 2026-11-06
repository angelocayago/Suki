# Buyer/Seller chat photos: local verification and teammate handoff

## Reproduce safely

Run from the project root with dependencies installed:

```powershell
php -d memory_limit=512M -d upload_max_filesize=6M -d post_max_size=40M vendor/phpunit/phpunit/phpunit --configuration phpunit.chat-photos.xml
node --test tests/JavaScript/chat-photo-composer.test.cjs tests/JavaScript/message-images.test.cjs tests/JavaScript/seller-inbox.test.cjs
```

The dedicated PHPUnit bootstrap forces SQLite `:memory:` and private local storage
under a unique owned temporary directory before Laravel starts. Test schemas are
created directly; no migrations run. Normal DB connection values, mail delivery,
queue/broadcast services and application storage paths are not used. Messaging
overrides occur only inside isolated tests; both financial flags remain false.
The directory is removed at shutdown only after verifying its path and ownership
marker. The suite also contains existing RAM-only S3 transport regressions; they
do not connect to Supabase or require a real object storage service.

The new HTTP journey follows normal Buyer/Seller inbox links into the real chat
pages and sends image-only messages and captions through both upload routes.
It reloads conversation pages and polls stored attachment metadata, retrieves
full images/thumbnails/downloads, and denies guests, unrelated participants and
cross-role access. Pre-existing files are preserved. Existing security tests cover
invalid/corrupt/oversized images, DB failures and new-file cleanup.

JavaScript tests exercise select/replace/remove previews, multipart photo-only and
captioned sends in both controllers, gallery enlargement/download links, text
regressions and responsive grid structure. A reproduced cached-page preview bug
was fixed: pagehide releases the old blob URL and persisted pageshow rebuilds it.

Local CLI has GD JPEG/PNG/WebP, Fileinfo and PDO SQLite. Its default upload limits
are 2M per file and 8M POST, insufficient for the existing backend. The command
above overrides them only for that test process; max_file_uploads remains 20.
No global PHP INI or application .env edits are needed.

Browser automation returned an unavailable browser access host. These are HTTP,
Blade, JavaScript and CSS structural results, not a visual/browser acceptance run.
Actual desktop/mobile widths, alignment, native chooser interaction and pixel
overflow must still be checked in a browser. No normal application server or
production account was used for local verification.

Phase 4.6 results: 31 isolated PHP tests passed with 1,086 assertions. All 187
repository JavaScript tests passed (the three chat test files contain 27 tests).
Route audit verified 453 unique routes and controller actions. Thirteen chat
Blade views compiled and passed PHP syntax checks; changed PHP files and Git
whitespace checks passed. The new HTTP round trip alone covers 222 assertions.
Temporary storage cleanup was verified. No live activation is implied.

## Deployment checklist — teammate responsibility, separate authorization

- [ ] Review and deploy only the complete chat-related code/dependencies/runtime
  changes; preserve and exclude unfinished financial work as appropriate.
- [ ] Verify existing `message_attachments` schema, FK, uniqueness and bounds, and
  `2026_11_01_000001_create_message_attachments_table.php` status. Do not rerun it
  blindly or use unrestricted migrations. No financial migration is required.
- [ ] If Supabase Storage is selected, provision a PRIVATE dedicated bucket and
  verify anonymous access denial. Server S3 keys bypass RLS; never expose them to
  browsers, source control or logs. Verify unsupported ACL handling against the
  actual service before attesting readiness.
- [ ] Configure deployment-only names, without publishing secret values:
  `CHAT_PHOTOS_STORAGE_DRIVER`, `CHAT_PHOTOS_S3_ENDPOINT`,
  `CHAT_PHOTOS_S3_REGION`, `CHAT_PHOTOS_S3_BUCKET`,
  `CHAT_PHOTOS_S3_ACCESS_KEY_ID`, `CHAT_PHOTOS_S3_SECRET_ACCESS_KEY`,
  `CHAT_PHOTOS_S3_PRIVATE_BUCKET_VERIFIED`.
- [ ] For a private persistent local backend instead, use the `local` selection
  and verified `MESSAGE_ATTACHMENTS_ROOT` outside public paths. An ephemeral
  Render directory does not establish durable storage.
- [ ] Verify built Docker GD JPEG/PNG/WebP and Fileinfo functions, web PHP and
  proxy request limits: at least 5M per image, 32M POST allowance and five files.
  Reviewed container settings are 6M/40M/20. Test processing memory bounds.
- [ ] Assess existing attachment files before changing backend. Repointing the
  disk alias does not copy old originals or thumbnails; separately authorize any
  verified transfer and retain old copies until acceptance.
- [ ] In an authorized test deployment, send synthetic photos from Buyer to
  Seller and Seller to Buyer, including image-only and captioned messages. Check
  preview/change/remove, full-size viewer, refresh and denied outsider access.
- [ ] Record normalized-image/thumbnail hashes, restart and redeploy the test
  service, then verify the same attachments and permissions again.
- [ ] Only after separate approval activate `MESSAGE_ATTACHMENTS_ENABLED` and
  `MESSAGE_ATTACHMENTS_DEPLOYMENT_VERIFIED`, with verified storage configuration.
  Refresh cached configuration/restart affected processes as required. Keep
  `returns.backend_enabled` and `returns.earning_release_enabled` false.
- [ ] Roll back by disabling messaging and preserving metadata and files. Do not
  drop the attachment table or delete the bucket; returning to local storage
  requires verified copies of newly remote files.

See `message-attachments-deployment.md` for full storage details and the existing
dependency-security advisories. This checklist provisions or activates nothing.
