<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\{Address, Category, DeliveryEvent, LogisticsHub, LogisticsProvider, LogisticsSortingZone, LogisticsSortingZoneArea, Order, PresentationImportRecord, Product, Rider, RiderServiceArea, Seller, SellerOrder, TransactionReview, User};
use App\Services\Admin\ProductModerationService;
use App\Support\DemoProductImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Explicitly-run, non-production presentation data.  The record table makes
 * retries additive and makes it impossible for this batch to overwrite an
 * untracked account, product, order, or review.
 */
class PresentationMarketplaceService
{
    public const BATCH = 'suki-presentation-v1';

    private const LOCATION = [
        'region' => 'Region IV-A / CALABARZON', 'region_code' => '0400000000',
        'province' => 'Laguna', 'province_code' => '0403400000',
        'city' => 'Santa Cruz', 'city_municipality_code' => '0403426000',
        'barangay' => 'Duhat', 'barangay_code' => '0403426005', 'postal_code' => '4009',
    ];

    /** Customer-facing names intentionally contain no importer/test labels. */
    private const SELLERS = [
        ['key' => 'sampaguita-pantry', 'owner' => 'Mariel Santos', 'shop' => 'Sampaguita Pantry', 'products' => ['Coconut Sugar Granules', 'Calamansi Honey Tea', 'Native Cacao Tablea', 'Banana Crisp Bites']],
        ['key' => 'laguna-crafts', 'owner' => 'Paolo Reyes', 'shop' => 'Laguna Home Crafts', 'products' => ['Abaca Market Basket', 'Bamboo Serving Tray', 'Woven Coaster Set', 'Rattan Storage Caddy']],
        ['key' => 'harvest-table', 'owner' => 'Liza Mercado', 'shop' => 'Harvest Table Produce', 'products' => ['Fresh Ginger Bundle', 'Laguna Coffee Beans', 'Muscovado Baking Sugar', 'Dried Mango Slices']],
        ['key' => 'everyday-essentials', 'owner' => 'Noel Garcia', 'shop' => 'Everyday Essentials PH', 'products' => ['Cotton Kitchen Towel Set', 'Glass Food Container Set', 'Laundry Soap Bar Pack', 'Reusable Shopping Tote']],
        ['key' => 'isla-wellness', 'owner' => 'Aira Villanueva', 'shop' => 'Isla Wellness Goods', 'products' => ['Virgin Coconut Oil', 'Lemongrass Bath Soap', 'Herbal Liniment Balm', 'Rice Bran Body Scrub']],
        ['key' => 'paper-and-thread', 'owner' => 'Celine Navarro', 'shop' => 'Paper and Thread Studio', 'products' => ['Handbound Recipe Journal', 'Cotton Canvas Pencil Case', 'Desk Planner Pad', 'Gift Wrap Paper Set']],
    ];

    private const BUYERS = ['Ana Dela Cruz', 'Miguel Bautista', 'Rina Flores', 'Joel Mendoza', 'Sofia Ramos', 'Carlo Lim'];

    public function plan(int $sellerLimit = 6, int $productsPerSeller = 4, bool $withHistory = true): array
    {
        $sellerLimit = min(max($sellerLimit, 1), count(self::SELLERS));
        $productsPerSeller = min(max($productsPerSeller, 1), 4);
        return [
            'buyers' => count(self::BUYERS), 'sellers' => $sellerLimit,
            'products' => $sellerLimit * $productsPerSeller,
            'logistics_users' => 1, 'riders' => 3,
            // The history set is deliberately small and is created only through
            // the central workflow service, never by status writes.
            'workflow_orders' => $withHistory ? 3 : 0,
            'reviews' => $withHistory ? 1 : 0,
        ];
    }

