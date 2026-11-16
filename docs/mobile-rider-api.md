# Rider Mobile API (`/api/rider/*`)

This API serves the Rider screens of the `suki-mobile` Flutter branch. It matches
the endpoints already called by `lib/services/rider_service.dart`, so the app
needs no URL changes. It is additive: Buyer `/api/*` and `/api/v1/*` routes are
unchanged, and every operation reuses the same services as the web Rider portal
(`OrderWorkflowService`, `DeliveryCompletionService`, `CodRemittanceService`).

Base URL: `https://<host>/api` (Flutter `--dart-define=API_BASE_URL=.../api`).

## Authentication

| Step | Request | Notes |
|---|---|---|
| Sign in | `POST /api/login` `{login, password}` | `login` is an email or a PH mobile number. |
| Who am I | `GET /api/user` | Returns `role: "rider"` for rider tokens. |
| Sign out | `POST /api/logout` | Deletes only the current token. |

* A rider receives a token named `mobile-rider` with the single ability
  `rider`, valid for **30 days**. Buyers receive `mobile-buyer` with ability
  `buyer` (unchanged lifetime).
* Rider tokens are refused by every Buyer route (`403`). Buyer tokens, and any
  legacy wildcard (`*`) token, are refused by every Rider route (`403`).
* `/api/v1/login` stays Buyer-only; riders sign in through `/api/login`.
* Sign-in and every Rider request require an operationally eligible rider:
  active account, verified contacts, active Rider profile, approved logistics
  provider and (if set) an active hub of that provider. If the provider is
  suspended later, existing tokens stop working immediately.

Send `Authorization: Bearer <token>` and `Accept: application/json`.

## Response format

Success: `{"success": true, "data": { ... }}` with `Cache-Control: private, no-store`.

Errors use Laravel's JSON format: `401` unauthenticated, `403` not permitted,
`404` not found / not assigned to you, `422` `{"message", "errors": {field: [..]}}`,
`429` rate limited. Stack traces are never returned when `APP_DEBUG=false`.

Money is in **minor units** (centavos) in every `*_minor` field. `total` in
order rows is pesos for display only.

## Endpoints

| Method & path | Purpose | Body |
|---|---|---|
| `GET /rider/dashboard` | Profile summary + assigned orders | – |
| `GET /rider/deliveries` | Assigned pickup/delivery orders (`data.orders`) | – |
| `GET /rider/history` | Delivered/completed or failed-attempt orders | – |
| `GET /rider/earnings` | `data.performance` counters | – |
| `GET /rider/reports` | Assignment report (`data.shipments`) | `?from=&to=` (dates) |
| `GET /rider/money` | COD liability and shipping-fee earnings | – |
| `GET /rider/cod-remittances` | COD custody rows + summary | – |
| `POST /rider/cod-remittances/{id}` | Hand over collected COD to the hub | `reference` (required), `amount_minor` (optional declaration) |
| `GET /rider/profile` | Rider profile | – |
| `PATCH /rider/profile` | Change first/last name | `first_name`, `last_name` |
| `POST /rider/profile/password` | Change password | `current_password`, `password`, `password_confirmation` |
| `POST /rider/availability` | Go online/offline | `availability`: `ONLINE`/`OFFLINE` |
| `POST /rider/erp-orders/{sellerOrder}/accept-pickup` | Accept pickup assignment | – |
| `POST /rider/erp-orders/{sellerOrder}/decline-pickup` | Decline before accepting | – |
| `POST /rider/erp-orders/{sellerOrder}/accept-delivery` | Accept last-mile assignment | – |
| `POST /rider/erp-orders/{sellerOrder}/decline-delivery` | Decline before accepting | – |
| `POST /rider/erp-orders/{sellerOrder}/delivery-proof` | Complete delivery (multipart) | `proof_photo` (jpg/png/webp ≤5 MB), `received_by`, `delivery_note`?, `cod_collected=1` (required for COD) |
| `POST /rider/erp-orders/{sellerOrder}/failed-delivery` | Record failed attempt | `failure_reason`, `failure_notes` (required for `other`) |
| `POST /rider/erp-orders/{sellerOrder}/delivery-issue` | Report an issue | `issue_category`, `issue_details`? |
| `POST /rider/erp-orders/{sellerOrder}/status` | Only `DELIVERED` (same as delivery-proof). Pickup, sorting-center arrival and dispatch return `422` — they must go through the scanner. | `status` |
| `POST /rider/shipment-scanner/resolve` | Verify one exact parcel | `payload` (signed QR) **or** `tracking_code`; `mode`? |
| `POST /rider/shipment-scanner/{shipment}/action` | Record the one allowed scan action | `action`, `transfer_decline_reason` (for `decline_hub_transfer`) |

`failure_reason`: `buyer_unavailable`, `buyer_unreachable`, `incorrect_address`,
`access_issue`, `buyer_requested_reschedule`, `buyer_refused`, `other`.
`issue_category`: `buyer_unreachable`, `wrong_or_incomplete_address`,
`damaged_parcel`, `other`.

### Scanner security (equivalent of the web session rule)

The web scanner keeps "this rider just verified this exact parcel" in the
browser session for 15 minutes and forgets it after one action. The API keeps
the same record on the server, bound to **the rider and the exact access token**
that scanned:

1. `resolve` accepts a signed QR payload (HMAC with the app key; altered
   payloads are rejected) or an exact tracking code. Unknown parcels and parcels
   not assigned to this rider both return `404`.
2. It returns `data.allowed_action` (or `null`) and `data.mode`. If `mode` is
   omitted it is derived from the rider's role on the parcel
   (`pickup`, `sorting_center_arrival`, `last_mile_dispatch`,
   `hub_transfer_dispatch`).
3. `action` succeeds only if the same token verified the same shipment within
   15 minutes and the action equals the state machine's allowed action. The
   verification is claimed atomically and **consumed** on success, so replays,
   double taps and other devices fail with `422`. A failed action keeps the
   verification for a retry.
4. After every action `allowed_action` is `null`: the next step needs a new scan.

### Financial integrity

* COD collected is always the persisted `shipments.cod_amount_minor`; any
  amount fields sent by the client are ignored.
* Delivery proof is idempotent: a repeat submission does not create a second
  COD liability or earning.
* A remittance with no `amount_minor` declares the full expected amount; a
  different declared amount is recorded as a `DISCREPANCY` for Logistics, never
  as the accepted amount. Resubmitting a submitted remittance changes nothing.

### Profile rules

Phone, vehicle and plate number were verified/approved during onboarding. The
API accepts them only if unchanged; a different value returns `422` (contact the
logistics provider). A password change signs out every other device.

## Rate limits (per user)

All rider routes 120/min; scanner resolve 30/min; delivery proof 20/min;
remittance submit 10/min; password change 5/min; sign-in 10/min.

## Flutter compatibility notes (no change made to `suki-mobile`)

* All URLs, field names and the `{success, data}` envelope match `rider_service.dart`.
* `rider_deliveries.dart` "Confirm pickup" calls the scanner action without a
  scan. The API refuses this with the message *"Scan and verify this exact
  shipment before recording this rider workflow action."* — the same rule as
  the web portal. Recommended app change: send the rider to the scanner screen
  for this step.
* The Logistics screens of the app do not call any API, so no Logistics mobile
  API was added.

Tests: `tests/Feature/RiderMobileApiTest.php`.
