# Seller inventory reliability

Baseline: skeleton at 8347764 (public storefront redesign). The only pre-existing local modification was resources/js/landing.js; its pre-task hash is preserved. No old reference project was accessed.

## Existing architecture and fixes

GET seller.inventory still uses Seller FulfillmentController::inventory. InventoryController owns the existing increase/decrease/set routes, with web/auth/not_suspended/approved/role:seller. No routes or middleware changed. SellerInventoryService remains the stock writer, using product_variants.stock and row locks. No new inventory tables, cache, reservations or competing stock fields were added.

The old page counted variant rows as products, used raw image paths, filtered only in JavaScript, sent plus/minus immediately, had no stale-value protection for absolute saves, and substituted session products when the owned database query was empty. Those issues are corrected while retaining the table/mobile cards, colors and summary layout. The explicit legacy route remains read-only; session products no longer appear in canonical Inventory.

Plus/minus now change only the editable variant quantity. Save uses the existing PUT seller.inventory.set endpoint and an expected_stock snapshot. Server validation requires nonnegative integers for stock/snapshot and caps them at 2147483647. Laravel's local PostgreSQL grammar maps unsignedInteger to PostgreSQL INTEGER, so the former 4294967295 maximum could exceed the actual database range. Under the existing variant row lock, mismatched expected stock raises a visible reload/retry error before any write. Existing relative endpoints still adjust the latest locked quantity; ProductCatalogService calls retain their existing optional-snapshot behavior. No success is fabricated by JavaScript.

Pending protection blocks duplicate submissions across desktop/mobile forms and preserves enabled form inputs for native multipart-free form transport. Enter within quantity focuses Save without submitting unexpectedly. Controls retain focus indicators and accessible names. Back navigation restores disabled buttons. Broken images reveal a bounded local placeholder; image URLs follow the same public disk/HTTP URL conventions as Seller Products.

## Stock definitions and commitments

Inventory lists actual non-deleted variants of the authenticated Seller's non-deleted products. Each quantity editor updates only that variant, never a product aggregate or every variation. Inactive listings/variations remain manageable and are identified beneath their controls. Product Management/Edit links remain the existing variation manager.

Summary cards count distinct owned products, including inactive/unpublished products as the prior inventory did. Product units are the sum of active, non-deleted variants, matching Seller Products' aggregate convention. Products without active variants contribute zero units. Per-product aggregate thresholds: zero units out of stock, 1-10 low stock, over 10 in stock. Summary totals always cover the whole owned inventory, independent of row filters. Each row also shows its active product total. Stock filters refer to each individual variant's stock. Aggregate Stock Status badges use the full active product total, even when search/stock filters hide other variants; they match the summary thresholds. The page explicitly explains the distinction.

Name/SKU search is case-insensitive and literal (percent/underscore/exclamation escaped). Category IDs come only from owned products. Category/search/stock filters run in the backend query and compose through GET Apply; Clear returns to the canonical unfiltered URL. This existing unpaginated listing remains unpaginated; no page-only client filtering or inventory cache was introduced. Associations are eager-loaded.

Checkout locks the same variant and decrements stock when an order is placed; cancellation restores it once under existing domain locks. Stock is already remaining units, so committed orders are not subtracted twice. Setting remaining stock to zero does not alter existing commitments. No order, wallet, refund, payout, payment or cancellation service was edited. The stale-form test specifically checks that a post-checkout save cannot overwrite deducted stock. Stock changes are visible through Seller Products, public storefront and Buyer product detail/catalog because all read the same variant field; cart/checkout contract and cancellation tests pass.

## Exact files

Modified:
- app/Http/Controllers/Seller/FulfillmentController.php (Inventory methods only)
- app/Http/Controllers/Seller/InventoryController.php
- app/Services/SellerInventoryService.php
- resources/views/seller/inventory.blade.php
- tests/Feature/PhaseThreeInventoryUnificationTest.php
- tests/Feature/PhaseTwoASellerProductEnforcementTest.php (focused manual inventory test)

Added:
- resources/views/seller/inventory-stock-controls.blade.php
- resources/views/seller/inventory-script.blade.php
- tests/Feature/SellerInventoryFunctionsTest.php
- tests/JavaScript/inventory.test.cjs
- docs/seller-inventory-functions.md

Existing inventory tests were adjusted for the new required snapshot, database-only canonical listing and current product presenter statuses. One old product-edit fixture now has a valid assigned root/child category; category authorization itself is unchanged.

## Verification and limitations

38 isolated PHP tests / 335 assertions passed: inventory functionality/unification, manual inventory enforcement, variant cart contracts, checkout lifecycle/cancellation and public storefront. Twelve JavaScript tests passed for inventory interactions and storefront regressions. PHP syntax, 26 compiled Blade files, four rendered inline scripts, 13 non-nested form boundaries with matching quantity/snapshot inputs, and git diff --check passed. All existing tracked files were compared with pre-task hashes: only the six listed files changed; landing.js and unrelated modules remained identical.

Tests used explicit SQLite :memory:, empty DB_URL, array cache/session and disposable compiled views. New fixtures refuse execution outside that environment. Existing RefreshDatabase suites initialize schema only in memory. No shared Supabase records/schema, application migration commands, .env or storage configuration were changed; no commit/push or Git mutation occurred.

Browser access host was unavailable; visual/native browser behavior was not verified. Real simultaneous PostgreSQL requests were not run against shared Supabase: stale-checkout and sequential stale/replay behavior are tested in isolation, while row-lock semantics rely on the existing PostgreSQL transaction path. Inventory is still an unpaginated listing and may need pagination for a substantially larger catalog. A genuinely missing product image displays a placeholder until its existing asset is repaired. The Product editor retains its previous stock-writing behavior; this task adds optimistic stale protection specifically to the Inventory Save endpoint.

## Rendering follow-up

The stock partial previously placed @if directly after the word units and @endif directly after variation. BladeCompiler's statement matcher uses a non-word-boundary before the at-sign, so both directives remained literal. The issue was reproduced with compileString; directives now occupy separate lines and rendered-HTML tests reject leaked directive fragments. This fixes the template rather than masking text with CSS.

The former Status column is now Aggregate Stock Status based on the active product total. Variant quantity editors, stock filters and product aggregates remain distinct, with inactivity separately identified. Compact non-wrapping badges and bounded stock controls sit in an eight-column horizontally scrollable desktop table. Mobile cards retain the same fields.

Last Updated uses product_variants.updated_at, converted to Asia/Manila before display. The column and tooltip identify a general variation record update, including stock and other edits; no stock-only audit timestamp is claimed. Missing timestamps show Not recorded. Tests cover UTC-to-Manila date rollover, missing dates, inactive listings with positive stock, variant totals, all thresholds and filtered totals. No stock mutation controller/service, routes or JavaScript changed during this follow-up.

Follow-up files edited: app/Http/Controllers/Seller/FulfillmentController.php; resources/views/seller/inventory.blade.php; resources/views/seller/inventory-stock-controls.blade.php; tests/Feature/SellerInventoryFunctionsTest.php; this document. Added: resources/views/seller/inventory-updated-at.blade.php.
