# SUKI SHOP — Defense Readiness (review branch `review/skeleton-integration-20261116`)

This branch contains the integration work, plus `origin/skeleton` (including
Lance's academic wallet commit `72354d0`, kept unchanged and gated off). It is
published for review and has **not** been merged into `skeleton`. See
"Decisions needed" below for why.

## Run the web application

```bash
composer install
npm install
npm run build              # or: npm run dev (hot reload)
php artisan storage:link   # once, for public product images
php artisan serve          # http://127.0.0.1:8000
```

Configure `.env` for your own demo database first. Run `php artisan migrate`
only against a disposable demo database, never a shared or production one.
Checkout is COD-only: `config/suki_wallet.php` keeps `checkout_enabled=false`.

### Mobile app against the local server

```bash
# Android emulator reaches the host as 10.0.2.2
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
# Physical phone on the same Wi-Fi: use the laptop's LAN IP and
#   php artisan serve --host=0.0.0.0 --port=8000
```

Rider API reference: `docs/mobile-rider-api.md`.

## Run the automated tests (isolated, in-memory SQLite)

```bash
# Main suite
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php vendor/bin/phpunit
# Suites that need disposable private storage roots (complaints, chat photos, money)
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php vendor/bin/phpunit -c phpunit.handoff.xml
# Front-end behaviour tests
for f in tests/JavaScript/*.test.cjs; do node --test "$f"; done
```

The `IsolatedComplaint*` suites only pass under `phpunit.handoff.xml`; they
fail by design under the default config (no disposable directory).

## Acceptance walkthrough

Use demo accounts on a disposable database. Each step lists the screen.

| Role | Step | Where |
|---|---|---|
| Buyer | Log in, browse, search, filter, open a product | `/login`, `/shop` |
| Buyer | Add to cart, change quantity, manage addresses | Cart, My Account → Addresses |
| Buyer | COD checkout, view order, track, cancel while `PLACED` | Checkout, My Purchases |
| Buyer | Confirm receipt, request a return with evidence | Order details → Return/Refund |
| Buyer | Open and print the return waybill (A4) | Return request → Print Return Waybill |
| Seller | Log in, create/edit a product with variants and images | Seller Centre → Products |
| Seller | Review orders, prepare and hand over a parcel | Seller Centre → Orders |
| Seller | Receive returned item, inspect, restock if eligible | Seller Centre → Returns |
| Logistics | Receive at sorting centre, sort, assign Rider, dispatch | Logistics → Shipment Scanner / Assignments |
| Logistics | Return Pickup queue, assign Rider, receive at hub | Logistics → Return pickups |
| Rider (web or app) | Accept, scan the exact parcel, pick up, deliver with proof, COD | Rider → Deliveries / Scanner |
| Rider | Review COD remittances and money report | Rider → COD remittances / Money |
| Admin | Approve Seller/products, monitor orders, COD, returns, audit | Admin dashboard |

A printed waybill never authorizes a scan, status change or payment; every
handoff still needs the exact scanned reference.

## Fixed in this integration pass

* `ReturnRefundService` did not parse after an earlier merge (missing brace):
  every return/refund, waybill and COD-custody path failed.
* Rider Deliveries page did not compile (inline `@php(...)` paired with a later
  `@endphp`): `/rider/deliveries` returned 500.
* Buyer web address list/edit/default/delete returned 500 after a service
  refactor changed controller signatures.
* Two return waybill routes shared one URL; the Buyer label route name vanished
  and its redirect crashed.
* Admin payout history crashed on any pending row (forms to removed routes).
* Rider performance counted deliveries by Rider id instead of user id.
* Checkout showed a disabled "Suki Wallet — unavailable" option; it is now
  hidden while the wallet gate is off.
* COD remittance service accepts both teams' call contracts and rejects
  arbitrary review statuses.
* New secure Rider mobile API (`/api/rider/*`).

## Decisions needed before merging into `skeleton`

These are business-rule conflicts between two working designs, introduced by
earlier merges. They were not changed silently:

1. **Seller approval of a RETURN.** Integration routes it into the Rider
   Return Pickup workflow (requires an active destination hub,
   `RETURN_AUTHORIZED`). `skeleton` finalizes it as Seller-approved
   (`ADMIN_APPROVED`) with a Buyer-printed label and wallet refund funding.
   Lance's waybill, seller-review, refund-funding and wallet-foundation tests
   assume the second design.
2. **COD settlement model.** Integration releases Seller earnings when the hub
   verifies cash and settles offline through Logistics. `skeleton` holds
   earnings until Admin accepts the upstream remittance and uses the
   simulation-settlement / academic-wallet ledger. Lance's held-earning,
   custody, simulation and academic-wallet tests assume the second model.

Until one model is chosen for each, merging would make one team's tests and
features fail.
