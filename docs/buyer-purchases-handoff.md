# Buyer My Purchases and Order Received

Implemented on `skeleton`, based on `e5f8847`. The starting working tree was clean. No changes were staged, committed, or pushed.

## Reused application components

- Existing `buyer.my-orders`, `buyer.order-details`, and `order.status.update` routes and Blade pages.
- Existing `Order`, `SellerOrder`, `Shipment`, `DeliveryEvent`, and `OrderStatus` conventions.
- `OrderWorkflowService::transition()` for parcel completion, parent synchronization, events, and existing notifications/side effects. Existing wallet behavior is unchanged; no financial functionality or flags were added or enabled.
- Existing tracking controller/routes and Seller Orders/dashboard queries. Existing Return/Refund, cancellation, reviews, and Buy Again actions are preserved.
- Existing `orders:complete-delivered` command and hourly scheduler registration.

## Navigation and categories

Buyer: existing **My Orders** navigation → `/my-orders` (**My Purchases**) → category tabs → **View Order** at `/my-orders/{order_number}`. Track Order uses the existing numeric order/seller-order route.

Seller: Dashboard or Orders → Completed filter (`/seller/orders?status=COMPLETED`). Both use the same updated SellerOrder records.

Tabs have owner-scoped counts and category empty states. To Pay contains placed, unpaid/pending/failed non-COD purchases. COD purchases remain in To Ship until collected, then To Receive through delivery. Completed and Cancelled have their own tabs. Delivery failures and return-to-seller fulfillment states remain visible under To Receive and All with their actual status labels. Return/Refund remains accessible through existing Order Details controls and existing feature availability.

Cards now include store names, variants, quantities, totals, parcel delivery status, delivery timestamps and automatic completion eligibility times in PHT, receipt actions, and existing tracking links. Tabs scroll on narrow screens and actions wrap.

## Manual receipt

Delivered purchases show **Order Received** on the list and details pages. The custom SUKI SHOP confirmation dialog precedes the existing CSRF-protected POST `/orders/{order_number}/status` with `status=completed`.

Ownership and active-buyer authorization are checked before accepting completion, including repeat requests. `confirmReceipt()` locks the parent and parcels in one transaction. Every unfinished parcel must pass the existing Delivered → Completed workflow, including shipment consistency. The parent, SellerOrder, and Shipment status conventions remain synchronized. Mixed/inconsistent parcels roll back together and return visible error feedback. Parent-only historical purchases also complete under the parent lock. Repeated receipt requests return success without duplicate events, notifications, or completion timestamps. Completed purchases hide the action.

Manual confirmation does not require waiting three days. Automatic completion uses each parcel's delivery timestamp independently; manual confirmation applies to the whole purchase once its parent is Delivered.

## Automatic completion

The existing hourly `orders:complete-delivered` command completes parcels whose `delivered_at` is at least three full days old. It revalidates eligibility inside the existing locked workflow, skips cancelled purchases, and synchronizes the parent. A purchase with a parcel still in transit remains incomplete. Historical parent-only Delivered orders use their own recorded delivery timestamp.

Missing delivery timestamps are never invented. The command prints the specific seller-order ID or historical order number and exits with status 1 when missing timestamps or inconsistent records need attention. Eligible unaffected orders still complete. Repeated runs are safe. Unexpected operational exceptions remain visible as command failures.

The hourly cadence means completion occurs on the first successful run after the 72-hour boundary, potentially up to one hour later. No buyer visit is required.

## Deployment

No schema migration is needed. Build frontend assets through the project's existing deployment build (`npm run build`).

The deployment must run Laravel's scheduler every minute from the deployed project directory:

```cron
* * * * * cd /absolute/path/to/project && php artisan schedule:run
```

The existing hourly registration invokes `php artisan orders:complete-delivered`. Keep scheduler output available for missing-timestamp/inconsistency diagnostics. No production scheduler, Render, Supabase, or production credentials were accessed or configured during implementation.

## Verification

All PHP tests used forced SQLite `:memory:` connections, empty `DB_URL`, synthetic fixtures, and temporary Laravel storage. The new feature suite additionally refuses to run without those database isolation settings and prevents stray HTTP requests. No configured application database migrations were run.

- Focused regression run: **77 tests, 1,202 assertions passed** across BuyerPurchasesTest, BuyerOrderCancellationTest, PhaseTwoBCheckoutLifecycleTest, PhaseTwoCheckoutTest, PhaseThreeBTrackingWaybillTest, SellerOrdersRestorationTest, and SellerOrderStatusWidgetTest.
- Broader run adding PhaseTwoSellerFulfillmentTest: **84 tests, 1,235 assertions; 83 passed, one existing failure**. `test_session_orders_remain_available_when_the_seller_has_no_database_orders` expects `Legacy Session Item` in Seller Orders. The same failure was reproduced using the original `e5f8847` web routes loaded in an isolated test. That unrelated behavior was left unchanged.
- New tests cover category filtering and empty states, listing/details ownership, manual completion immediately after delivery, Seller Orders/dashboard visibility, tracking, repeated receipt, exact three-day boundary, one second before the boundary, missing delivery timestamps, undelivered/cancelled purchases, repeat scheduled runs, parent-only historical orders, inconsistent shipment reporting, and mixed-parcel rollback.
- JavaScript: **29 tests passed** across buyer-cancellation, seller-order-status, delivery-completion-dialog, and delivery-proof-upload.
- Blade compilation, route registration checks, schedule registration check, PHP syntax checks, and `git diff --check` passed.
- Production Vite build passed using a disposable output directory outside the repository; tracked build artifacts and `landing.js` were not modified.
- Browser visual testing was not performed. Desktop/mobile appearance and actual browser confirmation interactions remain unverified; feature tests exercised rendered pages and POST actions.

