# S-ORD-01 implementation report

Implemented locally in `C:\Suki-group-work` on `seller-registration`. Existing untracked work was retained. No staging, commits, pushes, branch changes, migrations, development fixtures or commerce data writes were performed.

## A. Summary

Seller Orders now lists attributable persisted seller order segments instead of session orders. It provides server search, fulfillment/payment-method/date filters, stable sorting, 15-segment database pagination, matching summaries, shared collapsible filters/chips, and read-only previews. Existing Seller Centre shell, Orders heading, green palette, typography and card/table styling are retained. The table scrolls within its card on narrower screens.

## B. Files changed

- Added `app/Support/SellerOrders.php`: authorized query, segment aggregates, validation, filtering, choices, summary counts, SQL pagination and page-bounded item previews.
- Modified only the existing Seller Orders closure body in `routes/web.php`.
- Replaced `resources/views/seller/orders.blade.php`: persisted listing and summaries, shared filtering, accessible native read-only previews, honest empty states; removed old status forms and client-only search/filter script.
- Added `resources/views/seller/partials/orders-filters.blade.php` and `order-preview.blade.php`.
- Added `tests/Feature/SellerOrdersPersistentListingTest.php`.
- Extended `tests/js/seller-filters.test.mjs` to include Orders in existing shared behavior checks. Production JavaScript was not changed.
- Added this report and `S-ORD-01-routes-before.json` / `S-ORD-01-routes-after.json`.

## C. Existing route

Before and after: registered method `GET|HEAD`, URI `seller/orders`, name `seller.orders`, action `Closure`, middleware `web`. The existing guard remains; the closure now additionally resolves `SellerAccount::current()` before invoking `SellerOrders::data()`. No controller/action substitution or new details/status route was introduced.

## D. Data source

`SellerOrders` calls `SellerOrderItems::owned()` and joins its existing Orders/OrderItems/SellerOrders source. No session order fallback exists. The query selects only saved reference/number, creation time, recipient name, parent/effective status, method and seller item aggregates. It does not select full parent totals, buyer addresses/phone or payment-provider data.

SQL groups the authorized item cohort before filtering/sorting/pagination. A bound aggregate item-name search marker allows a matching item to select the segment without dropping its other owned lines or reducing its full seller gross. A second ownership-restricted query loads only items for parent IDs on the current page, grouped by exact parent/segment key. No unrestricted parent item relation is loaded and no per-row query is issued. Status/method choices are distinct database values, not all order records materialized in PHP.

## E. Seller identity

`SellerAccount::current()` remains authoritative: authenticated seller role, active/non-suspended user, exactly one approved shop, with the existing pending-shop exception only in local environment. Normalized `seller_orders.seller_id` identifies `sellers.id`. Legacy Order/OrderItem seller IDs identify the shop owner's `users.id`. Request seller/user IDs do not establish ownership. A guest carrying only the legacy seller flag is rejected by the persisted resolver.

## F. Duplicate and legacy handling

The shared ownership helper was not changed. Normalized items require the current shop's seller order and matching parent order. Legacy items require no seller-order link, matching user-owner IDs on both parent and item, and no normalized groups on the parent. Null owners, mismatched legacy owners, inconsistent parent links, empty normalized groups and partial-normalization leftovers are excluded. No data repairs/backfills or fabricated seller groups occur. Separate item IDs remain separate even with identical product slugs. Independently attributable snapshots survive missing, inactive or soft-deleted catalogue records. Arbitrary duplicate persisted item rows are not guessed away.

## G. Grouping and amounts

Normalized rows group by saved seller-order identity and parent order; qualified legacy rows use their verified parent segment. Saved reference is preferred, with actual order number fallback; normalized segment IDs are labelled. Gross uses the unchanged `SellerOrderItems::GROSS_SQL`: minor-unit price / 100 when present, otherwise saved decimal unit price, multiplied by quantity. The UI labels this Seller Item Gross. No shipping/discount/commission/refund/payment allocation or payout calculation is made.

## H. Filters

- Bound server search: saved reference, order number and authorized item name; literal search `0` is supported.
- Effective fulfillment status uses the shared lowercased seller-order-status/parent-status convention. Existing supported states plus actual owned unfamiliar states are offered. Unknown states remain visible and are not assigned a fabricated summary category.
- Payment-method choices come only from owned recorded orders; no paid-only selector exists.
- All ten `SellerDateFilter` presets apply to `orders.created_at`, labelled Date Placed. Asia/Manila business dates, inclusive start/exclusive next midnight, Monday weeks, rolling periods and validation are unchanged.
- Sort allowlist: newest, oldest, saved reference A–Z, highest/lowest seller item gross. Parent/segment ID tie-breakers stabilize database pagination.
- Shared `SellerFilterChips::make()` receives validated allowlisted criteria. Default sort, empty/All, page and unsupported parameters do not count. Custom bounds form one date chip. Removal preserves other criteria and drops page. Reset uses the clean Orders URL. Pagination retains validated filters only.

## I. Page and UI

