# Lahat Meron demo importer: Phase D2

Implementation baseline: `77236918e6d1158dec10a130c8cf4360fe56f9c1`.
D2 implements and tests the guarded paths only. No shared-database import,
approval, media upload or live query was performed during D2.

## Fixed target and dataset

- Explicit `--seller-id=3` means sellers.id, not users.id.
- Exact store name: Lahat Meron. Resolve the owner through sellers.user_id.
- Require an active, unsuspended Seller owner with verified contacts and an approved store.
- Resolve the active assigned Others root and exactly one active direct Miscellaneous child;
  enforce SellerCategoryService. No users, stores or categories are created or reassigned.
- The existing JSON dataset is unchanged: 36 simple products, 12 with two variants,
  six with three, six with four: 60 products / 102 variants.
- JSON prices are authoritative integer minor units. ProductOptions and the existing
  ProductCatalogService normalize options and create products; stored prices are verified.
- Stable references: suki-demo:lm001 through suki-demo:lm060. Deterministic global SKUs:
  DEMO-LM-LM001-1, etc. No unrelated products are overwritten or deleted.

The prior D1 read-only findings were point-in-time observations, not permission
or a guarantee that the target remains eligible. Every future run checks again.

## SQLite compatibility

Explicit connection, matching environment and exact database confirmation remain
mandatory. SQLite is permitted only in local/testing; URLs and split read/write
connections are refused. File databases must already exist and match their canonical
real path. :memory: is allowed only in testing with explicit confirmation. Execution
requires the reviewed owner ID. The command never migrates or creates a database.
It restores the previous default connection on exit and never edits .env.

## PostgreSQL guards

The separate PostgreSQL path requires:

1. Explicit connection, exact application environment and database name confirmation.
2. Exact pinned project `yzvcdrjriziejmecewci` and explicit reviewed owner ID.
3. A direct project endpoint with postgres login, or Supabase pooler with the exact
   postgres.<project-reference> tenant login. Alternate schemas, split routing and
   connect-via overrides are refused.
4. `sslmode=verify-full`, a trusted certificate chain and hostname verification.
   Plain require/verify-ca are refused. Existing credentials/configuration are never
   changed by this feature. A trusted CA setup is a separate operator prerequisite.
5. Effective resolved connection configuration must match the reviewed routing,
   protecting against stale cached connection objects. The connected server must
   report the confirmed database, postgres session role and expected transaction mode.
   Project routing is established through the authenticated, hostname-verified endpoint
   and project-specific direct hostname or pooler tenant; database name alone is insufficient.
6. All three unconditional unique indexes: products.slug,
   products(seller_id, legacy_reference), product_variants.sku.
7. A server-enforced REPEATABLE READ, READ ONLY preflight, bounded timeouts and rollback.
8. Exact target eligibility, category, asset integrity, global SKU and base-slug collision
   checks, and scoped demo-reference provenance checks.
9. On execution, the separate typed phrase and reviewed SHA-256 plan fingerprint.
   The fingerprint binds routing, target owner/category, dataset, manifest and actions.
10. A fresh write transaction with owner/store/category locks, repeated server/target/
    asset/collision checks and fingerprint comparison before creating products.

Configuration parsing is not itself a live verification. Connection/certificate,
server and schema checks must succeed on the actual future run. D2 does not claim
PostgreSQL runtime or concurrent-execution verification.

## Future commands: NOT EXECUTED

These are review templates, not current authorization to contact or write to Supabase.
Replace placeholders only after separately approving the target, owner and environment.
The configured connection must already meet the TLS requirements above; do not relabel
an environment or bypass guards to force a run.

Default PostgreSQL dry-run (server-enforced read-only):

```powershell
php artisan demo:products:import --seller-id=3 --expect-store="Lahat Meron" --connection=pgsql --expect-environment="<reviewed-APP_ENV>" --confirm-database=postgres --expect-project=yzvcdrjriziejmecewci --expect-owner-id="<reviewed-owner-user-id>"
```

Review the complete 60-row table, target/category/owner, insert/skip counts and plan
fingerprint. Review variant-level prices/options/stocks in the unchanged dataset.
Obtain separate explicit approval for the reviewed write plan. Then, NOT EXECUTED:

```powershell
php artisan demo:products:import --seller-id=3 --expect-store="Lahat Meron" --connection=pgsql --expect-environment="<reviewed-APP_ENV>" --confirm-database=postgres --expect-project=yzvcdrjriziejmecewci --expect-owner-id="<reviewed-owner-user-id>" --execute --confirm-execution="IMPORT 60 LAHAT MERON DEMO PRODUCTS" --confirm-plan="<reviewed-plan-sha256>"
```

