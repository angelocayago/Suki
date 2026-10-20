# S-PROD-00D: Seller delivery defaults and payment display

Completed locally on `seller-registration`, preserving all pre-existing uncommitted work. No route, migration, configuration, Buyer, Admin, Rider, Logistics, account or profile implementation was modified. Nothing was staged, committed or pushed.

## Architecture inspected

The seller forms previously submitted `shipping_options[]`, allowing `suki_rider` and `logistics_partner`. The controller validated these codes and `SellerProducts::save()` retained them in `products.seller_listing_data`, a JSON supplement. Neither Product nor ProductVariant has a dedicated shipping/payment column. Runtime searches found no other consumer of the product shipping-options metadata after the form controls were removed.

A read-only development database inspection found four persisted products, all with historical `['suki_rider', 'logistics_partner']` metadata. None had product-level payment keys. These records were not modified or normalized.

Buyer checkout separately validates the order payment choice as `cod` or `gcash`. Its COD radio is selected by default. Order creation persists the chosen identifier in `orders.payment_method`, sets `payment_status` to `UNPAID`, and sets order shipping to `suki_logistics`. Checkout does not read the product shipping-options list. The displayed order delivery label is SUKI Logistics.

The normalized schema also contains Payment, SellerOrder, LogisticsProvider and Shipment models/tables. Those shipment relationships use actual provider/rider IDs and tracking fields; they are not product form settings. The inspected Logistics routes retain their separate session-based sorting/assignment workflows. This task does not create or assign any provider, shipment, rider, tracking number, shipping fee or payment transaction.

## Implemented behavior

Both Add Product and Edit Product completely remove the Shipping Options selection controls, their old selection variables and their provider-choice descriptions. There are no replacement hidden shipping fields. Package weight and dimensions remain, with updated helper text describing measurements only.

A shared green card now displays:

- **Payment Methods**
- **Cash on Delivery (COD)**
- **Available at checkout** with a green indicator
- **Buyers pay when their orders are delivered.**

The card is informational and responsive; it has no unnecessary selector and submits no payment value. COD support is based on the actual existing checkout choice/order persistence, not on a new payment gateway.

For every newly created seller product, including drafts, the save helper supplies the existing metadata representation `shipping_options: ['logistics_partner']`. This is an intended product delivery default, not an operational courier booking. Request values for shipping options and administrative courier fields are ignored by the controller's existing allowlisted persistence. The save helper also overrides any supplied shipping-options data on creation.

For an existing product, the save helper reloads shipping metadata from the seller-owned, locked product row. It preserves the exact stored value, including both options, rider-only, partner-only, empty, null, unknown formats or an absent key. Ordinary edits and crafted shipping submissions cannot erase or remap it. No mass update or fallback backfill is performed. Unknown historical values are not displayed as configurable delivery choices.

Payment choice remains order-level. The seller form does not persist a product payment field or create a Payment row. If a request nevertheless supplies `payment_method`/`payment_methods`, only an optional `cod` value/list is accepted and ignored. Unsupported seller configuration values, including wallet or GCash, are rejected. This does not remove or change the existing Buyer GCash choice.

## Boundaries and integration findings

The seller metadata code `logistics_partner` and the checkout order code `suki_logistics` are distinct existing representations. This task preserves both in their current responsibilities. Product metadata does not select a real courier, and the checkout/order/logistics workflows would need a separately scoped integration to map intended delivery preferences to actual provider assignments if that is desired.

The existing Buyer checkout offers GCash as well as COD; the seller card intentionally describes COD availability without claiming it is the only option throughout the application. The isolated compatibility test verifies a real checkout request can create a COD order with status UNPAID. It does not establish payment collection, settlement, payout or complete logistics integration. The inspected legacy order flow still sets seller linkage to null; connecting the seller catalogue/default variants to that order flow is outside this task. No such Buyer or Logistics change was necessary to simplify these seller forms.

No existing product needed a schema or data change. No setup command or migration is required. Existing SKU allocation/stability, categories, pricing, images, inventory, approval restrictions, profiles and the No/Log Out modal are preserved.

## Files changed for this task

- `app/Http/Controllers/Seller/ProductController.php`: removes shipping selection validation/input persistence; validates unexpected seller payment configuration without persisting it.
- `app/Support/SellerProducts.php`: trusted new-product default and exact preservation of locked historical shipping metadata.
- `resources/views/seller/products-create.blade.php`: removes provider selectors and selection state; includes the payment card and updates package helper text.
- `resources/views/seller/products-edit.blade.php`: equivalent edit-form refinement.
- `resources/views/seller/partials/product-payment-methods.blade.php`: shared informational COD card.
- `tests/Feature/SellerProductDeliveryPaymentTest.php`: isolated seller and checkout compatibility tests.
- `documentation/S-PROD-00D.md`: this report.

Other modified/untracked files existed before this task and were retained.

## Verification

- `php artisan test`: **253 passed, 2,766 assertions**.
- `php artisan test --filter=SellerProduct`: **71 passed, 979 assertions**, including all **22 delivery/payment cases**.
- `node --test tests/js/seller-image-controls.test.mjs`: **5 passed**.
- `php artisan route:list --json`: run before and after; complete output was identical, including names, methods, URLs, actions and middleware. The existing route fixture test passed as well.
- `php artisan view:cache`: passed.
- `npm.cmd run build`: passed (Vite 7.3.6, 62 modules).
- PHP syntax checks: passed for the modified controller, support helper and new PHP test.
- Pint: passed for those three PHP files.
- `git diff --check`: passed. Git emitted informational CRLF-to-LF normalization warnings for already-modified Blade files.
- Git status and active branch were reviewed before and after. Branch remains `seller-registration`; no staging, commit or push was performed.

The full suite includes SKU generation/collisions, legacy SKUs, categories, optional pricing, image persistence/replacement/removal, stock, account/profile functionality, approval restrictions, CSRF and route regression tests. PHP test runs were sequential.

The delivery/payment tests cover both forms, missing shipping/payment inputs, trusted defaults, malformed/forged shipping fields, absence of courier/payment side effects, seven historical metadata shapes, unsupported payment rejection without writes, COD values without product payment storage, draft defaults, sale pricing, SKU generation, category/ownership/approval protections and existing COD checkout behavior. Test products/orders exist only in isolated test databases; no development sample records were inserted.

## Browser verification

Browser testing was not performed. Automated HTTP assertions verify the rendered forms and persistence. Existing Node DOM-double tests exercise image and logout handlers but do not verify real-browser layout.

Manual checks:

1. Open Add Product on desktop and mobile. Confirm provider checkboxes are gone, package measurements remain and the COD card has readable text and a green availability indicator.
2. Create a legitimate product without shipping/payment fields. Confirm it saves with its generated SKU, images, prices, stock and category intact.
3. Edit an existing product. Confirm the same card and absence of provider controls; save ordinary edits and verify SKU stability, pricing, stock and image Replace/Remove/Undo.
4. Verify unsupported seller payment submissions fail and forged shipping/courier values cannot alter the policy or existing metadata.
5. Check Store Profile, My Profile, sidebar and the shared logout dialog labels **No** and **Log Out**.

The outstanding browser checks and the existing cross-module integration boundaries above are the remaining limitations; no new logistics booking or payment processing is implemented or claimed.