Six cards cover all matching segments: total; pending/placed; processing (confirmed/accepted/preparing/packed); ready (ready_for_pickup/ready_to_ship); completed (delivered/completed); cancelled/failed (cancelled/delivery_failed/returned). Other statuses remain included in total but outside these named categories. Definitions are stated in the footer.

The table shows reference, creation date/PHT time, buyer name, owned item lines/units, seller gross, effective fulfillment status with its source, saved method and Preview. Native details/summary previews work without additional JavaScript and contain only authorized saved line names/options/variants/quantities/prices and minimal buyer identity. Normalized previews separately identify parent status. No phone, address, unrestricted totals, fake workflow controls or status forms remain. Native browser interaction was not verified here.

Shared trigger/panel/chips and `seller-filters.js` provide count, toggle, draft preservation, Escape/close focus restoration, initialization guard and no-JavaScript fallback. Empty/out-of-range pages retain actual paginator totals without invented ranges or global unattributed counts.

## J. Security

Feature tests cover different shop/user IDs, cross-seller normalized/legacy data, shared-parent previews and search, foreign method/status choices, manipulated owner parameters, null attribution, inconsistent parent references, normalized precedence, historical snapshots, session-only guests, other roles, inactive/suspended users, rejected/suspended shops, pending local/staging/production behavior and multiple eligible shops. Search and choice labels are escaped by Blade. Ownership is enforced before records enter HTML or preview data.

## K. Compatibility

Checkout, Buyer/Admin/Rider/Logistics routes and status writers were not edited. Product Management, images, variants, SKUs, inventory, profiles, Promotions, Sales and Reports code was not edited. Shared ownership, status and monetary helpers are unchanged. Reconciliation fixtures confirm equivalent completed/delivered item gross against Sales/Reports; segment and item counts are deliberately not equated. Existing full-suite regressions pass.

## L. Testing

PHPUnit configuration was rechecked; cached configuration was absent. Test commands explicitly set testing environment, SQLite `:memory:`, empty DB_URL and array cache/session. RefreshDatabase fixtures ran only in isolated tests.

- New Orders suite: **38 passed, 236 assertions**.
- Final full PHP suite: **387 passed, 3,867 assertions**.
- JavaScript suite: **31 passed, 0 failed**, including the added Orders shared-filter context.
- PHP syntax checks passed for new service/test and modified routes.
- Targeted Pint passed for new PHP service and test; unrelated route formatting was not rewritten.
- `npm.cmd run build`, `php artisan view:cache` and `git diff --check` passed.

The first focused run exposed one incomplete test fixture (missing required variant name); the fixture was corrected before successful verification. New tests also verify explicit status buckets, chronological/reference sorting, all-page counts, constant service query count, page-bounded preview materialization, grouped chip removal, malformed filters and unchanged commerce-table snapshots containing real isolated payment/shipment/event/stock fixtures.

## M. Routes

Before/after snapshots each contain **136 routes**. Domains, methods, URIs, names, actions and middleware match for every route. Source-location `path` metadata for later closures shifts because the Orders closure became shorter; this is not a route contract change. The Orders action remains Closure. Snapshots are stored alongside this report.

## N. Database

No migrations, schema changes, indexes, backfills, ownership changes, production/development fixture creation, stock adjustment, payment writes, shipment creation or fulfillment changes were performed. Commerce-table snapshot tests verify GET listing leaves Orders, OrderItems, SellerOrders, Products, ProductVariants, Payments, Shipments and DeliveryEvents unchanged. Test fixtures populate the isolated in-memory database only. Production/MySQL execution and applied deployment schema were not verified.

## O. Browser verification

Not performed. Browser inventory returned no apps or browsers; opening an in-app tab returned `Browser is not available: iab`. Automated view/DOM and handler checks do not establish real desktop/mobile rendering, native details/keyboard behavior or browser performance. The requested manual walkthrough remains outstanding; no development records were created to populate a browser.

## P. Known limitations

Current Buyer checkout does not reliably attribute saved purchases to sellers. Unattributed purchases intentionally do not appear; the page does not claim a complete purchase history. Partially normalized leftovers are conservatively omitted. Legacy owner-user identity remains less precise than normalized shop ownership. Existing parent/segment status divergence is displayed, not repaired. No Seller status mutations, stock lifecycle, payments, refunds, verified received earnings, settlements or payouts are introduced. Arbitrary duplicate stored item rows require a separate integrity audit.

## Q. Remaining tasks

- S-ORD-02: dedicated seller-owned details using the same ownership boundary and minimal data disclosure.
- Checkout Attribution Integration: connect persisted catalogue/cart lines to shops/variants/seller groups; audit historical provenance separately without guessing.
- S-ORD-03: agreed segment/parent transitions, authorization, concurrency/idempotency and event history.
- S-ORD-04: coordinated persisted pickup/shipment/provider/rider readiness and assignment.
- Manual desktop/mobile/keyboard walkthrough and deployment-database verification.

All implementation changes remain local for review. No staging, commit or push was performed.