Execution includes a fresh read-only preview, compares its fingerprint and then repeats
preflight under write locks. A changed plan aborts and requires a new review.
Owner identity is obtained through sellers.user_id, never guessed from Seller ID 3.
The CLI is for an authorized operator; public IDs and confirmation text are not credentials.

## Atomicity, idempotency and moderation

All new rows, variants, image associations, references, moderation events and normal
notification intents share the existing catalog service and outer transaction.
New products remain PENDING. Import never approves products. Mid-batch database failure
rolls back; there are no runtime file writes to clean up. Existing unique constraints
remain authoritative against conflicting inserts. Bounded lock waits can abort safely;
there is no automatic retry or promise of concurrent throughput.

Existing references are skipped only when the description marker and expected SKUs
establish provenance/ownership. Seller edits and archived demos are never overwritten
or restored. Foreign SKU, reference-provenance and new-product slug collisions refuse
execution. Existing media is never replaced or backfilled. A rerun can legitimately
produce a changed insert/skip plan requiring a newly reviewed fingerprint.

Progress marked Prepared is uncommitted until COMMITTED. Errors do not print credentials,
connection strings or raw exception text. Normal notification delivery remains governed
by the application's existing after-commit behavior.

## Portable committed images

The unchanged authoritative manifest/source images remain in images/. Published copies
are in `public/images/demo-products/lahat-meron/`: **60 WebPs, 1,227,734 bytes total**.
Both sets must match SHA-256 checksums, dimensions and WebP decoding. GD/WebP validation
is required by preflight; deployment web servers only need to serve the static files.

New image rows store `images/demo-products/lahat-meron/lmNNN.webp`, position 1.
The scoped resolver returns origin-relative URLs (including the current installation's
base path). Cart/Wishlist/new order snapshots therefore need no developer hostname.
Historical absolute URLs for this reserved namespace are normalized on presentation.
Normal unrelated media paths retain their existing behavior.

Catalog/details, Seller galleries/listings, Admin image markup, Wishlist, Cart summary,
purchases, reviews and reports use the same reserved-path resolver. No global URL
rewrite or new media storage architecture is introduced. Legacy Phase C storage paths
are not silently rewritten or backfilled.

After one separately authorized import/approval, groupmates only need the same code
and committed assets with Laravel public/ served normally. They do not run the importer,
copy files manually, or need storage:link for these demo images. Ordinary Seller uploads
still require their existing disk/symlink or persistent storage setup. These static
assets are public demo media; they are not suitable for private or frequently edited uploads.

## Separate Admin approval: NOT EXECUTED LIVE

An authenticated eligible Admin opens Product moderation > Review Lahat Meron demo batch
(GET /admin/products/demo/lahat-meron; mirrored under the existing Superadmin portal).
PostgreSQL preview is read-only. The server resolves exact Seller 3 and all 60 references;
client-supplied product/Seller IDs are prohibited. The preview lists names, references,
variant counts and statuses; it provides a fingerprint and separate confirmation field.

After separate authorization, Admin types `APPROVE LAHAT MERON DEMO` and submits.
The transaction refreshes/locks the authorized actor, owner, store, categories and batch,
revalidates target/provenance, exact products/variants/images and fingerprint, then calls
ProductModerationService::approve() for each eligible PENDING entry. APPROVED entries
are skipped. Rejected/hidden/archived/missing/conflicting entries block the entire batch.
All approval history/outbox side effects roll back on failure. Replay skips prior approvals
without duplicating events/notifications. Approved stock may decrease through normal sales;
pending stock must still match the dataset. No direct status bypass or auto-approval setting.

## Isolated verification and limitations

Use only the existing isolated SQLite/bootstrap, synthetic accounts, fake integrations
and disposable storage:

```powershell
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --bootstrap tests/Support/chat-photo-bootstrap.php --do-not-cache-result tests/Feature/ImportDemoProductsTest.php tests/Feature/DemoProductPhaseD2Test.php
```

Tests cover target/category/collisions, exact counts/prices/options/stocks, rollback,
idempotency, published integrity, portable media/snapshots, authorization, confirmation,
moderation reuse and replay. PostgreSQL configuration/server guards are mocked; no
independently disposable PostgreSQL server was available, so actual PostgreSQL SQL,
TLS, locks and concurrent execution remain unverified. No browser visual verification.