    public function apply(int $sellerLimit = 6, int $productsPerSeller = 4, bool $withHistory = true): array
    {
        return DB::transaction(function () use ($sellerLimit, $productsPerSeller, $withHistory) {
            $plan = $this->plan($sellerLimit, $productsPerSeller, $withHistory);
            $admin = $this->user('admin', 'Bea Alonzo', 'admin');
            $buyers = [];
            foreach (self::BUYERS as $index => $name) $buyers[] = $this->buyer('buyer-'.($index + 1), $name);
            [$provider, $logistics, $mainHub, $destinationHub, $riders, $zone] = $this->logistics($admin);

            $leaves = $this->activeLeaves();
            if ($leaves->isEmpty()) throw ValidationException::withMessages(['categories' => 'At least one active root and active leaf category are required for presentation products.']);
            $sellers = [];
            foreach (array_slice(self::SELLERS, 0, $plan['sellers']) as $index => $definition) {
                $leaf = $leaves[$index % $leaves->count()];
                $sellers[] = $this->seller($definition, $leaf, $admin, $productsPerSeller);
            }
            if ($withHistory && $sellers) $this->history($buyers, $sellers, $provider, $logistics, $riders, $zone, $mainHub, $destinationHub);
            return $plan;
        }, 3);
    }

    private function user(string $key, string $name, string $role): User
    {
        return $this->once('user:'.$key, User::class, function () use ($key, $name, $role) {
            [$first, $last] = array_pad(explode(' ', $name, 2), 2, 'Santos');
            return User::create([
                'name' => $name, 'first_name' => $first, 'last_name' => $last,
                'email' => Str::slug($key).'.presentation@suki.invalid',
                'phone' => '0917'.str_pad((string) (1000000 + crc32($key) % 8999999), 7, '0', STR_PAD_LEFT),
                'role' => $role, 'status' => 'active', 'is_suspended' => false,
                'email_verified_at' => now(), 'phone_verified_at' => now(),
                // This non-loginable random value is never displayed or documented.
                'password' => Str::random(64),
            ]);
        });
    }

    private function buyer(string $key, string $name): User
    {
        $buyer = $this->user($key, $name, 'buyer');
        $this->once('address:'.$key, Address::class, fn () => Address::create([
            'user_id' => $buyer->id, 'label' => 'Home', 'recipient' => $name, 'phone' => $buyer->phone,
            'line1' => '12 Maharlika Street', 'is_default' => true,
        ] + self::LOCATION));
        return $buyer;
    }