## Files changed

Modified:

- `app/Services/OrderWorkflowService.php`
- `routes/web.php`
- `routes/console.php`
- `resources/views/buyer/my-orders.blade.php`
- `resources/views/buyer/order-details.blade.php`

Created:

- `app/Support/BuyerPurchases.php`
- `resources/views/buyer/partials/receipt-action.blade.php`
- `resources/views/buyer/partials/purchase-delivery.blade.php`
- `tests/Feature/BuyerPurchasesTest.php`
- `docs/buyer-purchases-handoff.md`

The initial implementation left five modified tracked files and five untracked new files listed above. `.env`, `resources/js/landing.js`, `suki-mobile`, feature flags, and existing financial/Return/Refund implementations were not modified.

## UI polish follow-up

All preceding uncommitted functionality was preserved. This follow-up does not change the receipt backend, order transitions, checkout, tracking, scheduler, financial logic, Return/Refund, or chat attachments.

The native `window.confirm()` has been replaced with one green-and-white `<dialog>` per purchase page. It displays **Confirm Order Received**, **Have you received your order? Confirm to complete your purchase.**, **Cancel**, and **Confirm Received**. Cancel receives initial focus; Tab stays within the modal; Cancel, Escape, or a backdrop click dismisses it and restores focus to the original action. Confirmation submits the selected original form with its original CSRF/status fields. Controls disable during submission, repeated confirmation cannot send another request, and back navigation resets the UI. Existing redirect success/error feedback remains unchanged. JavaScript-disabled browsers show an explanatory message and cannot accidentally bypass confirmation.

Receipt guidance now reads: **Received your order? Tap "Order Received" to complete your purchase.** Smaller secondary text reads: **Orders are automatically completed 3 days after delivery.**

Confirmed image defect: normal checkout can snapshot a relative `products/...` public-disk path, while the old list/details `<img>` tags used that string directly as a browser URL. `BuyerPurchases::image()` now resolves those paths using the existing public storage disk convention, supports `storage/...` and `/storage/...` prefixes, preserves HTTP(S) URLs, and supports existing public `images/...` files. Missing local files yield a placeholder immediately. Remote or otherwise failed loads hide the broken image and show the same contained accessible placeholder. List/details thumbnail dimensions remain 80/96 pixels with `object-cover`, fixed containers, and overflow containment. No media records, uploads, files, or storage configuration were changed.

The named real purchases **Gelo** and **MGA URI NG GELO** were not queried because shared Supabase access is prohibited. Their specific original image files cannot be confirmed or recovered here; missing sources remain honestly marked unavailable. No replacement product photographs were invented.

Follow-up files updated:

- `app/Support/BuyerPurchases.php`
- `resources/views/buyer/my-orders.blade.php`
- `resources/views/buyer/order-details.blade.php`
- `resources/views/buyer/partials/receipt-action.blade.php`
- `tests/Feature/BuyerPurchasesTest.php`
- `docs/buyer-purchases-handoff.md`

Follow-up files created:

- `resources/views/buyer/partials/receipt-dialog.blade.php`
- `resources/views/buyer/partials/receipt-script.blade.php`
- `resources/views/buyer/partials/purchase-thumbnail.blade.php`
- `tests/JavaScript/buyer-receipt.test.cjs`
- `tests/Support/BuyerPurchasesSchema.php`

Follow-up validation:

- **9 PHP feature tests, 168 assertions passed**, including the preserved ownership, manual receipt, Seller Orders/dashboard, tab filtering, tracking, three-day boundary, and repeat-run tests, plus thumbnail resolution/fallback rendering. Tests now create disposable synthetic SQLite memory tables directly through `BuyerPurchasesSchema`; no application migrations were run. These fixtures exercise application behavior rather than production schema constraints.
- **19 JavaScript tests passed** across receipt/fallback, cancellation, and Seller order status. Receipt tests exercise open, Cancel, Escape, focus wrapping/restoration, correct form selection, CSRF/status preservation, double submission prevention, back navigation, initialization, responsive CSS structure, and failed-image fallback.
- Blade compilation, purchase/receipt route checks, PHP syntax validation, `git diff --check`, and a production Vite build into a disposable directory outside the repository passed.
- Actual browser visual testing was not performed. Desktop/mobile appearance and real browser interactions remain visually unverified; no actual browser success is claimed.

Final Git status after UI polish: **five modified tracked files and ten untracked new files**, all unstaged. Branch remains `skeleton`. Nothing was staged, committed, pushed, or migrated.
