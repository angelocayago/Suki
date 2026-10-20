# S-PROD-00C implementation report

Implemented locally on `seller-registration`, preserving existing uncommitted work. No routes, migrations, approval rules, category assignments or development database records were changed. Nothing was staged, committed or pushed.

## Discovered implementation

The S-PROD-00B edit section displayed image removal checkboxes, a replace-all checkbox and a native multiple-file picker. The existing controller/save helper could replace the complete image set or remove selected records, but could not replace an individual image. Existing `Product::images()` sorts by position then ID; its first available file supplies the cover. Main assets use public-disk `products/` paths and the active-site URL helper. These conventions remain intact.

Add Product already used hidden inputs with JavaScript preview tiles and a label-based add control. The logout layout already contained one native dialog, two triggers, a CSRF-protected POST form, cancel/focus handling and a submission guard. Its behavior needed no rewrite.

## Files changed in this task

- `app/Http/Controllers/Seller/ProductController.php`: validation and persistent upload handling for `replacement_images[image_id]` and `add_images[]`, using the existing product update endpoint and failed-upload cleanup.
- `app/Support/SellerProducts.php`: validates image ownership against the locked product relationship, replaces selected paths in place, appends additions, enforces the combined five-image limit and rejects conflicting removal/replacement requests. Upload fields are excluded from product JSON metadata.
- `resources/views/seller/partials/product-image-controls.blade.php`: new shared edit section with hidden pickers, styled Replace/Remove buttons, preview, cancellation, removal/undo, pending-save feedback, Add Images and validation messages.
- `resources/views/seller/products-edit.blade.php`: includes that section in place of the old controls.
- `resources/views/seller/products-create.blade.php`: makes Add Images a keyboard-accessible button, labels preview removal as Remove, avoids filenames in preview descriptions and checks selection MIME type/size before accepting it.
- `resources/views/layouts/seller.blade.php`: changes only the two visible logout labels to exactly **No** and **Log Out**.
- `tests/Feature/SellerProductThumbnailTest.php`: seven additional feature tests plus an updated assertion for the retired replace-all UI.
- `tests/js/seller-image-controls.test.mjs`: five dependency-free Node interaction tests running the production scripts against DOM doubles.
- `documentation/S-PROD-00C.md`: this report.

Other modified/untracked files already present belong to existing local work, including S-PROD-00B. They were preserved.

## Replace, remove and add behavior

Each existing image has a green Replace button and red-accented Remove button. Replace opens a single-file picker for that image's ID. A valid selection displays a local preview and pending-save message. Cancel replacement restores the original preview. Pending removal can be undone; it disables that image's replacement upload while retaining any selected replacement for undo. No click performs a backend mutation. Empty replacement inputs stay disabled so untouched images are not submitted as empty uploads.

The backend applies individual replacement by updating only the selected ProductImage path. Its ID and position remain unchanged; unrelated records and files stay intact. Removing the cover allows the next available image to become the cover; removing later images keeps the first cover. Additions append after the greatest remaining position. The final count is checked under the existing product transaction/lock, including simultaneous removals and additions.

The edit UI no longer submits the old replace-all operation. Its backend contract is retained for compatibility and prior tests; individual replacement never uses it. Conflicting operations are rejected.

Both forms preserve the five-image limit and JPG/PNG/WebP, 5 MB per-image rules. Client checks improve feedback; backend image/MIME/size rules remain authoritative. Tests include actual non-image content carrying a PNG filename and an oversized image. Replacement/removal IDs must belong to the selected product and the product must belong to the current seller. Foreign IDs, including another product owned by the same seller, fail safely.

Failed validation or saves preserve the old database state and files. Failed save cleanup removes only new uploads from that request. As in S-PROD-00B, old physical files are retained on successful replacement/removal to protect shared or legacy references; file reclamation is outside scope. Existing valid images need no re-upload. Active-site URLs, generated filenames, missing-file fallback and persisted records remain the source of truth.

## Logout

The modal labels are exactly **No** and **Log Out**. Its title, explanation, size, position, colors, overlay and animation are unchanged. Sidebar and account dropdown still open the same shared dialog. No closes it and restores focus without submitting; confirmation permits the original protected form submission. Tests exercise both trigger handlers and the cancel/submit guard. HTTP tests verify session logout, the existing login redirect and production CSRF rejection without a token. No logout route or JavaScript was changed.

## Verification

- `php artisan test`: **220 passed, 2,338 assertions**.
- `php artisan test --filter=SellerProduct`: **38 passed, 551 assertions**.
- `node --test tests/js/seller-image-controls.test.mjs`: **5 passed**.
- PHP syntax checks: passed for the modified controller, support helper and PHP test.
- Pint: fixed test formatting, then `--test` passed for those three files.
- `php artisan view:cache`: passed.
- `npm.cmd run build`: passed (Vite 7.3.6, 62 modules).
- `git diff --check`: passed. Git reports an informational CRLF-to-LF normalization warning for the seller layout.
- `php artisan route:list --json`: recorded before and after; full JSON was identical, including methods, URLs, names, actions and middleware. The existing route fixture test passed as well.
- `git status --short`: reviewed before and after; branch remains `seller-registration`.

The first full/filtered PHP runs overlapped and interfered with the shared fake-storage directory. Both were rerun sequentially to obtain the passing results above. No production/development product fixtures were inserted; tests use isolated databases and fake storage. The full suite includes pricing, categories, ownership, variants, pending-seller restrictions, My Profile and Store Profile regression coverage.

## Browser verification and remaining limits

Manual browser verification remains outstanding. Node DOM doubles exercise event behavior but do not verify native file dialogs, image decoding, focus rendering or desktop/mobile layout in a real browser.

Manual checks:

1. Edit a real product with multiple images. Replace one image, inspect its preview, save and refresh; confirm its position and the other images are unchanged. Repeat for the cover and a later image.
2. Mark one image for removal, undo it, then remove and save. Confirm only that record disappears and the next available image becomes cover when appropriate.
3. Use Add Images on Edit and Add Product, inspect previews, remove a selected preview, then save. Confirm count/type/size feedback and persistence after refresh and logout/login.
4. Open Sidebar Sign Out, verify No/Log Out, cancel and verify focus/sign-in. Open Account Sign Out, verify the same dialog, confirm logout and verify the existing redirect.
5. Check thumbnails, optional sale pricing, categories, stock/SKU and both profile pages on desktop and mobile.

No migrations or manual storage setup are required. File selections cannot survive a server validation redirect and must be chosen again, as explained in the UI; existing images and pending removal IDs are preserved. JavaScript is required for image controls, with a noscript message. Existing advanced auxiliary media controls on Add Product retain their prior behavior. No remaining backend failures were observed in the sequential tests.
