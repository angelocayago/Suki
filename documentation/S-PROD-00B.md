# S-PROD-00B: Seller product thumbnails

Completed locally on `seller-registration`. Existing uncommitted work was retained; no staging, commit, push, branch switch, route edits, database migration, or development product creation was performed.

## Root cause and storage inspection

`SellerProducts::listing()` generated product thumbnail URLs with `Storage::disk('public')->url()`. That disk's URL comes from `APP_URL`; its effective value during inspection was `http://localhost/storage`. This pins thumbnails to the configured host instead of the browser request's host, scheme and port. Store Profile already avoids this problem using `asset('storage/'.$path)`.

A read-only audit of the connected development database found four image records belonging to three products. Every referenced file exists on the public disk, with matching filenames and extensions. All four references use `products/<generated-name>.jpg`, with positions 0, 0, 1 and 0. No referenced image files were missing. Five product JPG files exist in the directory; no unreferenced file was removed.

The public disk root is `storage/app/public`. The existing `public/storage` Windows junction targets `C:\Suki-group-work\storage\app\public` correctly. No storage setup is required here; the junction was not recreated or overwritten.

## Relationships and persistence

`products` belongs to a seller and category. `product_images.product_id` references its product; `path` is a string and `position` defines ordering. There is no separate primary-image flag and no variant-image foreign key in the inspected schema. Product variants are a separate product relationship. Ties in image position now use image ID for deterministic ordering.

Main uploads are saved to `products/` on the public disk using generated filenames; new image rows receive disk-relative paths directly. Previously the create controller converted paths to configured-host URLs, then the save helper removed that URL prefix before inserting records. This unnecessary round trip has been removed for main product images. Existing auxiliary image/video metadata in `seller_listing_data` retains its prior behavior and is outside this thumbnail fix.

Listing URLs now use the Store Profile `asset()` approach, preserving the current request origin under the project's current asset configuration. Relative `products/...`, `storage/products/...`, `/storage/products/...`, and legacy HTTP(S) `/storage/products/...` references are resolved to local public assets without rewriting records or fetching a remote host. Arbitrary remote URLs, bare filenames and unsafe traversal paths are not guessed or exposed. Existing valid product images display without re-upload.

The first available image in position/ID order supplies the cover. Missing files are skipped for display; if none is available, the existing neutral package placeholder appears. A browser image-error fallback covers delivery failures after the existence check. Reads never delete or rewrite missing-file records. Table, mobile and edit thumbnails preserve their original containers, rounding, overflow handling and object-cover styling.

## Edit behavior and security

The existing edit form supports explicit replacement of all product images or removal of selected image records using the existing PUT endpoint and CSRF token. Replacement requires selecting its checkbox and valid files. File input values cannot survive validation errors; the form explains that files must be selected again. An ordinary edit preserves existing images, including requests carrying files without the explicit replacement flag.

Image mutations run in the existing product transaction and seller ownership lock. Removal IDs must belong to that product, including when replacement is also requested. New upload failures and failed saves clean up only the files created by that failed request. Explicit replacement/removal detaches selected records but retains old files to avoid deleting assets that another reference may use. File reclamation is intentionally not automated.

Existing pricing, category restrictions, variant protection, authorization, production approval restrictions and local pending-seller access remain in place.

## Files changed for this task

- `app/Support/ProductImageUrl.php` — safe local reference interpretation, existence checks and active-request asset URLs.
- `app/Support/SellerProducts.php` — image records for editing, available covers, direct path persistence and transactional image changes; avoids projecting each product twice during sync.
- `app/Http/Controllers/Seller/ProductController.php` — direct upload paths and validated explicit replacement/removal with failure cleanup.
- `app/Models/Product.php` — deterministic image ordering for equal positions.
- `resources/views/seller/partials/product-thumbnail.blade.php` — shared thumbnail and error fallback.
- `resources/views/seller/products.blade.php` — shared thumbnail in both layouts.
- `resources/views/seller/products-edit.blade.php` — multipart upload controls, replacement/removal, shared current thumbnail.
- `tests/Feature/SellerProductThumbnailTest.php` — five focused tests using isolated database fixtures and fake storage.
- `documentation/S-PROD-00B.md` — this report.

The create Blade template, sale pricing partial and existing product tests already had local changes before this task. Those changes were preserved; this task does not modify the create template.

## Verification results

- `php artisan test`: **213 passed, 2246 assertions**.
- `php artisan test --filter=SellerProduct`: **31 passed, 459 assertions**.
- New thumbnail tests: **5 passed, 83 assertions**. Coverage includes actual file persistence and relationships, ordered cover selection, stale configured URL versus active host, refresh, a fresh authenticated session, legacy references without record rewrites, unsafe paths, absent/missing images, explicit replacement/removal, no duplicate rows after ordinary edits, foreign image/product rejection, invalid uploads and transaction rollback with upload cleanup.
- Existing product/category/pricing tests also passed, including optional sale pricing, multi-variant preservation, category restrictions, CSRF and pending seller restrictions.
- `php artisan route:list --json` ran before and after. Full JSON output was identical, including all URLs, names, methods, actions and middleware. The existing route fixture test also passed. No routes were added.
- `php artisan view:cache`: passed.
- `npm.cmd run build`: passed (Vite 7.3.6, 62 modules).
- PHP syntax checks: passed for all five changed/new PHP files.
- Pint `--test` on those five PHP files: passed.
- `git diff --check`: passed. Git status reviewed before and after.

## Browser verification and limits

Browser testing was not performed. Automated HTTP tests verify HTML and persistence but do not execute JavaScript or prove browser image decoding/delivery.

For manual verification with a seller account:

1. Open Seller Centre Products at the actual development URL, including its port. Confirm the existing three products show uploaded covers in both layouts. Check in DevTools that image requests use the page's origin and return image content successfully.
2. Create a legitimate product with multiple images. Confirm the first upload becomes its cover; refresh, log out, and log back in to confirm persistence.
3. Edit the product without changing images and confirm ordering is preserved. Select replacement, upload new images and save. Confirm the new first image becomes the cover and other product data stays intact.
4. Select individual removal checkboxes and save. Confirm remaining images retain their order and removing the last image yields the neutral placeholder.
5. In an isolated test environment, simulate a missing image and a browser delivery failure. Confirm graceful fallback and preservation of product/image records. Do not remove development assets merely to test this behavior.

No manual setup is needed in this workspace. Other deployments must serve their existing public storage mapping. Custom `ASSET_URL` settings follow Laravel's normal `asset()` behavior, just as Store Profile does. External storage providers, arbitrary historical filename conventions and auxiliary media URL migration are outside this task.