    private function logistics(User $admin): array
    {
        $manager = $this->user('logistics-manager', 'Rogelio Cruz', 'logistics');
        $provider = $this->once('provider:laguna-route', LogisticsProvider::class, fn () => LogisticsProvider::create([
            'user_id' => $manager->id, 'name' => 'Laguna Route Cooperative', 'slug' => 'laguna-route-cooperative',
            'status' => 'approved', 'contact_phone' => $manager->phone, 'address' => 'Santa Cruz, Laguna',
            'region' => self::LOCATION['region'], 'province' => self::LOCATION['province'], 'city' => self::LOCATION['city'], 'barangay' => self::LOCATION['barangay'], 'postal_code' => self::LOCATION['postal_code'],
        ]));
        // The fixture's sole covered barangay hub is also this provider's main
        // sorting facility. The workflow records hub routing without inventing
        // a second physical hub at the same provider/barangay combination.
        $main = $this->once('hub:duhat', LogisticsHub::class, fn () => LogisticsHub::create(['logistics_provider_id' => $provider->id, 'name' => 'Duhat Community Logistics Hub', 'is_active' => true] + self::LOCATION));
        $destination = $main;
        if (! $provider->main_sorting_hub_id) $provider->update(['main_sorting_hub_id' => $main->id]);
        $zone = $this->once('zone:duhat', LogisticsSortingZone::class, fn () => LogisticsSortingZone::create(['logistics_provider_id' => $provider->id, 'logistics_hub_id' => $destination->id, 'zone_code' => 'SC-DUHAT', 'name' => 'Duhat Service Zone', 'is_active' => true, 'priority' => 10]));
        $this->once('zone-area:duhat', LogisticsSortingZoneArea::class, fn () => LogisticsSortingZoneArea::create(['logistics_sorting_zone_id' => $zone->id, 'area_key' => app(SortingZoneResolver::class)->areaKey(self::LOCATION['region_code'], self::LOCATION['province_code'], self::LOCATION['city_municipality_code'], self::LOCATION['barangay_code'])] + array_intersect_key(self::LOCATION, array_flip(['region_code','province_code','city_municipality_code','barangay_code']))));
        $riders = [];
        foreach ([['pickup-rider', 'Erwin Castro', $main], ['transfer-rider', 'Nico Valdez', $main], ['delivery-rider', 'Maya Aquino', $destination]] as [$key, $name, $hub]) {
            $user = $this->user($key, $name, 'rider');
            $rider = $this->once('rider:'.$key, Rider::class, fn () => Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $provider->id, 'logistics_hub_id' => $hub->id, 'vehicle_type' => 'Motorcycle', 'plate_no' => 'PRS-'.strtoupper(substr(hash('sha256', $key), 0, 6)), 'is_active' => true, 'availability' => 'ONLINE', 'active_assignment_capacity' => 5, 'approved_by' => $admin->id, 'approved_at' => now()]));
            $this->once('rider-area:'.$key, RiderServiceArea::class, fn () => RiderServiceArea::create(['rider_id' => $rider->id, 'logistics_provider_id' => $provider->id, 'is_active' => true, 'area_key' => RiderServiceArea::key(self::LOCATION['region_code'], self::LOCATION['province_code'], self::LOCATION['city_municipality_code'], self::LOCATION['barangay_code'])] + array_intersect_key(self::LOCATION, array_flip(['region_code','province_code','city_municipality_code','barangay_code']))));
            $riders[] = $rider;
        }
        return [$provider, $manager, $main, $destination, $riders, $zone];
    }

    private function seller(array $definition, Category $leaf, User $admin, int $productLimit): Seller
    {
        $root = $this->rootFor($leaf);
        $owner = $this->user('seller:'.$definition['key'], $definition['owner'], 'seller');
        $seller = $this->once('seller:'.$definition['key'], Seller::class, fn () => Seller::create(['user_id' => $owner->id, 'name' => $definition['shop'], 'slug' => $definition['key'], 'category_id' => $root->id, 'description' => 'Fictional Philippine small-business catalog prepared for local presentation use.', 'status' => 'approved']));
        $address = $this->once('address:seller:'.$definition['key'], Address::class, fn () => Address::create(['user_id' => $owner->id, 'label' => 'Shop pickup', 'recipient' => $definition['shop'], 'phone' => $owner->phone, 'line1' => '18 Mercado Road', 'is_default' => true] + self::LOCATION));
        if (! $seller->pickup_address_id) $seller->update(['pickup_address_id' => $address->id]);
        foreach (array_slice($definition['products'], 0, $productLimit) as $index => $name) $this->product($seller, $leaf, $admin, $definition['key'].':'.($index + 1), $name, $index);
        return $seller;
    }

    private function product(Seller $seller, Category $leaf, User $admin, string $key, string $name, int $index): Product
    {
        return $this->once('product:'.$key, Product::class, function () use ($seller, $leaf, $admin, $key, $name, $index) {
            $product = app(ProductCatalogService::class)->create($seller, [
                'name' => $name, 'description' => $this->description($name), 'category_id' => $leaf->id,
                'status' => 'active', 'product_mode' => 'simple', 'price' => number_format(89 + ($index * 37), 2, '.', ''),
                'stock' => $index === 0 ? 8 : 30 + $index * 7, 'sku' => 'PRS-'.strtoupper(substr(hash('sha256', $key), 0, 10)),
                'option_groups' => [], 'variants' => [], 'shipping_options' => [],
            ]);
            $imageKey = 'lm'.str_pad((string) (($index % 6) + 1), 3, '0', STR_PAD_LEFT);
            $imagePath = DemoProductImages::path($imageKey);
            if (! DemoProductImages::url($imagePath)) {
                throw ValidationException::withMessages(['media' => 'The approved local presentation image is unavailable.']);
            }
            $product->images()->create(['path' => $imagePath, 'position' => 1]);
            return app(ProductModerationService::class)->approve($product->fresh(), $admin);
        });
    }

    /** Generate a small realistic spread exclusively through central workflow operations. */
    private function history(array $buyers, array $sellers, LogisticsProvider $provider, User $logistics, array $riders, LogisticsSortingZone $zone, LogisticsHub $mainHub, LogisticsHub $destinationHub): void
    {
        // This batch intentionally stops at workflow-safe stages when a prior
        // rerun has no remaining inventory. It never edits a pre-existing order.
        foreach (array_slice($sellers, 0, min(3, count($sellers))) as $index => $seller) {
            $recordKey = 'order:'.($index + 1);
            if (PresentationImportRecord::where('batch_key', self::BATCH)->where('record_key', $recordKey)->exists()) continue;
            $product = $seller->products()->with('variants')->where('moderation_status', 'APPROVED')->first();
            $buyer = $buyers[$index % count($buyers)];
            if (! $product || ! $product->variants->first()) continue;
            // Checkout owns totals, stock reduction, order items, shipment and references.
            $variant = $product->variants->first();
            \App\Models\CartItem::create(['user_id' => $buyer->id, 'product_variant_id' => $variant->id, 'product_slug' => $product->slug, 'product_name' => $product->name, 'price' => $variant->price_minor / 100, 'image' => $product->images()->value('path'), 'quantity' => 1, 'selected' => true]);
            $order = app(CheckoutService::class)->checkout($buyer, [
                'recipient_name' => $buyer->name, 'recipient_phone' => $buyer->phone,
                'province' => self::LOCATION['province'], 'municipality' => self::LOCATION['city'], 'barangay' => self::LOCATION['barangay'], 'street_address' => '12 Maharlika Street',
                'region_code' => self::LOCATION['region_code'], 'province_code' => self::LOCATION['province_code'], 'city_municipality_code' => self::LOCATION['city_municipality_code'], 'barangay_code' => self::LOCATION['barangay_code'],
            ], 'cod', [], null, 'PRS-ORDER-'.($index + 1));
            $sellerOrder = $order->sellerOrders()->with(['seller','shipment'])->sole();
            $workflow = app(OrderWorkflowService::class);
            $owner = $seller->owner;
            $workflow->transition($sellerOrder, $owner, OrderStatus::Confirmed);
            $workflow->transition($sellerOrder->fresh(), $owner, OrderStatus::Preparing);
            $workflow->transition($sellerOrder->fresh(), $owner, OrderStatus::ReadyForPickup);
            if ($index >= 1) {
                $workflow->assignPickupRider($sellerOrder->fresh(), $provider, $logistics, $riders[0]);
                $workflow->acceptPickup($sellerOrder->fresh(), $riders[0]);
                if ($index === 1) $workflow->transition($sellerOrder->fresh(), $riders[0]->user, OrderStatus::PickedUp);
            }
            if ($index >= 2) {
                $workflow->transition($sellerOrder->fresh(), $riders[0]->user, OrderStatus::PickedUp);
                $workflow->transition($sellerOrder->fresh(), $logistics, OrderStatus::AtSortingCenter);
                $workflow->transition($sellerOrder->fresh(), $logistics, OrderStatus::Sorted, [
                    'sorting_area' => $zone->zone_code.' — '.$zone->name,
                    'sorting_zone_id' => $zone->id,
                    'destination_hub_id' => $destinationHub->id,
                    'logistics_hub_id' => $mainHub->id,
                    'origin_hub_id' => $mainHub->id,
                    'sorting_event_note' => 'Presentation route resolved through the designated barangay hub.',
                ]);
                $workflow->assignHubTransferRider($sellerOrder->fresh(), $provider, $logistics, $riders[1]);
                $workflow->acceptHubTransfer($sellerOrder->fresh(), $riders[1]);
                $workflow->transition($sellerOrder->fresh(), $riders[1]->user, OrderStatus::InTransitToHub);
                $workflow->transition($sellerOrder->fresh(), $logistics, OrderStatus::AtDestinationHub);
                $workflow->transition($sellerOrder->fresh(), $logistics, OrderStatus::AssignedToRider, ['rider' => $riders[2]]);
                $workflow->acceptDelivery($sellerOrder->fresh(), $riders[2]);
                $workflow->transition($sellerOrder->fresh(), $riders[2]->user, OrderStatus::OutForDelivery);
                $this->recordDeliveryEvidence($sellerOrder->fresh(), $riders[2]);
                $workflow->transition($sellerOrder->fresh(), $riders[2]->user, OrderStatus::Delivered);
                $workflow->transition($sellerOrder->fresh(), $buyer, OrderStatus::Completed);
                $this->review($sellerOrder->fresh(['order', 'items.product']), $buyer);
            }
            $this->record($recordKey, $order);
        }
    }

    private function activeLeaves()
    {
        return Category::query()->where('is_active', true)->whereNotNull('parent_id')->with('parent')->orderBy('id')->get()
            ->filter(fn (Category $category) => $category->parent?->is_active)->values();
    }

    private function rootFor(Category $category): Category
    {
        while ($category->parent_id) $category = Category::findOrFail($category->parent_id);
        return $category;
    }

    private function once(string $key, string $class, callable $create): Model
    {
        $record = PresentationImportRecord::where('batch_key', self::BATCH)->where('record_key', $key)->lockForUpdate()->first();
        if ($record) {
            if ($record->model_type !== $class || ! ($model = $class::find($record->model_id))) throw ValidationException::withMessages(['presentation' => 'Presentation provenance no longer matches '.$key.'. No records were changed.']);
            return $model;
        }
        $model = $create();
        $this->record($key, $model);
        return $model;
    }

    private function record(string $key, Model $model): void
    {
        PresentationImportRecord::create(['batch_key' => self::BATCH, 'record_key' => $key, 'model_type' => $model::class, 'model_id' => $model->getKey()]);
    }

    private function description(string $name): string
    {
        return $name.' is a practical, locally presented item with clear care guidance and everyday use in mind.';
    }

    /** The import writes only a committed local proof asset before the central transition. */
    private function recordDeliveryEvidence(SellerOrder $sellerOrder, Rider $rider): void
    {
        $shipment = $sellerOrder->shipment()->firstOrFail();
        $source = public_path(DemoProductImages::path('lm001'));
        if (! is_file($source)) throw ValidationException::withMessages(['media' => 'The local delivery-proof source is unavailable.']);
        $path = 'presentation/'.$shipment->id.'/delivery-proof.webp';
        Storage::disk('delivery_proofs')->put($path, file_get_contents($source));
        $shipment->update([
            'proof_path' => $path, 'proof_mime' => 'image/webp', 'proof_size' => filesize($source),
            'proof_checksum' => hash_file('sha256', $source), 'proof_delivered_at' => now(),
            'delivered_by' => $rider->user_id, 'received_by' => $sellerOrder->order->recipient_name,
            'delivery_note' => 'Presentation delivery confirmation.', 'cod_collected' => $sellerOrder->order->payment_method === 'cod',
            'cod_collected_minor' => $sellerOrder->order->payment_method === 'cod' ? $shipment->cod_amount_minor : null,
            'cod_collected_at' => $sellerOrder->order->payment_method === 'cod' ? now() : null,
        ]);
        DeliveryEvent::create(['shipment_id' => $shipment->id, 'status' => 'PRESENTATION_DELIVERY_PROOF_RECORDED', 'attempt' => 0, 'user_id' => $rider->user_id, 'note' => 'Local proof recorded before the centralized delivery transition.', 'photo_path' => $path, 'occurred_at' => now()]);
    }

    private function review(SellerOrder $sellerOrder, User $buyer): void
    {
        $item = $sellerOrder->items->first();
        if (! $item || ! app(TransactionReviewEligibility::class)->order($sellerOrder, $buyer->id, true)
            || ! app(TransactionReviewEligibility::class)->productIdentity($item, $sellerOrder)) return;
        $key = 'product:'.$item->id;
        if (TransactionReview::where('eligibility_key', $key)->exists()) return;
        TransactionReview::create([
            'kind' => 'product', 'eligibility_key' => $key, 'buyer_id' => $buyer->id,
            'seller_order_id' => $sellerOrder->id, 'order_item_id' => $item->id,
            'seller_id' => $sellerOrder->seller_id, 'product_id' => $item->product_id,
            'rating' => 5, 'body' => 'Well packed, accurately described, and useful for everyday needs.',
        ]);
    }
}
