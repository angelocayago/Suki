# Store Profile save and image display fix

## Findings

- The form already used multipart POST; `profile_photo` and `banner` were inside it and matched controller validation. The canonical Seller fields are `logo_path` and `banner_path`, and both are fillable.
- The controller writes to the existing public local disk under `seller-store-assets/`. Files existed in that directory, but the current project had no `public/storage` link. Thus `/storage/...` URLs could fail despite successful file/database persistence. No real Seller database rows were inspected or changed to diagnose this.
- Created and verified the missing local Windows junction with Laravel's `storage:link`, connecting only `public/storage` to `storage/app/public`. No existing image files were deleted or replaced by this repair.
- The success flash appeared above the section navigation, while the redirect targeted the Shop Profile section. The message could be outside the visible area near Save.
- The old save handler accepted an unchecked false storage return value and deleted old images inside the database transaction, before commit. A failed write or database rollback could therefore leave an incorrect reference or a missing old image.
- The Buyer storefront rendered the logo but did not render a stored banner.

## Changes

Image writes are verified before their canonical references are saved. New files are staged once outside transaction retries. A database rollback or later image-write failure cleans staged files; original files are retained until commit. Old-file cleanup is limited to recognized store-image paths and skips any path still referenced by either image field on any Seller. Cleanup failure is reported internally without converting an already committed profile into a failed save.

Profile and removal redirects now target `#store-profile-feedback`, a single accessible success message directly beside the Save action. Address feedback remains separate. Saved-image load failures show an explicit storage/display message rather than silently appearing as empty images.

Both saved image fields are rendered after fresh requests. The Buyer storefront now renders its existing banner field. Added a small POST banner-removal action matching the logo-removal flow, with the same controller ownership/active/approved checks and web CSRF protection. Logo and banner removal submit independent forms. Existing image limits remain 2 MiB for logos and 4 MiB for banners.

## Local and deployment setup

The localhost storage link is repaired. CLI PHP currently loads `C:\php\php.ini` with `upload_max_filesize=2M` and `post_max_size=8M`. To upload banners above 2 MiB, change only:

```ini
upload_max_filesize=4M
```

Restart the PHP development server. The current 8M POST limit accommodates a 2 MiB logo plus a 4 MiB banner and this form's fields. The web runtime's loaded INI was not independently verified. No INI or `.env` was edited automatically.

Render still uses the existing local public filesystem. This fix does not make it durable across redeployments/restarts, nor recover files previously lost from an ephemeral instance. A storage link must also exist in the deployment (`php artisan storage:link` if missing). Persistent deployment uploads require separately configured durable storage; no provider, volume, environment-variable change or infrastructure redesign was introduced. Cloudflare does not supply persistence for these local upload files.

## Verification and boundaries

22 PHP tests / 360 assertions and 7 JavaScript tests passed using guarded SQLite `:memory:` and uniquely named disposable public storage directories. Coverage includes text saves, nonduplicated near-action feedback, JPG/PNG/WEBP, both images across fresh requests, logo/banner replacement and removal, canonical URLs, Buyer storefront display, validation, failed writes, second-file failure, real database rollback cleanup, shared-reference protection, authorization, CSRF, untouched registered fields, address/postal resolution and My Profile behavior.

PHP/Blade syntax, rendered JavaScript parsing and `git diff --check` passed. Browser access reported that its access host was unavailable; visual verification and actual browser multipart-limit checks remain outstanding. PostgreSQL concurrency/crash recovery were not dynamically tested. Filesystem and database writes are not one distributed transaction; process termination or failed cleanup can still leave orphan files.

No application migration was run, no shared Supabase test records or real profiles were written, no real image upload was automated, and `.env`, `landing.js`, registration, finances and unrelated modules were preserved. The local storage junction is an intentional environment repair, not a tracked asset copy. No commit/push occurred.

## Exact files

- `app/Http/Controllers/Seller/StoreProfileController.php`
- `routes/web.php` (only the banner-removal route added in this task)
- `resources/views/seller/store-profile.blade.php`
- `resources/views/seller/store-profile-script.blade.php`
- `resources/views/seller/store-profile-style.blade.php`
- `resources/views/buyer/store.blade.php` (saved banner display)
- `tests/Feature/SellerProfilesTest.php`
- `docs/store-profile-save-review.md`
