# S-PROD-01B-UX-02 — Add Product frontend replacement

Implemented on `seller-registration`, 22 October 2026. All work remains local and unstaged. Existing uncommitted work was retained. No development products/accounts, database records, backend classes, routes, middleware, or `.env` settings were changed.

1. **Exact route.** Products-page and seller-header Add Product links use `seller.products.create`: `GET|HEAD /seller/products/create`. The route is a closure in `routes/web.php` that runs the existing seller guard and delegates to the controller. It retains the `web` middleware and existing authentication, seller-account, approval, and local-development restrictions.

2. **Controller action.** `App\Http\Controllers\Seller\ProductController::create()` obtains the current seller and returns the view with assigned category/subcategories. The form still posts to `seller.products.store`, `POST /seller/products`, which delegates to the same controller's `store()` method.

3. **Rendered view and dependencies.** The actual view is `resources/views/seller/products-create.blade.php`, extending `resources/views/layouts/seller.blade.php`. The new create view includes product-category, product-variations, product-sale-pricing, and product-payment-methods partials. The layout loads `resources/css/app.css` and `resources/js/app.js` through Vite. `app.js` imports bootstrap, the shared variation editor, the new create-page module, and Alpine. The CSS scans Blade and JS sources through the existing Tailwind configuration. The seller sidebar, header, account menu, notifications, and logout dialog remain in the existing layout. Searching view filenames and route/controller references found one Add Product template, with no duplicate create template selected by this route.

4. **Replacement scope.** Fully replaced the Add Product Blade content and removed its old inline script. The former two-column form, sticky Listing Settings sidebar, publishing checklist, repeated top actions, and small image-tile picker are no longer the Add Product presentation. The replacement view is a compact centered form with a `max-w-3xl` container, stacked rounded cards, uppercase green section labels, and bottom product actions. It carries `data-add-product-page="reference-v2"` so the real rendered response is unambiguous.

5. **Visible differences.** Basic Information now has a simple name field, four-row description textarea, locked category/subcategory row, and optional brand. Pricing & Stock has a compact two-column arrangement and informational automatic SKU text. Options have a header-aligned Add Option action and horizontal name/value/remove rows on larger screens, stacking on small screens. Photos have a large dashed upload box with an icon/instructions and a separate square preview grid. Sale Pricing and COD follow the main cards. Optional package/media controls are collapsed under Extra media & package details. Status, Add Product, Save as Draft, and Cancel are together at the bottom.

6. **Reference adaptation.** Adapted the supplied reference's centered card structure, simple fields, optional groups, comma-separated values, explicit generation action, variant table, large upload box, preview grid, and final Add Product action. Used SUKI SHOP's existing green values and Tailwind utilities. Did not copy the Shopleap shell, editable SKUs/categories, six-image/3-MB limits, browser-calculated aggregate inputs, or discount system.

7. **JavaScript changes.** Added `resources/js/seller-product-create.js` and imported it once from `app.js`. It owns Add Product photo selection, previews, ordered submission, removal, upload limits, required-photo publishing feedback, draft compatibility, duplicate-submit protection, and initialization guarding. Updated `seller-product-variations.js` for compact unsaved option rows, accessible table input labels without repeated visible labels, and switching the hidden product type only after generation. Existing combination generation, normalization, reconciliation, value cache, limits, old-input state, and persisted-label restrictions remain authoritative. There is no second copied reference variation script.

8. **Files added/modified/no longer used.**
   - Added: `resources/js/seller-product-create.js` and this report.
   - Replaced: `resources/views/seller/products-create.blade.php`.
   - Modified: `resources/views/seller/partials/product-variations.blade.php`, `resources/js/seller-product-variations.js`, and `resources/js/app.js`.
   - Updated tests: `tests/Feature/SellerProductVariationsTest.php`, `tests/Feature/SellerProductSkuTest.php`, `tests/Feature/SellerProductThumbnailTest.php`, `tests/js/seller-product-variations.test.mjs`, and `tests/js/seller-image-controls.test.mjs`.
   - Removed from the create page: the old inline description/image/submit script and old sidebar/publishing markup. Edit's image-control partial and shared sale/category/payment partials remain in use.
   - Rebuilt generated Vite assets. Other existing dirty/untracked work was retained.

