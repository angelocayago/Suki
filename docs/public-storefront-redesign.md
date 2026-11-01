# Public storefront redesign

Baseline: branch skeleton, HEAD 55d092a. Only pre-existing working-tree change was resources/js/landing.js. It was hashed before edits and preserved byte-for-byte. No old reference project was consulted.

## Architecture and supported behavior

GET /stores/{seller:slug} remains buyer.stores.show, handled by StoreController::show and buyer/store.blade.php inside layouts.app. The layout receives only a head-stack hook; its header, footer and asset pipeline are unchanged.

The public visibility gate is unchanged: approved Seller, active owner, owner not suspended. Pending/unapproved stores return 404, including for their owner. There was no existing private owner-preview route; this task does not introduce one or bypass approval. No public editor link is shown.

The product query remains scoped through seller.products, requiring an active, approved, non-deleted product and an active, non-deleted variant with positive stock. Category/images/active variants are eager-loaded. Search is a case-insensitive literal name search; category is an exact product category from this store's eligible collection. Sorts are newest, lowest price and highest price, with stable ID ties. Price sorting and card prices use the minimum active, non-deleted variant price; variable prices are marked From. There is no synthetic original price or discount badge. The existing in-stock visibility makes a second All/In-stock control redundant, so it was omitted. Pagination is 12 products and retains query selections. Empty-store and filtered-empty states differ; Clear filters returns to the canonical store URL.

The hero uses saved Seller banner/logo fields, approved status, description, category and real joined date. Missing/broken images reveal local gradient/initials/image placeholders. Product counts come from the eligible query; review counts/averages and paginated bodies/replies use visibleReviews (seller kind, hidden_at null). Review authors/contact details/evidence are not disclosed. No new review form or moderation workflow was added.

BuyerStoreFollow and existing authorized follow/unfollow routes support the Follow control. There is no exposed store-wide conversation start endpoint: the existing authorized, throttled POST buyer.messages.context.product.store is reused on product cards. Guests get the existing login link. The messaging service continues to enforce approved store/participant access and reuse the canonical product inquiry conversation. No route, service or authorization changes were made. Cards use View product rather than quick cart, preserving variant selection and the canonical cart/stock checks.

Sharing uses the canonical URL with native sharing or Clipboard API fallback and live success/failure feedback. Section links use normal anchors with a single active navigation location. Script controls remain hidden until initialized. Layout is four columns on desktop, three on tablet, two on mobile. No extra sticky header competes with marketplace navigation.

Followers and authentic store ratings are supported and retained. Sale-price fields, vacation mode, response metrics, business hours and public policy fields were not found; no invented values or controls were added. Optional sold counts/best-selling ranking, review photo filters, price ranges and public pickup location were omitted to keep this change small and protect private data. Product and store review models do support public ratings, but product-rating badges were not added in this pass.

## Files

Modified:
- app/Http/Controllers/StoreController.php (show method only)
- resources/views/buyer/store.blade.php
- resources/views/layouts/app.blade.php (page head stack)
- tests/Feature/SellerProfilesTest.php (two in-memory fixture columns required by new query)

Added:
- resources/views/buyer/store-style.blade.php
- resources/views/buyer/store-script.blade.php
- tests/Feature/PublicStorefrontTest.php
- tests/JavaScript/storefront.test.cjs
- docs/public-storefront-redesign.md

## Verification and limits

All test execution used explicit SQLite :memory:, no DB_URL, array cache/session and disposable compiled views. New storefront fixtures refuse execution without explicit isolated settings. Existing RefreshDatabase suites initialize their schema only in SQLite memory; no migration command or writes touched the configured application/shared Supabase database. No .env or schema/migration file was changed. No production build, commit, push or Git mutation was performed.

The storefront tests cover public approval/owner restrictions, real data, image fallbacks, ownership/product/variant visibility, literal search, combined category/sort filters, variant pricing, pagination, privacy, review moderation, follows/inquiry integration, invalid filters and bounded query counts. PHP, compiled Blade, rendered inline JavaScript and diff whitespace were checked. Existing Store Profile, public marketplace, review and cart contract tests were exercised, along with Buyer inquiry deduplication/authorization tests. JavaScript harnesses cover sharing, clipboard fallback, cancellation/errors/pending protection, images and anchor navigation plus existing Store Profile/address behavior.

Final passing results: 49 PHP tests / 650 assertions across the storefront, Store Profile, marketplace, review and cart suites; three additional Buyer messaging context tests / 27 assertions; 26 JavaScript tests. All passed.

One broader regression failure remains in the untouched Seller Orders page: PhaseThreeMessagingContextEntryPointTest::test_seller_entry_accepts_only_the_store_that_owns_the_database_seller_order expects visible text Message Buyer. Other three context tests pass. This task does not change that page/controller/route or messaging service. Browser access host was unavailable, so desktop/mobile visual layout and native sharing were not verified in an actual browser. No live PostgreSQL query execution was required; SQL filtering was tested on isolated SQLite. Images without saved assets intentionally show neutral placeholders.
