# Buyer notifications handoff

Implemented on `skeleton` from `a756d9b`. The starting working tree was clean. The committed My Purchases, Order Received, receipt modal, image fixes, and three-day scheduler behavior were preserved.

## Existing components reused

- Laravel database notifications through `User::notifications()` and `unreadNotifications()`.
- `NotificationInboxController` and its existing inbox, unread-count, individual-read, and read-all routes.
- Shared notification bell component and existing Buyer navbar notification links.
- `DomainNotificationService`, `DomainEventNotification`, the existing outbox and retry command. Delivery remains synchronous after business commit, with existing durable retry behavior. No tables, flags, third-party services, queues, or workers were added.
- Existing ERP transitions, receipt confirmation endpoint, scheduler, Order Details, and Track Order routes.

## Gaps fixed and Buyer navigation

The Buyer navbar previously had text notification links but no visible bell. The shared generic inbox displayed stored notifications and supported marking read, but had no Buyer unread filter or ownership-checked purchase navigation. Order workflow messages used generic titles and omitted order references. Parent-only historical receipt completion produced no Buyer completion notification.

The navbar now renders one bell for Buyers, with the unread count and five recent updates. Open it to view recent notifications or choose **View all notifications**. Existing text links continue to open `/notifications/inbox`.

The Buyer inbox supports **All notifications** and **Unread**, newest-first ordering, 25 items per page, read/unread labels, individual read actions, mark-all-as-read, clear empty states, timestamps in PHT, and **View update** buttons. The dropdown remains usable through server-rendered forms; JavaScript refreshes its count and recent updates on opening, window focus, and restored browser navigation. It does not mark notifications read simply by opening the bell. Escape closes the dropdown and returns focus to its summary; outside clicks close it. On narrow screens the dropdown uses viewport margins and bounded scrolling.

**View update** and dropdown item forms use CSRF-protected `POST /notifications/{notification}/open`. This loads only the authenticated Buyer's own notification, resolves its order through that Buyer's ownership, marks the notification read, and redirects to the existing destination. Delivery updates use Track Order; confirmation/completion/checkout updates use Order Details. Existing voucher notifications retain their voucher destination. Unknown, deleted, inconsistent, or foreign-order contexts fall back to the inbox. Arbitrary stored URLs are ignored. No other Buyer's order reference is looked up for display.

## Order events

Existing successful SellerOrder transitions now produce readable Buyer titles/messages with their actual parent order reference:

| Actual transition result | Buyer title | Navigation |
| --- | --- | --- |
| `CONFIRMED` | Order confirmed | Order Details |
| `PICKED_UP` | Order shipped | Track Order |
| `OUT_FOR_DELIVERY` | Out for delivery | Track Order |
| `DELIVERED` | Order delivered | Track Order |
| `COMPLETED`, manual or automatic | Order completed | Order Details |

Other existing ERP status notifications continue to be delivered with the order reference. Existing checkout and cancellation notifications also resolve to owned purchases without changing their workflows. Historical parent-only completion now uses the same existing notification delivery service.

Only successful transitions produce notifications. Existing parent locks and transition checks prevent repeated status updates or receipt submissions from emitting duplicate notices. Existing outbox UUID delivery prevents retry duplication. For multiple stores, updates are per SellerOrder/parcel, preserving the existing event granularity. Completion text describes that store's items rather than claiming every parcel in a mixed purchase has finished. Notification messages contain no recipient address, phone, delivery proof, or personal details.

## Exact files

Modified:

- `app/Http/Controllers/NotificationInboxController.php`
- `app/Services/OrderWorkflowService.php` (Buyer notification content and historical completion notification only)
- `resources/views/components/buyer-navbar.blade.php`
- `resources/views/components/notification-bell.blade.php` (Buyer branch; existing other-role rendering preserved)
- `routes/web.php` (Buyer preview/open routes only)

Created:

- `app/Support/BuyerNotifications.php`
- `resources/views/buyer/notifications.blade.php`
- `resources/views/buyer/partials/notification-bell.blade.php`
- `resources/views/buyer/partials/notification-script.blade.php`
- `tests/Feature/BuyerNotificationsTest.php`
- `tests/JavaScript/buyer-notifications.test.cjs`
- `docs/buyer-notifications-handoff.md`

## Verification

- **87 PHP tests, 1,232 assertions passed** across BuyerNotificationsTest, BuyerPurchasesTest, NotificationInboxTest, NotificationOutboxTest, SellerHeaderNotificationsTest, PhaseTwoBCheckoutLifecycleTest, PhaseThreeBTrackingWaybillTest, and BuyerOrderCancellationTest.
- New notification tests cover the visible single bell, counts, unread filtering, individual/read-all actions, notification ownership, purchase ownership, safe destinations, existing order/tracking pages, pagination, escaping, suspended/guest/non-Buyer access, real authorized status transitions, repeated transitions, manual completion, exact three-day completion eligibility, repeated scheduler/retry execution, and historical parent-only completion.
- **23 JavaScript tests passed** across buyer-notifications, buyer-receipt, seller-header, and seller-order-status. Bell tests cover refresh/count handling, CSRF-protected POST links, empty states, escaped text, rejection of external actions, refresh failure feedback, Escape/outside-click behavior, initialization, and overlapping refreshes.
- Blade compilation, notification route checks, PHP syntax checks, `git diff --check`, and production Vite build passed. Build verification used a disposable directory outside the repository.
- Tests used forced SQLite `:memory:`, an empty `DB_URL`, synthetic records, and temporary Laravel storage. Existing migration-based regression tests migrated only the isolated in-memory database. No configured application database or shared Supabase connection was used.

## Limitations and handoff

Real-browser visual verification remains unverified. Automated tests exercise rendered HTML, request behavior, and JavaScript through DOM test harnesses; no actual browser success is claimed. The bell refreshes through normal navigation/open/focus events rather than real-time push. Existing historical messages are not rewritten, but their owned order contexts become clickable. Notifications without a valid supported context remain readable without invented destinations.

No schema migration or new scheduler/worker setup is required. Deploy source through the project's normal build (`npm run build`) and retain its existing Laravel scheduler/outbox retry setup. No live jobs were activated.

Final Git status: `skeleton`, **five modified tracked files and seven untracked new files**, all unstaged. Nothing was staged, committed, pushed, fetched, pulled, merged, reset, restored, stashed, cleaned, rebased, or switched. `.env`, `resources/js/landing.js`, `suki-mobile`, Return/Refund, Chat Add Photo, wallet, payout, and financial workflows were not modified.