9. **Backend preservation.** No controller, support class, model, migration, relationship, seller guard, checkout, inventory, logistics, or pricing calculation was edited for this task. The existing backend still validates ownership/categories, allocates SKUs, persists images and variants transactionally, reconciles identities, and derives product summaries from actual active variants. All data changes in tests used isolated SQLite databases and fake storage.

10. **Request contract.**

    | Form data | Existing server contract |
    |---|---|
    | Name, description, brand | `name`, `description`, `brand` |
    | Read-only main category | Display only; server obtains the seller's assigned category |
    | Permitted subcategory | `subcategory` ID |
    | Simple regular price/stock | `price`, `stock` |
    | Threshold/status/action | `low_stock_threshold`, `status`, `submit_action=publish` or `draft` |
    | Type | `product_type=simple` or `variable` |
    | Option names and parsed values | `option_groups[index][name]`, `option_groups[index][values][]` |
    | Variant values | `variant_rows[index][selections][]`, `[price]`, `[stock]`, `[is_active]`; existing Edit rows retain `[id]` |
    | Main photos | `images[]` |
    | Optional extra media/package fields | `additional_image`, `product_video`, `weight`, `length`, `width`, `height` |
    | Optional simple sale | `on_sale`, `sale_price` through the existing partial |
    | CSRF | Existing `_token` |

    No editable SKU or seller/category ownership field was added. Option display labels never become request-field keys.

11. **Variation generation.** A new form has no groups/rows, and Add Option is available. Adding/typing a group leaves simple pricing and simple product type in place. Generate Variant Combinations validates normalized names/values and deterministic Cartesian combinations, then changes to variant pricing. Limits remain three groups, ten values per group, and fifty combinations. Blank comma segments, duplicates, excessive lengths/counts are rejected. Generation is explicit; edits do not erase rows on every keystroke. Regeneration retains unchanged price/stock/availability values and reports removed rows. Removing every unsaved group restores simple values and removes stale variant submission controls. Saved Edit labels, IDs, SKUs, and historical references retain their existing restrictions.

12. **Photos.** The large Add Images button opens a hidden picker; raw native file input is not the primary main-photo UI. Previews use object URLs and text/DOM properties. Removal updates the submitted FileList and cover ordering. Five main images at 5 MB each remain enforced, with JPG/PNG/WebP support and server validation unchanged. The button disables at five photos and re-enables after removal. Publishing without photos gives an accessible error; drafts may omit photos. Browser file selections must be repeated after a validation redirect. Optional supporting photo/video limits remain 2 MB/10 MB. Edit Replace/Remove/Undo and active-host persistence remain unchanged.

13. **Pricing modes.** Simple price/stock values remain editable until generation. Generated variants own price and stock; simple and sale inputs hide/disable, so hidden required inputs and stale sale values cannot block or corrupt valid variant submissions. Removing all groups restores previous simple values. Product stock/price-range summaries remain server-derived. Simple sale pricing is optional, disabled sale persists null, and enabled sale must be valid and below regular price. No unsupported variant discounts were introduced.

14. **SKU/category protections.** New product information says “Generated automatically after saving.” Actual product/variant SKUs remain server-generated and immutable. The seller's main category stays read-only; subcategory options and validation remain restricted to the existing configured categories, including Kids and Baby and Office and School Supplies compatibility. No profile category-change instruction was copied.

15. **Product/Edit compatibility.** Existing regression tests create simple/variable products through the unchanged store route and open Edit/listing/inventory. They verify saved groups, variant identity/SKUs, prices/stocks, images, category/ownership restrictions, availability, price ranges, stock totals, and historical references. Edit received only shared variation presentation refinements; its main view and image mechanisms were not redesigned. Product Management was not changed. Discount persistence/scheduling remains outside this task.

