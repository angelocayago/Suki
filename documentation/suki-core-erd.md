# SUKI Core Database ERD

```mermaid
erDiagram

    users {
        int8 id PK
        varchar name
        varchar email UK
        varchar first_name
        varchar last_name
        varchar phone UK
        bool is_suspended
    }

    roles {
        int8 id PK
        varchar name UK
    }

    role_user {
        int8 role_id PK, FK
        int8 user_id PK, FK
    }

    addresses {
        int8 id PK
        int8 user_id FK
        varchar label
        varchar recipient
        varchar phone
        varchar line1
        varchar barangay
        varchar city
        varchar province
        varchar postal_code
        bool is_default
    }

    wishlist_items {
        int8 id PK
        int8 user_id FK
        varchar product_slug
        varchar product_name
        int4 price_minor
        varchar image
    }

    sellers {
        int8 id PK
        int8 user_id FK
        varchar name
        varchar slug UK
        text description
        varchar logo_path
        varchar banner_path
        varchar status
        text rejection_reason
        int4 commission_bps
        int8 pickup_address_id FK
    }

    logistics_providers {
        int8 id PK
        int8 user_id FK
        varchar name
        varchar slug UK
        varchar status
        text rejection_reason
        varchar contact_phone
    }

    riders {
        int8 id PK
        int8 user_id FK, UK
        int8 logistics_provider_id FK
        varchar vehicle_type
        varchar plate_no
        bool is_active
    }

    categories {
        int8 id PK
        int8 parent_id FK
        varchar name
        varchar slug UK
        int4 position
        bool is_active
    }

    products {
        int8 id PK
        int8 seller_id FK
        int8 category_id FK
        varchar name
        varchar slug UK
        text description
        bool is_active
        timestamp deleted_at
    }

    product_variants {
        int8 id PK
        int8 product_id FK
        varchar sku UK
        varchar name
        json options
        int4 price_minor
        int4 stock
        int4 weight_grams
        bool is_active
        timestamp deleted_at
    }

    product_images {
        int8 id PK
        int8 product_id FK
        varchar path
        int4 position
    }

    carts {
        int8 id PK
        int8 user_id FK, UK
    }

    cart_items {
        int8 id PK
        int8 cart_id FK
        int8 product_variant_id FK
        int4 quantity
        bool selected
    }

    orders {
        int8 id PK
        int8 buyer_id FK
        varchar reference UK
        int4 total_minor
        varchar payment_method
        varchar payment_status
        json shipping_address
    }

    seller_orders {
        int8 id PK
        int8 order_id FK
        int8 seller_id FK
        int8 logistics_provider_id FK
        int4 subtotal_minor
        int4 shipping_fee_minor
        int4 commission_minor
        varchar status
        timestamp delivered_at
        text note
    }

    order_items {
        int8 id PK
        int8 seller_order_id FK
        int8 product_id FK
        int8 product_variant_id FK
        varchar product_name
        varchar variant_name
        int4 unit_price_minor
        int4 quantity
    }

    payments {
        int8 id PK
        int8 order_id FK
        varchar method
        int4 amount_minor
        varchar status
        varchar provider_ref
    }

    shipments {
        int8 id PK
        int8 seller_order_id FK, UK
        int8 logistics_provider_id FK
        int8 rider_id FK
        varchar tracking_code UK
        varchar status
        int4 fee_minor
        int4 cod_amount_minor
        bool cod_collected
        int4 attempts
    }

    delivery_events {
        int8 id PK
        int8 shipment_id FK
        varchar status
        int4 attempt
        int8 user_id FK
        text note
        varchar photo_path
        timestamp occurred_at
    }


    users ||--o{ role_user : "has"
    roles ||--o{ role_user : "assigned through"

    users ||--o{ addresses : "owns"
    users ||--o{ wishlist_items : "has"

    users ||--o{ sellers : "owns"
    addresses o|--o{ sellers : "pickup address"

    users ||--o{ logistics_providers : "owns"
    users ||--o| riders : "rider profile"

    logistics_providers ||--o{ riders : "manages"

    categories o|--o{ categories : "parent of"

    sellers ||--o{ products : "lists"
    categories ||--o{ products : "categorizes"

    products ||--o{ product_variants : "has"
    products ||--o{ product_images : "has"

    users ||--o| carts : "owns"
    carts ||--o{ cart_items : "contains"
    product_variants ||--o{ cart_items : "added as"

    users ||--o{ orders : "places"
    orders ||--o{ payments : "has"
    orders ||--o{ seller_orders : "split into"

    sellers ||--o{ seller_orders : "fulfills"
    logistics_providers o|--o{ seller_orders : "assigned to"

    seller_orders ||--o{ order_items : "contains"
    products ||--o{ order_items : "references"
    product_variants ||--o{ order_items : "references"

    seller_orders ||--o| shipments : "has shipment"
    logistics_providers ||--o{ shipments : "handles"
    riders o|--o{ shipments : "delivers"

    shipments ||--o{ delivery_events : "records"
    users ||--o{ delivery_events : "records"