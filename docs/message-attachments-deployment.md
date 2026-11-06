# Private Buyer/Seller chat photos — Phase 4.5

This is local implementation documentation, not authorization to provision or
activate a live service. It supersedes the earlier local-only and multi-image
composer descriptions. The current visible composer selects one image; the
existing backend and historical gallery support up to five.

## Current boundaries

- Logical disk remains `message_attachments`; the default backend remains local.
- Messaging activation and deployment-verification flags remain OFF by default.
- Both Return/Refund financial flags remain false and are independent of photos.
- No live bucket, credential, database connection, image upload, migration, Render
  change or financial operation was performed for Phase 4.5.
- Tests use SQLite memory schemas created directly and an in-memory HTTP transport.
  No real S3-compatible service, Supabase integration, Docker image build or
  persistence-after-redeployment test has been executed.

## Backend configuration

`CHAT_PHOTOS_STORAGE_DRIVER=local` retains the existing private local filesystem.
`MESSAGE_ATTACHMENTS_ROOT` optionally sets its private root. Existing public-root
and linked-public-target rejection remains intact. Local development uploads are
not a claim of deployed durability or Windows ACL verification.

For deployment, the supported external selection is
`CHAT_PHOTOS_STORAGE_DRIVER=s3`. It maps to the dedicated internal driver
`chat_private_s3`; other application storage disks are unchanged.

Required deployment-only settings (no values or credentials supplied here):

- CHAT_PHOTOS_S3_ENDPOINT
- CHAT_PHOTOS_S3_REGION
- CHAT_PHOTOS_S3_BUCKET
- CHAT_PHOTOS_S3_ACCESS_KEY_ID (server secret)
- CHAT_PHOTOS_S3_SECRET_ACCESS_KEY (server secret)
- CHAT_PHOTOS_S3_PRIVATE_BUCKET_VERIFIED (defaults false)

The endpoint must be HTTPS on the hosted Supabase project hostname, optionally
its `.storage.supabase.co` hostname, with `/storage/v1/s3`, no userinfo, custom port,
query or fragment. Arbitrary S3 hosts and self-hosted/local HTTP endpoints are
intentionally not enabled by this adapter. The test uses a fake Supabase-shaped
hostname intercepted entirely by a RAM-only transport; no DNS is performed.

Use a dedicated private bucket, conservative S3-compatible bucket naming, its
actual project region, and path-style addressing. Explicit nonempty credentials
are required; the implementation does not fall back to normal AWS credential
providers. No credentials belong in version control, browser variables, URLs or
logs. S3 server keys have broad project Storage privileges and bypass RLS; they
are not per-conversation user credentials. Laravel performs participant checks.

`PRIVATE_BUCKET_VERIFIED` is an operator attestation, not an automated privacy
probe. It must remain false until an authorized person has verified bucket
privacy and anonymous access denial. Missing/invalid settings fail closed before
object storage is created. The existing `MESSAGE_ATTACHMENTS_ENABLED` and
`MESSAGE_ATTACHMENTS_DEPLOYMENT_VERIFIED` are separately required for PostgreSQL.
Neither backend readiness nor this implementation enables those flags.

Official references:
- https://supabase.com/docs/guides/storage/s3/authentication
- https://supabase.com/docs/guides/storage/s3/compatibility
- https://supabase.com/docs/guides/storage/buckets/fundamentals
- https://laravel.com/docs/12.x/filesystem

## Official adapter and ACL compatibility

The official `league/flysystem-aws-s3-v3` dependency and AWS SDK are used through
Laravel's S3 filesystem factory. There is no custom network protocol or REST
storage implementation. The dedicated driver adds narrowly scoped command
middleware: remove the default private object ACL before wire serialization,
reject public ACLs and grant headers, and reject bucket/ACL administration.
Supabase does not implement S3 object ACL headers. Bucket privacy is enforced by
private bucket configuration and verified deployment controls, not by a successful
object visibility API call.

Only bounded attachment data operations are permitted. SDK configuration has TLS
verification, finite timeouts, no configured retries, explicit credentials,
path-style requests and checksums only when required. Real-provider compatibility
still needs separate isolated verification; mocked HTTP serialization is not proof
of Supabase service behavior. Never weaken the bucket to fix an upload error.

Direct `url`, temporary download URL and temporary upload URL generation on this
dedicated disk are disabled. Browser JSON contains existing Laravel routes only.
The filesystem alias and random relative object/thumbnail keys remain unchanged.

Lockfile additions only, with no existing package version changed:
- league/flysystem-aws-s3-v3 3.35.3
- aws/aws-sdk-php 3.403.0
- aws/aws-crt-php 1.2.7
- mtdowling/jmespath.php 2.9.2
- symfony/filesystem v7.4.23

The new packages' PHP requirements fit PHP 8.3. Use the reviewed lockfile with
`composer install`; do not resolve dependencies on deployment. Composer scripts
were disabled during local installation. CLI ZIP was enabled only for that
Composer process; no installed INI was changed.

Composer's read-only audit reports 13 advisories across seven existing unchanged
packages: laravel/framework, league/commonmark, symfony/http-foundation,
symfony/http-kernel, symfony/mime, symfony/routing and symfony/yaml. None were
reported for the five new dependencies. A separate reviewed dependency-security
update remains outstanding; no unrelated framework update was attempted here.

