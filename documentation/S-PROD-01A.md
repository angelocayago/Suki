# S-PROD-01A: Automatic and stable seller product SKUs

Implemented locally on `seller-registration`. Existing uncommitted work was retained. No staging, commits, push, branch changes, migrations, configuration changes, or development data writes were performed.

## Architecture and development data audit

The sole authoritative field is `product_variants.sku`. A simple seller product has one default ProductVariant holding its SKU, price and stock. Products have no SKU column; `seller_listing_data` excludes SKU. Seller Product Management and Inventory obtain their displayed/searchable SKU from the first active variant through `SellerProducts::listing()`. CartItem and OrderItem reference the variant ID, not a duplicated SKU field. No dedicated SKU correction workflow was found outside the ordinary seller forms.

The original variant migration defines a non-null string SKU and a global unique index, `product_variants_sku_unique`. A read-only inspection confirmed that index in the connected SQLite database. Uniqueness covers all sellers/products and includes soft-deleted variants. No new constraint or redundant column is necessary.

The audit found three variants with exact SKUs `001`, `005` and `S9-P3`. There were no blank/missing values, duplicate SKUs, products without an active variant, or SKU keys in product JSON. All historical values are retained without normalization or reallocation.

## Generation and concurrency

Generation keeps the existing project convention: `S{seller_id}-P{persisted_product_id}`. Both IDs come from trusted persisted entities; the product is inserted inside the existing save transaction before SKU allocation. Visible product counts and guessed next IDs never participate. Every newly created product, including a draft, receives one default variant and one persisted SKU.

The new `SellerProductSku` helper inserts the default variant using the existing global unique index as the authority. It does not depend on a pre-insert availability check. The first attempt uses the base above. If a legacy or competing row already holds it, subsequent attempts append `-` plus eight uppercase random alphanumeric characters generated using Laravel's secure random-string implementation. There are at most five allocation attempts. Each attempted insert uses a nested transaction/savepoint so a uniqueness violation can roll back cleanly before another attempt, including on engines that abort a transaction after a constraint error.

On exhaustion, a validation error explains that allocation failed and asks the seller to save again. The outer transaction rolls back the product and related rows; existing upload cleanup removes only files created by that failed request. Product save transactions permit at most three attempts for database-recognized concurrency errors. Existing values are never overwritten to resolve a conflict.

Two synchronized PHP processes exercise this allocator against a dedicated temporary SQLite database. They allocate distinct database product IDs and SKUs successfully. Separate feature tests force actual global unique-index collisions, including a soft-deleted record, and exhaust all five attempts to verify bounded retry and cleanup. MySQL/PostgreSQL concurrency was not exercised in this environment.

## Stability, UI and compatibility

The create controller no longer reads or validates a browser SKU field; generation takes place on the server. Add Product shows a read-only **Generated automatically when saved** display with no submitted SKU name and no fabricated allocation preview.

Edit Product shows the actual persisted SKU in a read-only, unnamed input. It deliberately does not use flashed `old('sku')` data. Ordinary seller updates ignore crafted SKU values, including arrays. The locked existing variant retains its SKU when changing name, description, subcategory, price/sale, stock, status or images. Other variants are preserved by the existing multi-variant price/stock guard; SKU editing is no longer part of that form's contract.

If a historical product has no active variant, ordinary editing reports the problem instead of silently adding another variant/SKU. No such products were found. A safe recovery would first inspect the original variant, soft-deletion history, ownership and cart/order references, then obtain approval for a specific restoration. Blank or conflicting historical values would likewise require an audited correction rather than a bulk rewrite. No backfill or cleanup is run by this task.

Leading zeros and historical SKU formats remain unchanged. Product and inventory search retain their current variant-derived searchable data. Existing active-site image URLs, individual replacement/removal, pricing, category restrictions, stock fields, authentication, pending-seller local access and production approval checks remain in place. Variations and advanced inventory synchronization are deferred to their separate tasks.

## Files changed for this task

- `app/Support/SellerProductSku.php`: bounded, unique-index-backed default variant/SKU allocation.
- `app/Support/SellerProducts.php`: automatic creation, immutable existing SKU, missing-variant error and bounded concurrency retries.
- `app/Http/Controllers/Seller/ProductController.php`: removes ordinary browser SKU validation/persistence.
- `resources/views/seller/products-create.blade.php`: read-only automatic-assignment explanation.
- `resources/views/seller/products-edit.blade.php`: saved read-only SKU and updated section help text.
- `tests/Feature/SellerProductSkuTest.php`: isolated feature tests for generation, stability, compatibility, ownership, errors and concurrent allocation.
- `tests/Fixtures/create-sku-concurrent.php`: worker for the isolated two-process concurrency test; it accepts only a temporary database location and creates no development products.
- `documentation/S-PROD-01A.md`: this report.

Other modified/untracked files were already present and were preserved.

## Verification

- `php artisan test`: **231 passed, 2,474 assertions**.
- `php artisan test --filter=SellerProduct`: **49 passed, 687 assertions**, including all **11 SKU tests**.
- The SKU cases cover trusted generation without manual input, multiple products/sellers, drafts, immutable edits and image replacement, leading-zero legacy values and searchable markup, soft-deleted collisions, bounded allocation failure/rollback, read-only forms, ownership/category restrictions, missing variants and actual simultaneous processes against temporary SQLite.
- `node --test tests/js/seller-image-controls.test.mjs`: **5 passed**, preserving image and shared logout interactions.
- `php artisan route:list --json`: run before and after implementation. Complete output is identical, including methods, URLs, names, actions and middleware; the existing route fixture assertion also passed.
- `php artisan view:cache`: passed.
- `npm.cmd run build`: passed (Vite 7.3.6, 62 modules).
- PHP syntax checks: passed for the controller, both support helpers, new PHP test and concurrency worker.
- Pint: applied to the new files, then `--test` passed for all five changed/new PHP files.
- `git diff --check`: passed. Git also reports an informational CRLF-to-LF normalization warning for the already-modified seller layout.
- Git status and the active branch were reviewed before and after; all changes remain local on `seller-registration`.

The full suite covers the existing category, pricing, variants, image operations, stock, profile, CSRF, role, pending-seller and production-approval behavior. Tests use isolated fixtures/fake storage; no test product was inserted into the development database. The concurrent worker only writes its dedicated temporary database. PHP test runs were performed sequentially.

## Manual verification and remaining limits

Browser testing was not performed. Automated HTTP tests inspect the actual HTML and persistence; the existing Node interaction tests exercise image and logout event handlers with DOM doubles.

Manual checks:

1. Open Add Product and confirm SKU explains automatic assignment without an editable field or allocated preview. Save a legitimate product and confirm its real SKU in Product Management and Inventory.
2. Refresh, log out and log back in, then open Edit Product. Change its name, allowed subcategory, price and stock; confirm the saved SKU stays the same.
3. Create another product and confirm a distinct SKU. Search the listing using both generated and historical SKUs.
4. Check optional sale pricing, thumbnails, Replace/Remove/Undo, low-stock threshold, category locking, sidebar and logout confirmation.

No setup commands or migrations are required. The concurrency test requires PHP's process support and writable temporary storage; its dedicated SQLite database is removed afterward. Retaining arbitrary legacy SKUs means generated base values can occasionally require the random suffix, as tested. No broader variant management or inventory behavior was introduced.