The complete Admin product-detail render is blocked by the committed PHT Blade syntax
error at line 110. The actual changed image fragment is compiled/rendered separately;
the full-page D2 test is explicitly skipped while that baseline error exists.
The new batch page and existing index compile/render successfully.

Running the existing PhaseThreeProductModerationTest and SellerProductFormsTest produced
36 tests / 540 assertions / six failures. An exported committed HEAD with isolated path
plumbing produced the same six failures: legacy category/transition/selection contracts
and the existing Admin Blade error. No unrelated baseline repairs or weakened assertions.

The four existing importer test contracts changed only for static media: importer no
longer writes runtime public-disk files, old destination files remain untouched, remote
runtime disks are irrelevant, and media metadata uses portable paths. Database rollback,
ownership, counts, prices and no-overwrite assertions remain in place.


### Existing test-contract updates

| Test (ImportDemoProductsTest) | Previous expectation | D2 expectation | Reason |
| --- | --- | --- | --- |
| test_import_creates_exact_distribution_and_reuses_catalog_inventory_and_pending_moderation | 60 runtime disk files and storage URLs | 60 matching committed public files, portable image records/URLs, zero runtime writes | Approved static publication strategy |
| test_partial_storage_failure_rolls_back_and_removes_only_new_media ? test_static_import_never_calls_runtime_media_storage | Third media write fails and rolls back | Runtime write/delete methods are never called; records succeed and prior files survive | Runtime file-copy operation removed |
| test_preexisting_destination_is_never_overwritten_or_cleaned ? test_legacy_runtime_destination_is_untouched_by_static_import | Existing storage destination blocks import | Import succeeds and prior storage file stays byte-for-byte intact | Static images cannot overwrite that destination |
| test_nonlocal_public_disk_is_refused_before_storage_writes ? test_static_images_do_not_require_the_runtime_public_disk | Nonlocal runtime disk blocks import | Import succeeds without runtime storage access | Demo assets are public repository files |

Final inventory coverage additionally ran SellerInventoryFunctionsTest and
PhaseThreeInventoryUnificationTest: 17 tests / 178 assertions / one failure.
The same failure and counts occur on exported committed HEAD:
test_checkout_uses_stock_after_serialized_seller_adjustments omits mandatory
cart_selection. Together with the six moderation/form baseline failures above,
seven pre-existing failures were observed and left unchanged.


### D2 verification results

- Importer + D2 tests: 34 tests / 1,559 assertions / one explicit baseline skip.
- After adding public-store image coverage: D2 + PublicStorefrontTest: 26 tests /
  264 assertions / the same one baseline skip. These runs overlap; do not sum them.
- Catalog, Seller listing, product details/Wishlist, purchases and inventory: 57 tests /
  921 assertions / one confirmed unchanged baseline inventory failure (56 passed).
- Existing moderation/forms: 36 tests / 540 assertions / six confirmed baseline failures.
- Relevant JavaScript: 49 passed. Inline approval script syntax: passed.
- Vite production build: passed (66 modules). PHP syntax and route checks: passed.
- New approval, Admin index and public-store Blade compile/generated PHP parse: passed.
  Admin detail full compilation retains its baseline PHT parse failure.
- All 60 published image checksums match; unchanged source dataset/manifest/WebPs.
- git diff --check: passed. No staged files; no protected files changed.
- No live PostgreSQL execution, TLS handshake, concurrency or browser visual test.

### Changed-file inventory

Modified:

- app/Console/Commands/ImportDemoProducts.php
- app/Http/Controllers/Buyer/WishlistController.php (reserved image presentation only)
- app/Services/CheckoutService.php (reserved image presentation only)
- app/Services/ProductCatalogService.php
- app/Support/BuyerPurchases.php
- app/Support/DemoProductImages.php
- app/Support/SellerReports.php
- app/Support/SellerReviews.php
- database/demo-products/README.md
- database/demo-products/images/README.md
- resources/views/admin/products/index.blade.php
- resources/views/admin/products/show.blade.php
- resources/views/buyer/store.blade.php
- routes/web.php
- tests/Feature/ImportDemoProductsTest.php

Created:

- app/Http/Controllers/Admin/DemoProductApprovalController.php
- app/Support/DemoImportDatabase.php
- app/Support/DemoProductBatch.php
- resources/views/admin/products/demo-approval.blade.php
- tests/Feature/DemoProductPhaseD2Test.php
- public/images/demo-products/lahat-meron/lm001.webp through lm060.webp (60 assets)
