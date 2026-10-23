# S-PROD-01B: Product variations

## Inspection and implementation design

The current simple product has one default ProductVariant. `product_variants.price_minor`, `stock`, `sku`, `options` and `is_active` are authoritative. Products have no price/stock/SKU columns; product JSON holds simple regular/sale prices and supplementary fields. The prior variation-name/value form was descriptive metadata only. No separate option/attribute tables exist.

The read-only audit found four non-deleted default variants with null options, SKUs `001`, `005`, `S9-P3`, `S9-P4`, and no cart/order variant references. The global SKU unique index and nullable variant foreign keys in cart/order items already exist. No development records will be migrated or rewritten.

The implementation uses product metadata `product_type` and `option_groups`, with existing variant JSON holding name/value selections. Simple remains the default and a lone default variant does not imply variations. Explicitly managed variable products have one row per Cartesian combination, without an extra stock-bearing default. The earliest variant's stable SKU remains the product listing reference. Legacy unstructured multi-variant products retain their existing guarded workflow rather than being silently converted.

Limits: three option groups, ten values per group, fifty combinations. Labels normalize whitespace and case for duplicate detection. Saved names/values and persisted product type are locked; group/value ordering can change and new values can be added. Variant IDs/SKUs are matched against the owned product and saved option combination. Deactivation preserves rows, stock, foreign keys and history; no variant is hard-deleted. Conversions and label renaming require a separate audited workflow.

New variable products use the existing base SKU for their first combination. Additional combinations use a generated `-V` suffix, with the existing unique-index/savepoint retry strategy. There is no product-level duplicate SKU field. Price summaries use the active variants' actual minimum/maximum and stock summaries sum active variants only. Inventory will consume the same summary and direct variable-stock edits back to Product Management.

Variant-specific sale prices are deferred: the schema has only one variant price column. The UI will state this and reject variant discount submissions. Simple-product sale metadata/rules remain unchanged. Product images, COD, logistics metadata, category restrictions, ownership, approval rules and routes remain in their existing flows. Buyer checkout and logistics will not be changed.

## Implemented form and persistence behavior

Add Product now defaults to Simple Product. The shared variations section precedes pricing and lets a new listing select Product With Variations. It supports adding/removing unsaved groups and values, generating combinations, and entering each combination's peso price, integer stock and active flag. The simple price/stock/sale inputs are disabled and hidden in variable mode. Shared SKU, low-stock threshold, images, package measurements and COD information remain in their existing form sections.

The existing create/update endpoints validate the full matrix on the server. A sellable variant price must be positive with at most two decimal places and no more than PHP 42,949,672.95. Stock ranges from zero through 4,294,967,295. At least one group/value/combination is required; incomplete, duplicated or excessive matrices are rejected. Names and values are limited to 50 characters. Names/values normalize whitespace; case differences do not make distinct options.

`option_groups` stores an ordered list of names and values in the existing product JSON. Each authoritative ProductVariant stores its associative option selections in `options`, its actual selling price in `price_minor`, its stock in `stock`, and availability configuration in `is_active`. No prices or stocks are stored only in the session/browser, and no extra default variant is created for variable products. Metadata does not duplicate per-variant price/stock rows.

The browser submits indexed selection values rather than putting seller labels inside field names, so brackets and other special characters do not corrupt PHP input parsing. It uses text nodes for seller-entered labels and escaped JSON for initial state. Regenerating combinations retains entries, persisted IDs, SKUs and deactivation for unchanged normalized combinations. After validation errors, entered values are restored, while displayed SKUs are recovered only from the product's saved variant IDs, never from flashed forged SKU values.

Edits match combinations against the owned, locked product's current variants. Supplied IDs must match both the product and the original combination. Missing IDs on unchanged combinations resolve to their existing row; they do not cause duplicates. Changed attributes are saved in place; unchanged records are not needlessly updated. Reordering groups or values does not allocate new identities. Adding values appends only genuinely new combinations. Saved group names, saved values, and the number of option dimensions cannot be changed. New values can be added to an existing group. Use deactivation instead of removing saved values/combinations.

