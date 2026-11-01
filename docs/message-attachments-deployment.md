# Buyer/Seller image messaging: local setup and verification

## Deployment status: OFF and not production ready

`MESSAGE_ATTACHMENTS_ENABLED` defaults to `false`. No `.env` file is changed. Local/testing SQLite with no connection-URL override supports the isolated preview. PostgreSQL additionally requires the default-false `MESSAGE_ATTACHMENTS_DEPLOYMENT_VERIFIED` administrator attestation; it was NOT enabled. No disposable PostgreSQL tools/service were found locally. Runtime PostgreSQL verification remains outstanding; static analysis finds ordinary Eloquent FK/EXISTS/ordered relationship queries and no SQLite-specific attachment persistence SQL. Shared/Render uploads remain disabled. `APP_ENV=local` alone never establishes test isolation.

The backend requires the existing attachment table, JPEG/PNG/WEBP GD codecs, Fileinfo, and private local-disk configuration. Upload controls and image sends also require adequate PHP upload limits. Missing upload prerequisites leave ordinary text messaging usable. No flag was enabled in the project or shared deployment.

## Essential Windows local setup

The inspected CLI uses `C:\php\php.ini`. Change only these insufficient limits and restart the PHP/Laravel server:

```ini
upload_max_filesize = 5M
post_max_size = 32M
```

`max_file_uploads=20` and `memory_limit=128M` already meet this implementation's checked local requirements. The web-server process/configuration was not independently available for verification. No global PHP configuration was edited. For a local demo, use an isolated local database and enable `MESSAGE_ATTACHMENTS_ENABLED=true` only in that isolated environment. Do not change the shared `.env` or enable shared uploads for testing.

The private local disk is reused at `storage/app/private/message-attachments`; optional `MESSAGE_ATTACHMENTS_ROOT` selects a different private local directory. No object-storage provider was added.

## Pre-implementation storage gate

- Existing private storage patterns were found in delivery-proof and complaint-evidence controllers.
- The new `message_attachments` disk is local, rooted at `storage/app/private/message-attachments`, with private visibility, private directory visibility, generated filenames, `serve=false`, throwing I/O errors, and requested file/directory permissions 0600/0700. Roots in the public directory or configured linked public targets are rejected.
- Durable persistence across deploys, backups, shared volumes, multi-instance availability, and web-server aliases were NOT verified. A shared PostgreSQL database does not share local files. No Supabase Storage integration or S3 adapter is installed for this feature.
- On Windows, Unix mode bits/Flysystem visibility cannot prove Windows ACL privacy. The local adapter reported public mode visibility despite private write options; file access is still protected by private root placement and participant-authorized routes. Verify deployment ACLs independently.
- CLI PHP reports `upload_max_filesize=2M`, `post_max_size=8M`, and `memory_limit=128M`. These do not meet five 5 MiB uploads, and deployed web/proxy settings are unknown. Verify web PHP per-file limits of at least 5 MiB, request limits of approximately 32 MiB including multipart overhead, `max_file_uploads >= 5`, and corresponding proxy/server limits. This implementation does not modify INI or server configuration.

## Schema: already applied by the user; not rerun

The user reports `2026_11_01_000001_create_message_attachments_table` as `[6] Ran` on the intended Supabase database. Phase 4 did not rerun or modify that migration, create another migration, or query/write shared messaging records.

`2026_11_01_000001_create_message_attachments_table.php` creates:

- bigint `id` and `message_id` referencing canonical `messages.id`, cascading metadata deletion;
- integer `position`, bounded to 0–4;
- `disk` (50), `path` (255), `original_name` (255);
- allowlisted `mime_type` (JPEG/PNG/WEBP), integer `size`, `width`, `height`;
- `created_at` and `updated_at`;
- unique `(message_id, position)` and `(disk, path)` indexes;
- positive dimensions and size between 1 and 5,242,880 bytes.

PostgreSQL uses a CHECK constraint for bounds; the existing migration uses SQLite INSERT/UPDATE triggers. Phase 4 tests now build equivalent guarded SQLite memory fixtures directly, without executing any migration. PostgreSQL DDL/transactions are not thereby dynamically verified.

No existing message-body column or message types are altered. An image-only message stores an empty body after actual image validation. `TEXT` and `SYSTEM` retain their existing meaning. Do not run migrations with the shared project `.env` without separate coordination and authorization.

Rollback drops only the new metadata table, not messages or files. Before a real deployment rollback, disable the feature and preserve metadata/files deliberately; dropping metadata or cascading message deletion does not remove physical files and can strand images. No shared historical records were modified.

## Request and response contract

Existing Buyer/Seller `POST .../messages/{reference}/send` accepts optional `images[]` multipart uploads, with optional text body for image-containing requests. Text-only validation remains unchanged. Only Buyer/Seller portal participants may upload; other roles retain text-only sending.