16. **Verification results.**
    - `php artisan test`: **287 passed, 3,122 assertions**, 34.44 seconds.
    - `php artisan test --filter=SellerProduct`: **105 passed, 1,335 assertions**, 17.00 seconds.
    - `node --test tests/js/*.test.mjs`: **18 passed, 0 failed**.
    - `npm.cmd run build`: passed; 64 modules transformed. Confirmed the actual hashed JS contains both create-photo and variation modules and both CSS/JS manifest files exist.
    - `php artisan view:cache`: passed; rerun after final tests to refresh the compiled views.
    - PHP syntax checks for the changed Blade/PHP files and JS syntax checks pass.
    - Scoped Pint checks on all three modified PHP test files pass.
    - `git diff --check` passes; Git reports CRLF normalization notices for some existing working-tree files.
    - The first focused run had two assertions tied to the removed inline image script and old SKU input markup. Updated those tests to check the replacement controls/informational SKU; final focused and full runs pass. PHP suites ran separately to avoid shared fake-storage interference.

17. **Route comparison.** Captured `php artisan route:list --json` before and after implementation. All **136 complete entries are identical**, including method, URI, name, action, middleware, domain, and source location. No route was added, removed, renamed, overridden, or modified.

18. **Actual endpoint and browser verification.** **Yes: the exact `seller.products.create` GET endpoint was exercised through Laravel's HTTP test harness and asserted to return `seller.products-create` with the replacement UI.** The test checks the unique replacement marker, centered container, upload button/grid, option/generate controls, initial price/stock, category lock, actual form method/action/CSRF, and Vite asset URLs. This is endpoint/render verification, not a claim of a network browser session. The computer-use browser inventory returned no browsers or tabs, so interactive visual verification was unavailable.

19. **Build/cache/refresh instructions.** Blade compilation and Vite build were performed. At inspection, compiled Blade views existed; there was no `public/hot` or `bootstrap/cache/config.php`. No specific stale-cache fault was established. The earlier implementation still retained much of the old presentation; this task replaces that content. No unrelated caches were cleared and no services were restarted.

    Manual verification on the running local app:
    1. Use the same checkout/origin as this workspace. Open Seller Centre → Products → Add Product and confirm the URL ends in `/seller/products/create`.
    2. Hard-refresh with Ctrl+F5. Expect a centered stack of cards and a large Add Images box; the old Listing Settings sidebar should be absent.
    3. If needed, inspect page source for `data-add-product-page="reference-v2"`. If absent, confirm the web server points to `C:\Suki-group-work\public` and uses the intended checkout. Do not change its configuration blindly.
    4. Confirm the JS/CSS asset requests return successfully. This build references `app-Bf3VZ_cj.js` and `app-DjA_UZbc.css`; later builds can change these names. If deploying elsewhere, transfer the matching manifest and generated assets together.
    5. If source is current but compiled views are stale, run `php artisan view:cache` in this checkout. Rebuild with `npm.cmd run build` if source/assets differ. A deliberately running Vite development server must serve this checkout; an unexpected `public/hot` can direct Laravel to a different dev asset server.
    6. Without saving development records, try Size: Small, Medium and Color: Black, White. Confirm four rows appear only after generation, simple inputs disappear, entries survive regeneration, and removing all groups restores simple inputs.
    7. Check image previews/removal/cover ordering, sale toggle, COD, keyboard focus, and small-screen table scrolling. Perform save/reopen/listing checks in an isolated test environment.

20. **Limitations.** Browser layout, native file-picker behavior, and end-to-end browser submission remain manually unverified. Automated handler tests use DOM doubles; persistence/security tests use the real Laravel endpoints with isolated fixtures. No failing automated checks remain. No sample development records were created. No staging, commit, or push was performed.