No variant is hard-deleted or soft-deleted by this feature. Inactive rows retain their stock, options, price, SKU and cart/order references. Historical order item snapshots are untouched. A soft-deleted combination already present in the database cannot be recreated under a new identity through this form; support must review restoration. Ambiguous existing duplicate combinations are also blocked for review.

Product save, variant updates and image changes share the existing transaction and seller ownership lock. The SKU helper uses nested savepoints and the existing global unique index for at most five allocation attempts. New first combinations use `S{seller ID}-P{persisted product ID}`; additional combinations use that base plus `-V` and ten uppercase random alphanumeric characters. A unique-index collision uses the existing eight-character random collision suffix. The product ID is already persisted before generation; no product counts or guessed variant IDs participate. The outer transaction retains its maximum of three database concurrency-error attempts. Failed saves roll back rows and clean up only the new files from that request.

## Summaries and inventory boundary

The earliest saved variant ID supplies the stable product-reference SKU, including when that combination is deactivated. A variable listing's displayed price is the minimum/maximum of configured active variants, including active zero-stock configurations; a row is available only when its product is active, the variant is active and stock is positive. The available-stock total sums active variants. If none is active, the listing displays No active variations with no fabricated price and zero available stock. Inactive variants' retained stock remains visible in the variation editor and is excluded from available-stock totals.

Product Management, its current-price display, and Inventory use a shared price-range partial. Inventory receives the same derived stock value as Product Management. Its aggregate plus/minus controls are replaced with a link/instruction to edit individual variations, including a variable product with only one combination. Direct calls to aggregate stock routes are rejected for variable products. This is a minimal consistency adjustment, not an inventory dashboard redesign. Stock ledgers, reservations and order deductions remain deferred to S-PROD-01C and the relevant order workflows.

## Compatibility and explicit limits

- Simple products retain their one default variant, regular price, optional sale price, stock and immutable SKU. Historical leading-zero/manual SKUs remain untouched. The former descriptive `has_variations` flag alone does not convert a default row into a variation product.
- Persisted simple/variable product types cannot be converted by this form. This avoids losing the original default variant, its stock or references. Saved option renaming/removal and dimension changes likewise require a separately audited workflow; they are clearly restricted in the UI and backend.
- Existing unstructured multi-variant records use a guarded legacy mode. Their variants are preserved and shown for reference; the existing aggregate price/stock guard remains in effect. They are not inferred into a new Cartesian product. None was found in the development audit.
- Variant-level sale prices are not implemented because no regular/sale columns exist at variant level. The UI states this, and submissions of variant discounts or product-level sale settings for a variable product are rejected. Simple-product discounts remain fully supported.
- Images remain product-level. Add/Replace/Remove/Undo, cover order and active-site URLs are preserved. No per-variant image relationship exists, so that feature is deferred.
- Buyer product pages/checkout, order processing and logistics implementation are unchanged. Buyer variant selection, stock reservation/deduction and any broader catalogue integration are separate work. COD remains order-level; new products keep the `logistics_partner` metadata default, and no provider/payment/shipment is fabricated.
- Existing categories, ownership, approval restrictions, local pending-seller access, profiles, sidebar and No/Log Out labels are unchanged.

## Files changed

- `app/Support/SellerProductVariants.php`: type detection, validation, canonical combinations, immutable configuration guards and transactional row synchronization.
- `app/Support/SellerProducts.php`: delegates managed variants, derives truthful summaries, protects aggregate stock adjustment and excludes derived data from JSON persistence.
- `app/Support/SellerProductSku.php`: supports collision-safe additional-variant SKUs using the existing allocator.
- `app/Http/Controllers/Seller/ProductController.php`: accepts validated variation matrices through existing endpoints and keeps simple/variant pricing responsibilities distinct.
- `app/Models/Product.php`: deterministic variant ordering by saved ID.
- `resources/js/seller-product-variations.js` and `resources/js/app.js`: shared editor, deterministic combination generation and retained form state.
- `resources/views/seller/partials/product-variations.blade.php`: shared Add/Edit configuration UI and trusted initial state.
- `resources/views/seller/partials/product-price.blade.php`: shared simple price/variant range display.
- `resources/views/seller/partials/product-sale-pricing.blade.php`: marks the simple-only sale section for mode switching.
- `resources/views/seller/products-create.blade.php` and `products-edit.blade.php`: replace the old descriptive option controls/scripts with the shared editor; preserve existing simple fields.
- `resources/views/seller/products.blade.php` and `inventory.blade.php`: consistent summaries and safe per-variant stock navigation.
- `tests/Feature/SellerProductVariationsTest.php`: isolated feature coverage for variations, identity, security, persistence and references.
- `tests/Feature/SellerProductSkuTest.php` and `tests/Fixtures/create-sku-concurrent.php`: extend the isolated two-process SKU test to additional variant allocation.
- `tests/js/seller-product-variations.test.mjs`: combination and DOM-double editor tests.
- `documentation/S-PROD-01B.md`: this inspection/design/implementation report.

