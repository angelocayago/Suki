<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Shipping Label - {{ $shipment->tracking_number }}</title>

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet">

  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

  @vite('resources/css/shipments-label.css')

  <style>
    .address-block {
      width: 100%;
      max-width: 100%;
      overflow-wrap: anywhere;
      word-break: break-word;
      white-space: normal;
    }

    .buyer-address {
      display: block;
      width: 100%;
      max-width: 100%;
      overflow-wrap: anywhere;
      word-break: break-word;
      white-space: normal;
    }
  </style>
</head>

<body>
  @php
  $sellerOrder = $shipment->sellerOrder;
  $order = $sellerOrder?->order;
  $sellerShop = $sellerOrder?->seller;
  $sellerUser = $sellerShop?->owner;
  $pickupAddress = $sellerShop?->pickupAddress;
  $buyer = $order?->user;
  $parcelItems = $sellerOrder?->items ?? collect();

  /*
  |--------------------------------------------------------------------------
  | Buyer Delivery Address
  |--------------------------------------------------------------------------
  |
  | delivery_address can be either:
  | - JSON string
  | - array
  | - normal plain-text address
  |
  */

  $rawDeliveryAddress = $shipment->delivery_address;

  $deliveryData = null;

  if (is_array($rawDeliveryAddress)) {
  $deliveryData = $rawDeliveryAddress;
  } elseif (is_string($rawDeliveryAddress)) {
  $decoded = json_decode($rawDeliveryAddress, true);

  if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
  $deliveryData = $decoded;
  }
  }

  $buyerName =
  $deliveryData['recipient']
  ?? $buyer?->name
  ?? 'Buyer';

  $buyerPhone =
  $deliveryData['phone']
  ?? $buyer?->phone;

  if ($deliveryData) {
  $buyerAddress =
  $deliveryData['address']
  ?? $deliveryData['full_address']
  ?? $deliveryData['address_line1']
  ?? '';
  } else {
  $buyerAddress = is_string($rawDeliveryAddress)
  ? $rawDeliveryAddress
  : '';
  }
  @endphp

  <div class="no-print text-center mb-3">
    <button
      onclick="window.print()"
      class="btn btn-primary">
      <i class="bi bi-printer"></i>
      Print Label
    </button>

    <a
      href="{{ url()->previous() }}"
      class="btn btn-secondary">
      <i class="bi bi-arrow-left"></i>
      Back
    </a>
  </div>

  <div class="shipping-label">

    <div class="label-header text-center">
      <h4 class="mb-0">
        BILI HUB SHIPPING LABEL
      </h4>

      <small>
        {{ $shipment->logistic->company_name
            ?? $shipment->logistic->business_name
            ?? 'Speedy Express' }}
      </small>
    </div>

    <div class="tracking-number">
      <i class="bi bi-upc-scan"></i>
      {{ $shipment->tracking_number }}
    </div>

    <div class="barcode">
      *{{ $shipment->tracking_number }}*
    </div>

    <div class="qr-section text-center my-3">
      <div class="fw-bold mb-2">
        SCAN TO CONFIRM
      </div>

      <img
        src="{{ route('shipments.qr.image', $shipment) }}"
        alt="Shipment QR Code"
        width="220"
        height="220"
        style="
          display: block;
          margin: 0 auto;
          background: white;
        ">

      <div class="small mt-2">
        <strong>
          {{ $shipment->tracking_number }}
        </strong>
      </div>
    </div>

    <div class="row">

      {{-- SELLER --}}
      <div class="col-6">
        <div class="address-block">

          <strong>
            FROM (Seller):
          </strong>

          <br>

          {{ $sellerShop?->name
              ?? $sellerUser?->business_name
              ?? $sellerUser?->store_name
              ?? $sellerUser?->name
              ?? 'Seller' }}

          <br>

          @if($pickupAddress)

          {{ $pickupAddress->address_line1 }}

          @if($pickupAddress->address_line2)
          {{ $pickupAddress->address_line2 }}
          @endif

          <br>

          {{ $pickupAddress->city }},
          {{ $pickupAddress->province }}
          {{ $pickupAddress->postal_code }}

          <br>

          {{ $pickupAddress->country }}

          <br>

          @if($pickupAddress->phone)
          <i class="bi bi-telephone"></i>
          {{ $pickupAddress->phone }}

          @elseif($sellerUser?->phone)
          <i class="bi bi-telephone"></i>
          {{ $sellerUser->phone }}
          @endif

          @elseif($sellerUser)

          {{ $sellerUser->house_number }}
          {{ $sellerUser->street_address }}

          <br>

          {{ $sellerUser->barangay_name ?? $sellerUser->barangay }},
          {{ $sellerUser->municipality_name ?? $sellerUser->municipality }}

          <br>

          {{ $sellerUser->province_name ?? $sellerUser->province }},
          {{ $sellerUser->region_name ?? $sellerUser->region }}

          <br>

          @if($sellerUser->phone)
          <i class="bi bi-telephone"></i>
          {{ $sellerUser->phone }}
          @endif

          @else

          {{ $shipment->pickup_address }}

          @endif

        </div>
      </div>

      {{-- BUYER --}}
      <div class="col-6">
        <div class="address-block">

          <strong>
            TO (Buyer):
          </strong>

          <br>

          <strong>
            {{ $buyerName }}
          </strong>

          <br>

          @if($buyerAddress)
          <span class="buyer-address">
            {{ $buyerAddress }}
          </span>
          @else
          <span class="text-muted">
            No delivery address available
          </span>
          @endif

          @if($buyerPhone)
          <div class="mt-1">
            <i class="bi bi-telephone"></i>
            {{ $buyerPhone }}
          </div>
          @endif

        </div>
      </div>

    </div>

    <div class="row mt-2">

      <div class="col-6">
        <small>

          <strong>
            Order #:
          </strong>

          {{ $order?->order_number ?? 'N/A' }}

          @if($sellerOrder)
          <br>

          <strong>
            Parcel #:
          </strong>

          {{ $sellerOrder->id }}
          @endif

        </small>
      </div>

      <div class="col-6 text-end">
        <small>

          <strong>
            Date:
          </strong>

          {{ $shipment->created_at->format('M d, Y') }}

        </small>
      </div>

    </div>

    @if($parcelItems->count() > 0)

    <div class="mt-3">

      <strong>
        Items:
      </strong>

      <ul class="small mb-0">

        @foreach($parcelItems as $item)

        <li>

          {{ $item->product_name }}
          x{{ $item->quantity }}

          @if($item->size)
          ({{ $item->size->name }})
          @endif

        </li>

        @endforeach

      </ul>

    </div>

    @endif

    <div class="mt-3 text-center small text-muted">

      <i class="bi bi-info-circle"></i>

      Rider must scan QR code upon pickup to confirm parcel.

    </div>

  </div>

</body>

</html>