## Delivery, validation and cleanup

Buyer and Seller use the same disk, service and activation rules. Existing
conversation policy, role/context ownership, active-account checks and CSRF remain.
Every original, thumbnail and download request goes through authenticated Laravel
routes and scopes the attachment to the authorized conversation. Private/no-store,
validated Content-Type, generated download names, CSP and nosniff are preserved.
Revoked/unrelated participants and guests cannot use a stored object key as access.

Images remain limited to JPEG/PNG/WebP, 5 MiB each, 4096 pixels per side and four
megapixels. Detected MIME and extension must agree. GD decodes and re-encodes
pixels; input metadata is not retained. Random normalized-image keys and private
320px thumbnails are preserved. EXIF orientation is not guaranteed. A memory-budget
guard remains in force; test actual web memory use with worst-case accepted files.

Message metadata, read cursor and conversation update remain transactional. Files
are external side effects: failed writes attempt to delete only newly generated
keys. A later thumbnail failure must remove its new original and roll back the
message. Existing files are preserved. Persistence, read and cleanup warnings
are generic and omit SDK exceptions, endpoints, credentials and internal paths.

Filesystem and database commits are not distributed transactions. Process crashes,
ambiguous commit results or failed deletion may leave private orphan objects.
There is no automatic orphan-deletion worker. Use an explicitly authorized,
age-delayed, metadata-aware reconciliation process, never a blanket prefix purge.

## Container runtime

Dockerfile adds libwebp-dev and GD --with-webp. Existing JPEG/PNG and Fileinfo
requirements remain. Verify the actual built image's encode/decode functions.
The container-only docker/php/chat-uploads.ini sets:

    upload_max_filesize = 6M
    post_max_size = 40M
    max_file_uploads = 20

These satisfy the unchanged backend minima of 5M per file, 32M POST allowance (or
unlimited) and five uploads. The request allowance covers the existing five-file
backend, not merely today's one-photo UI. Verify Apache/proxy limits as well.
This file does not change global Windows PHP settings. No Docker build was run.

## Schema and existing files

Use the already committed migration:
`2026_11_01_000001_create_message_attachments_table.php`.
It depends on messages and the existing messaging schema, not the four pending
Return/Refund financial/workflow migrations. No new database migration is needed.
An earlier document reported that the user had applied the attachment migration;
its actual current shared status remains independently UNVERIFIED.

Switching the backend behind `message_attachments` does NOT move local images.
Before switching, separately authorize a metadata-only inventory and private-file
verification. If files exist, copy each referenced normalized image and its
thumbnail to the exact same relative key, verify byte count and SHA-256, and retain
the old files until acceptance. Do not inspect private message content or rewrite
attachment rows as a shortcut. Do not assume an empty bucket means no old records.

## Separate rollout and verification

1. Prepare a reviewed chat-only patch from the committed baseline; preserve the
   current dirty working tree and exclude Return/Refund financial changes.
2. Test the adapter with disposable credentials/bucket/database, synthetic images
   only. Include upload/read/delete, ACL compatibility, validation, failed-write
   cleanup and failed-read errors. Actual provider access needs separate approval.
3. Separately authorize and verify existing attachment schema/constraints, private
   bucket settings, anonymous denial, secret handling, quotas and durable storage.
4. Resolve any existing-file transfer before changing the disk configuration.
5. Deploy the complete chat code, dependency lockfile and image runtime with gates
   still off. Use deployment secret configuration, not committed credentials.
6. Verify through two synthetic participant accounts: photo-only and captioned
   messages, original/thumbnail/download, refresh and browser/mobile layout.
   Unrelated Buyer/Seller and guest access must fail.
7. Record stored normalized-image/thumbnail digests, restart the disposable app,
   then redeploy it. Reopen the same messages and verify identical bytes and
   current participant authorization. Repeat across instances if applicable.
8. Only after separate approval set the private-bucket attestation and the two
   messaging gates, refresh application config and restart processes as needed.
   Keep both financial flags false. No refund migration or activation is implied.

Rollback: disable messaging first, retain metadata and both stores, and revert only
the reviewed chat code/config if necessary. Do not drop the attachment table or
remove a bucket. Reverting to local requires verified local copies of any new
remote objects; otherwise those messages will lose image access. Photo-only
messages may appear empty while the feature is disabled. Do not silently fall
back to another store when an object is missing.

## Verification limits

Mocked transport tests establish Laravel/SDK request serialization and application
behavior, not actual Supabase bucket privacy, persistence or PostgreSQL concurrency.
Browser/mobile acceptance, actual Docker execution and real S3 integration remain
outstanding. No live credentials were read and no real images were uploaded.

Phase 4.5 verification: 33 isolated PHP tests passed with 915 assertions;
183 JavaScript tests passed. Route audit verified 453 routes; relevant Blade
compilation, PHP syntax and whitespace checks passed. No database migrations were
executed; test schemas were constructed directly in SQLite memory. Composer
manifest/lock validation passed, while security audit remains nonzero for the
existing advisories listed above. Docker and real-provider integration were not run.