No migrations, new tables, new columns, new routes, model duplication, configuration changes or new dependencies were needed. No setup commands are required for this workspace; the normal build is included in verification. All changes remain local on `seller-registration`, with existing untracked work retained and no staging, commit or push.

## Verification

- `php artisan test`: **284 passed, 3,063 assertions**.
- `php artisan test --filter=SellerProduct`: **102 passed, 1,276 assertions**, including all **30 variation cases** and **12 SKU cases**.
- Focused variation/SKU checks also passed during implementation. The variation cases cover one/two/three dimensions, deterministic persisted combinations, independent prices/stocks, zero stock, immutable identities, additions/reordering, rejected duplicates/limits, failed-save rollback/upload cleanup, cart/order reference safety, validation error restoration, images, category/security/local approval rules and session persistence.
- SKU concurrency tests run synchronized PHP processes against an isolated temporary SQLite database, covering both the original base and additional-variant allocation. The original bounded-collision/soft-deleted-SKU tests also pass. MySQL/PostgreSQL concurrency was not exercised here.
- `node --test tests/js/*.test.mjs`: **11 passed**. Six cover combination/editor behavior; five preserve image/logout interactions. Editor tests use DOM doubles and verify mode switching, retained entries, immutable saved labels, unsaved value removal and safe indexed field names.
- `php artisan route:list --json`: recorded before and after; complete output was identical, including every method, URL, route name, action and middleware. The existing route fixture assertion also passed.
- `php artisan view:cache`: passed.
- `npm.cmd run build`: passed (Vite 7.3.6, 63 modules).
- PHP syntax checks and Pint `--test`: passed for all eight changed/new PHP implementation/test/fixture files.
- `git diff --check`: passed. Git emitted informational CRLF-to-LF warnings for the edited Inventory/Add/Edit Blade files.
- Git status and branch reviewed before and after. Work remains on `seller-registration`; no staging, commit, push or schema/data migration was performed.

Initial implementation runs exposed a Blade JSON compilation issue and a PHPUnit helper-name conflict; both were corrected before the passing final runs above. PHP test suites ran sequentially with isolated databases/fake storage. No test products, users or orders were inserted into development data. The full suite also verifies existing category, image, sale pricing, COD/logistics metadata, stock, profile, navigation, CSRF and approval behavior.

## Browser verification

Real browser testing was not performed. Automated HTTP tests verify HTML and persistence, while Node DOM doubles exercise the editor and the existing image/logout handlers. They do not verify native rendering, file pickers or real-browser focus/layout.

Manual checks:

1. Create a simple product with an image, regular price, no sale and stock. Confirm its actual SKU and unchanged edit flow; then check optional sale pricing.
2. Create a variable product with Size: Small/Large and Color: Black/White. Generate four rows, enter independent prices and stocks including zero, save, refresh and reopen Edit Product. Confirm IDs/SKUs and all entries persist.
3. Change one price, another stock and deactivate a third row. Save and confirm only intended values change, ranges and active stock agree between Products and Inventory, and a deactivated row keeps its SKU/stock.
4. Add a value to a saved group, regenerate and verify only new combinations receive new SKUs. Confirm saved labels/type are read-only and saved rows can be deactivated rather than removed.
5. Try duplicate labels, invalid prices, fractional/negative stocks and more than fifty combinations. Confirm errors and retained entries, then correct and save.
6. Verify image Add/Replace/Remove/Undo, COD, category restrictions, local pending access, both profiles, sidebar and No/Log Out behavior on desktop/mobile.
