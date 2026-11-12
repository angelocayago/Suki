# Lahat Meron demo importer

Implementation baseline: `b1ca626adb3c2e019bb83f0175bdadaad826b47c`.
This command is built/tested for disposable SQLite. No real store import has been authorized or performed.

## Bound target and scope

- `--seller-id=3` means `sellers.id`, not the owner User ID.
- `--expect-store="Lahat Meron"` is required and must match exactly.
- Resolve the owner using `sellers.user_id`. The preview prints its User ID; execution requires matching `--expect-owner-id`.
- Require an active, non-suspended Seller owner with verified contacts and an approved Seller store.
- Require the active assigned root `Others` and exactly one active direct child named `Miscellaneous`, also accepted by `SellerCategoryService`.
- Resolve category IDs from that relationship. Never create/reassign categories, users or stores.
- This is a CLI maintenance command for an authorized operator. Public identifiers alone do not confer authorization.

## Database guard

An explicit connection, matching application environment, and exact database confirmation are mandatory before any importer query. Only the actual `local` and `testing` environments and the SQLite driver are supported. Connection URLs, split read/write configurations, other drivers and production are refused. The command temporarily selects the confirmed connection for Eloquent/catalog/notification work and restores the previous default when finished. It never edits `.env`.

`:memory:` is allowed only in `testing`, with explicit `--confirm-database=":memory:"`. File-based SQLite requires an existing file and confirmation matching its canonical real path; the command does not create a database or migrate it. The approved existing Seller record must already be present in that database.

**PostgreSQL/shared Supabase is deliberately unsupported.** A later approval to populate a store on PostgreSQL would require a separately reviewed database-safety extension. Do not change the environment label to bypass a refusal. Real Seller 3 ownership, category and account state have not been queried in this phase.

## Dataset

`lahat-meron-v1.json` contains 60 original demo titles and descriptions across content groupings such as household items, groceries, stationery, apparel and pet items. These groups are descriptive metadata in this file only; every imported product uses the resolved Miscellaneous category.

- 36 simple products with one Default variant and no choices.
- 12 products with two choices, six with three choices, six with four combinations.
- Total: 60 products and 102 variant records.
- Prices are JSON integer `price_minor` values; stocks are JSON integers.
- The adapter formats exact major-unit decimal strings for the existing catalog service and verifies the resulting stored minor-unit prices within the transaction.
- `ProductOptions::creation()` validates and normalizes the existing option combinations. No alternate variant schema is introduced.

## Preview and separately approved execution

The commands below are templates for review, **not instructions to run against a real/shared database now**. Replace placeholders only with a separately approved existing SQLite target. Do not invent an owner User ID.

Default read-only preview:

```powershell
php artisan demo:products:import --seller-id=3 --expect-store="Lahat Meron" --connection="<approved-sqlite-connection>" --expect-environment=local --confirm-database="<exact-existing-SQLite-real-path>"
```

The preview displays the target, owner ID, category ID, product count/distribution and each product's action, name, variant count, price range in minor units and total stock. Review option-level details in the checked-in dataset. Dry-run does not call product creation and then roll back; it does not create products, variants, media, moderation events or notification intents.

Only after separate approval of that preview and database:

```powershell
php artisan demo:products:import --seller-id=3 --expect-store="Lahat Meron" --connection="<approved-sqlite-connection>" --expect-environment=local --confirm-database="<exact-existing-SQLite-real-path>" --expect-owner-id="<reviewed-owner-user-id>" --execute
```

The in-memory test runner owns its fixtures; a new shell process cannot access an already-disposed in-memory database. Do not use the real application's unconfirmed connection for testing.

## Existing product behavior

Creation uses `ProductCatalogService::create()`. All new products remain `PENDING` moderation, with active product/variant flags and the existing moderation event and Admin notification-outbox behavior. Nothing auto-approves or publishes the products. Admin notification intents may fan out once per newly inserted product per eligible Admin; reruns do not repeat submission events/intents for skipped items.

No product or variant image files/records are inserted. Existing catalog fallback behavior is retained. Its current generated placeholder uses `placehold.co`, so image display depends on an external placeholder service. No third-party product-photo hotlinks or private Seller media are persisted. Fully local placeholder behavior would need separately approved work.

## Repeat runs and failure behavior

Stable `suki-demo:lmNNN` references use the existing `(seller_id, legacy_reference)` unique index. Globally unique, deterministic `DEMO-LM-LMNNN-N` SKUs identify variants. A matching reference is skipped only when its description marker and expected SKU ownership establish demo provenance. Seller-edited names, stock and prices are never overwritten. Soft-deleted demo rows are reported and skipped without restoration. Removed provenance or missing/conflicting SKU records produce a refusal, not an automatic repair.

Non-demo reference/SKU collisions abort before inserts. No update/delete mode is provided. New product creation and reference assignment share one outer transaction, including moderation records/outbox intents. Preconditions are checked again inside that transaction. SQLite concurrent writer contention can fail safely and require a new preview; no automatic retry or claim of multi-process throughput is made. Existing unique constraints remain authoritative.

Progress marked "Prepared" is not committed until the final COMMITTED message. Unexpected exceptions produce generic operator feedback without raw SQL, credentials or internal exception text. Database rows roll back on a mid-batch failure. Existing notification delivery follows the application's normal after-commit behavior.

## Isolated verification

Run the new test file using the existing isolated bootstrap, which forces SQLite memory, temporary storage and fake external integrations before application startup:

```powershell
php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --bootstrap tests/Support/chat-photo-bootstrap.php --do-not-cache-result tests/Feature/ImportDemoProductsTest.php
```

The synthetic owner User ID is 41 while Seller ID is 3, and category IDs are 17/29, proving neither User ownership nor category identifiers are guessed. Tests cover exact counts/prices/options/stocks, pending visibility, catalog and Seller presentation, read-only previews, repeat runs, edited/archived demos, non-demo and foreign records, collisions, mid-batch rollback, notification intents, target/owner/category guards and remote-driver refusal before queries.

No migrations, packages, approval changes or existing production-file edits are required by this importer.