All uploads must pass existing portal/policy checks and are reauthorized inside the canonical conversation transaction. Sender IDs, message IDs, disk names, and file paths are server-derived. The existing read cursor and last-message update remain in that transaction. No new notification behavior or durable send-idempotency mechanism is added.

When enabled and ready, send/poll JSON gains additive `attachments` and `preview` fields. Attachments contain IDs, positions, sanitized original names, MIME, size, dimensions, and authorized inline/thumbnail/download URLs. Stored disk/path fields are hidden. Image-only previews derive Photo/number-of-photos from actual related metadata. Text captions retain their normal preview. Other message fields and forward/older cursors are preserved. When disabled attachment fields are omitted, preserving pre-migration text behavior.

Original names are still untrusted display text: future UI must escape them or use `textContent`, never HTML insertion.

Each existing messaging portal receives these GET routes, under its existing auth, suspension, approval, and role middleware:

- `/messages/{reference}/attachments/{attachment}`
- `/messages/{reference}/attachments/{attachment}/thumbnail`
- `/messages/{reference}/attachments/{attachment}/download`

Every delivery checks the current portal participant and context ownership, then scopes the attachment through its message to that conversation. Admins require actual authorized participation. IDs, storage paths, or signed URLs do not substitute for authorization. Delivery uses generated filenames, validated stored MIME, inline/attachment disposition, `nosniff`, sandbox CSP, and private/no-store caching. Missing files return 404; ordinary storage failures have generic responses.

## Image processing and limits

- At most five files, each at most 5 MiB; extension and detected MIME must agree.
- At most 4096 pixels per dimension and 4 megapixels; valid decode required.
- A conservative per-image memory-budget check runs before decoding. Processing is sequential, never retaining five decoded images together.
- GD decodes and re-encodes raster pixels, dropping incoming EXIF/GPS/text metadata. JPEG/WEBP output is recompressed; originals are not retained as separate raw files.
- EXIF/Imagick support is unavailable locally. EXIF orientation is NOT preserved or guaranteed; portrait images relying on orientation metadata may rotate. No new dependency or external processing service was installed. Unsupported animated files that cannot be decoded are rejected.
- Normalized images must remain at most 5 MiB. A private thumbnail, at most 320 pixels on its longest edge, is stored beside each normalized image with a server-derived `-thumb` filename. No image bytes enter the database.

## Failure handling and remaining gates

Ordinary validation, decode, filesystem, and database failures roll back message/attachment metadata and attempt deletion of all newly written originals/thumbnails. Late-file failure cleanup is tested. Errors do not return storage paths. Failed cleanup is logged internally.

Filesystems and database commits are not one distributed transaction. Process termination, ambiguous commit outcomes, cleanup failures, outer transaction rollback, or later message deletion can leave orphan files. No automatic orphan-deletion worker was added; define and test an age-delayed, metadata-aware operational cleanup process before production. Do not blindly delete directories.

Durable send idempotency, PostgreSQL concurrent behavior, shared durable storage, Windows/deployment ACLs, actual web upload-limit verification, and orientation handling remain deferred. The existing send throttle remains 30/minute. No live test messages were submitted.

## Integrated Buyer/Seller frontend

Buyer and Seller share the picker, drag/drop/paste processing, five-image/5 MiB client validation, removable blob previews, multipart XHR transport, measured upload progress, private thumbnail grid, +N overlay, and modal gallery with keyboard navigation/focus restoration/protected downloads. Upload progress reaching 100% means uploaded/processing, not a successful message. Only the server's successful response clears the draft. Network timeouts retain caption/files and warn of an ambiguous result; no automatic POST retry or exactly-once guarantee is claimed.

Server-rendered and polled galleries use authorized URLs, lazy loading, reserved thumbnail dimensions, error fallbacks, and escaped names. Related metadata is eager-loaded; previews do not issue per-message queries. Seller filters/unread/scroll/history/composer behavior and other portal text contracts remain covered by regression tests. No attachments are advertised when upload prerequisites are unavailable. Normal multipart forms remain usable without JavaScript.

Browser automation was unavailable; visual/mobile verification remains outstanding. Automated HTTP/SQLite/disposable-file tests and JS DOM/transport tests provide functional coverage, not a claim of a real browser or PostgreSQL deployment test.

Isolated tests were run with per-process PHP limits, without changing installed INI files:

```text
php -d upload_max_filesize=5M -d post_max_size=32M vendor/phpunit/phpunit/phpunit --do-not-cache-result tests/Feature/MessageAttachmentsTest.php tests/Feature/SellerMessagesInboxTest.php
node --test tests/JavaScript/message-images.test.cjs tests/JavaScript/seller-inbox.test.cjs
```
