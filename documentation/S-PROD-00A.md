# S-PROD-00A: Optional sale price validation

Implemented locally on `seller-registration`. No staging, commit, push, branch switch, database reset, or development database fixtures.

## Root cause and existing behavior

Create used `required_if:on_sale,1` but still applied `numeric`, `min:0`, and `lt:price` to a submitted blank sale price. The JavaScript hid the field and removed its `required` attribute without disabling it, so the blank value was submitted and converted to null by middleware. The unchecked checkbox itself was omitted. Validation ran before persistence evaluated the checkbox. Drafts had `nullable`, but still validated nonblank stale values.

Create also saved any nonblank stale sale price in metadata even with the checkbox off. Update had no sale validation or controls, retained stale sale metadata, and populated its regular-price input from the variant's current selling price. Listing strikethrough depended only on the sale flag.

The existing schema stores regular price, sale price, and sale flag inside `products.seller_listing_data` JSON. `product_variants.price_minor` stores the actual selling price in centavos. There is no dedicated sale-price column or shared Product/Variant sale accessor. Existing save transactions and ownership checks are retained.

## Changes and files

- `app/Http/Controllers/Seller/ProductController.php`: shared conditional validation and pricing normalization for create/update. Missing checkbox defaults to false; malformed checkbox values fail boolean validation. Disabled sale data is excluded from validation and explicitly cleared during persistence. Enabled sales require a numeric value greater than zero, below regular price, with zero to two decimal places and within the variant column's range. Enabled draft sales also require valid prices; incomplete drafts without a sale remain supported.
- `app/Support/SellerProducts.php`: listing marks a sale active only when its prices form a positive discount and the variant's actual selling price matches the sale. Both existing listing layouts retain their formatting.
- `resources/views/seller/products-create.blade.php`: uses shared sale controls and script.
- `resources/views/seller/products-edit.blade.php`: adds shared sale controls and loads regular price separately from the selling price.
- `resources/views/seller/partials/product-sale-pricing.blade.php`: explicit hidden unchecked value, persisted/old checkbox state, sale input, inline error, and shared JavaScript. JavaScript hides/disables unchecked input and makes checked input editable/required. Controls remain available without JavaScript; server rules are authoritative. Old form submissions take precedence over persisted checkbox state, including omitted unchecked values.
- `tests/Feature/SellerProductSalePriceTest.php`: isolated SQLite feature coverage with fake storage.
- `tests/Fixtures/seller-product-routes.json`: all pre-change route methods, URIs, names, actions, and middleware for automated comparison.
- `documentation/S-PROD-00A.md`: this report.

## Persistence and verification

Disabled sale: `on_sale=false`, `sale_price=null`, regular price retained, variant selling price set to regular price. Enabled sale: regular price retained, sale metadata validated, variant selling price set to sale price. No migration was needed or added.

Add tests cover regular products, valid discounts (including centavos), invalid/blank/missing/zero/negative/equal/higher/excess-precision sale values, and stale values with an unchecked or omitted checkbox. Edit tests cover ordinary saves, enabling/updating/removing discounts, retaining regular price, preserving existing product/variant identities, and preserving images, stock, metadata, and categories when validation fails. Tests also cover ownership, category restrictions, old input, and both listing layouts.

Regular listings show the selling price without strikethrough. Valid sales show crossed-out regular price and the actual sale price. A stale flag alone cannot create a displayed discount.

- `php artisan test`: **208 passed, 2,163 assertions** (final run, including the route snapshot test).
- `php artisan test --filter=SellerProduct`: **26 passed, 376 assertions**.
- `php artisan route:list --json`: **136 routes**, identical before/after, including middleware. The route snapshot test also passes.
- `php artisan view:cache`: passed.
- `npm.cmd run build`: passed.
- PHP syntax checks for both changed application PHP files and the new feature test: passed.
- Pint on those three PHP files, followed by `--test`: passed.
- `git diff --check`: passed. Existing unrelated uncommitted files were preserved.

The create endpoint remains `POST seller/products` (`seller.products.store`); update remains `PUT seller/products/{product}` (`seller.products.update`). Both retain the `web` middleware and their existing seller access gates. No route file was edited.

## Manual browser checks still required

Browser verification was not performed. Automated requests exercised rendered Blade responses and server behavior, but did not execute the form JavaScript in a browser.

1. Add a product with all required fields, an image, regular price ₱149, checkbox unchecked, and sale price blank. Save and refresh: expect ₱149.00 with no strikethrough.
2. Add a product at ₱149 with the checkbox checked and sale price ₱99. Save: expect crossed-out ₱149.00 and selling price ₱99.00.
3. Edit that discounted product. Confirm regular price is ₱149, sale price is ₱99, and the checkbox is checked. Uncheck and save: expect the discount cleared and ₱149.00 displayed.
4. Enable sale pricing and enter ₱200 against ₱149. Submit: expect a sale-price error and retained form input. Repeat with blank, zero, negative, and equal prices.
5. Check the box, enter ₱99, uncheck, and save: expect no sale. Confirm checkbox visibility/disabled behavior and old state after another field fails validation.
6. Confirm assigned category/subcategory restrictions, ownership, image retention, refresh persistence, My Profile, Store Profile, sidebar navigation, and logout in the browser. Related automated regression tests passed.

No remaining issue was identified in the covered single-price workflow. Existing protection against flattening multiple independently priced variants remains; editing separate variant pricing and automatic SKU work are outside this task. Historical inconsistent records are not bulk rewritten by this fix.
