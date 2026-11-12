<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Http\Controllers\ShipmentScannerController;
use App\Models\Address;
use App\Models\LogisticsHub;
use App\Models\LogisticsProvider;
use App\Models\LogisticsSortingZone;
use App\Models\Order;
use App\Models\Rider;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentQrService;
use App\Services\SortingZoneResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhaseSixShipmentScannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_qr_payload_validates_only_with_the_configured_signing_key(): void
    {
        $qr = app(ShipmentQrService::class);
        $shipment = new Shipment();
        $shipment->tracking_code = 'SUKI-20261105023229-YI58JAYB';

        config(['app.key' => 'focused-test-signing-key-one']);
        $payload = $qr->payload($shipment);
        $this->assertSame($shipment->tracking_code, $qr->trackingCodeFromPayload($payload));

        config(['app.key' => 'focused-test-signing-key-two']);
        $this->assertNull($qr->trackingCodeFromPayload($payload));
    }

    public function test_sorting_zone_resolution_prefers_exact_barangay_then_city_wide_mapping(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));
        $cityZone = LogisticsSortingZone::create(['logistics_provider_id' => $provider->id, 'logistics_hub_id' => $destinationHub->id, 'zone_code' => 'SC-CITY', 'name' => 'Santa Cruz Citywide', 'priority' => 1, 'is_active' => true]);
        $cityZone->areas()->create(['region_code' => '01', 'province_code' => '0101', 'city_municipality_code' => '010101', 'barangay_code' => null, 'area_key' => '01:0101:010101:-']);
        $resolver = app(SortingZoneResolver::class);

        $this->assertSame($sortingZone->id, $resolver->resolve($provider, $sellerOrder->order)['zone']->id);
        $sortingZone->update(['is_active' => false]);
        $this->assertSame($cityZone->id, $resolver->resolve($provider, $sellerOrder->order)['zone']->id);
    }

    public function test_logistics_can_configure_provider_owned_zones_and_code_mappings(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));
        $this->fakePsgc();
        $this->actingAs($logisticsUser)->get(route('logistics.sorting-zones.index'))
            ->assertOk()->assertSee('Sorting Zones')
            ->assertSee('href="'.route('logistics.sorting-zones.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('data-address-region', false)
            ->assertSee('Apply to the entire city/municipality');
        $this->actingAs($logisticsUser)->post(route('logistics.sorting-zones.store'), ['zone_code' => 'sc-priority', 'name' => 'Priority Area', 'priority' => 5])->assertSessionHasNoErrors();
        $zone = $provider->sortingZones()->where('zone_code', 'SC-PRIORITY')->firstOrFail();
        $zone->update(['logistics_hub_id' => $destinationHub->id]);
        $this->actingAs($logisticsUser)->post(route('logistics.sorting-zones.areas.store', $zone), ['region_code' => '01', 'province_code' => '0101', 'city_municipality_code' => '010101', 'barangay_code' => '010101001'])->assertSessionHasNoErrors();
        $this->assertSame($zone->id, app(SortingZoneResolver::class)->resolve($provider, $sellerOrder->order)['zone']->id);
        $this->assertSame('01:0101:010101:010101001', $zone->areas()->value('area_key'));
        $this->actingAs($logisticsUser)->get(route('logistics.sorting-zones.index'))
            ->assertOk()->assertSee('Region · Province · City · Barangay')
            ->assertSee('Barangay-specific mapping');
    }

    public function test_zone_mapping_resolves_and_persists_canonical_duhat_psgc_codes(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));
        $this->fakeDuhatPsgc();
        $zone = LogisticsSortingZone::create(['logistics_provider_id' => $provider->id, 'logistics_hub_id' => $destinationHub->id, 'zone_code' => 'SC-DUHAT', 'name' => 'Duhat Sorting Zone', 'is_active' => true]);

        $this->actingAs($logisticsUser)->post(route('logistics.sorting-zones.areas.store', $zone), [
            'region_code' => '0400000000', 'province_code' => '0403400000',
            'city_municipality_code' => '0403426000', 'barangay_code' => '0403426005',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('logistics_sorting_zone_areas', [
            'logistics_sorting_zone_id' => $zone->id,
            'region_code' => '0400000000', 'province_code' => '0403400000',
            'city_municipality_code' => '0403426000', 'barangay_code' => '0403426005',
        ]);
        $sellerOrder->order->update([
            'region' => 'Region IV-A / CALABARZON', 'region_code' => '0400000000',
            'province' => 'Laguna', 'province_code' => '0403400000',
            'municipality' => 'Santa Cruz', 'city_municipality_code' => '0403426000',
            'barangay' => 'Duhat', 'barangay_code' => '0403426005',
        ]);
        $sortingZone->update(['is_active' => false]);
        $destinationHub->update(['name' => 'SUKI Duhat Pickup Hub - Corrected', 'region_code' => '0400000000', 'province_code' => '0403400000', 'city_municipality_code' => '0403426000', 'barangay_code' => '0403426005']);
        $zone->update(['zone_code' => 'SC-DUHAT', 'name' => 'Duhat Sorting Zone']);
        $this->assertSame($zone->id, app(SortingZoneResolver::class)->resolve($provider, $sellerOrder->order)['zone']->id);
        $this->actingAs($logisticsUser)->get(route('logistics.sorting-zones.index'))
            ->assertOk()->assertSee('Region IV-A / CALABARZON · Laguna · Santa Cruz · Duhat')
            ->assertSee('Barangay-specific mapping');

        $this->actingAs($logisticsUser)->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $shipment->tracking_code, 'mode' => 'sort'])->assertRedirect(route('logistics.shipment-scanner'));
        $this->get(route('logistics.shipment-scanner'))->assertOk()
            ->assertSee('Duhat, Santa Cruz, Laguna')
            ->assertSee('SUKI Duhat Pickup Hub - Corrected')
            ->assertSee('SC-DUHAT — Duhat Sorting Zone')
            ->assertSee('Automatically determined from barangay destination codes.');
    }

    public function test_zone_mapping_requires_valid_location_parent_relationships(): void
    {
        extract($this->context());
        $this->fakeDuhatPsgc();
        $zone = $sortingZone;
        $endpoint = route('logistics.sorting-zones.areas.store', $zone);

        $this->actingAs($logisticsUser)->from(route('logistics.sorting-zones.index'))->post($endpoint, [
            'region_code' => '9999999999', 'province_code' => '0403400000',
            'city_municipality_code' => '0403426000', 'barangay_code' => '0403426005',
        ])->assertSessionHasErrors('region_code');

        $this->actingAs($logisticsUser)->from(route('logistics.sorting-zones.index'))->post($endpoint, [
            'region_code' => '0400000000', 'province_code' => '0101',
            'city_municipality_code' => '0403426000', 'barangay_code' => '0403426005',
        ])->assertSessionHasErrors('province_code');

        $this->post($endpoint, [
            'region_code' => '0400000000', 'province_code' => '0403400000',
            'city_municipality_code' => '010101', 'barangay_code' => '0403426005',
        ])->assertSessionHasErrors('city_municipality_code');

        $this->post($endpoint, [
            'region_code' => '0400000000', 'province_code' => '0403400000',
            'city_municipality_code' => '0403426000', 'barangay_code' => '0403426999',
        ])->assertSessionHasErrors('barangay_code');
        $this->assertDatabaseCount('logistics_sorting_zone_areas', 1); // Only the pre-existing fixture mapping.
    }

    public function test_city_wide_mapping_is_explicit_and_provider_isolation_is_preserved(): void
    {
        extract($this->context());
        $this->fakeDuhatPsgc();
        $zone = LogisticsSortingZone::create(['logistics_provider_id' => $provider->id, 'zone_code' => 'SC-CITY', 'name' => 'Santa Cruz Citywide', 'is_active' => true]);
        $this->actingAs($logisticsUser)->post(route('logistics.sorting-zones.areas.store', $zone), [
            'region_code' => '0400000000', 'province_code' => '0403400000',
            'city_municipality_code' => '0403426000', 'city_wide' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('logistics_sorting_zone_areas', ['logistics_sorting_zone_id' => $zone->id, 'barangay_code' => null]);
        $this->actingAs($logisticsUser)->get(route('logistics.sorting-zones.index'))
            ->assertOk()->assertSee('Entire city/municipality');

        [, $otherProvider] = $this->provider('zone-foreign');
        $foreignZone = LogisticsSortingZone::create(['logistics_provider_id' => $otherProvider->id, 'zone_code' => 'FOREIGN', 'name' => 'Foreign zone', 'is_active' => true]);
        $this->post(route('logistics.sorting-zones.areas.store', $foreignZone), [
            'region_code' => '0400000000', 'province_code' => '0403400000',
            'city_municipality_code' => '0403426000', 'city_wide' => '1',
        ])->assertNotFound();
    }

    public function test_inactive_foreign_and_ambiguous_zones_are_not_selected(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));
        $resolver = app(SortingZoneResolver::class);
        $sortingZone->update(['is_active' => false]);
        [$otherUser, $otherProvider] = $this->provider('zone-other');
        $foreign = LogisticsSortingZone::create(['logistics_provider_id' => $otherProvider->id, 'zone_code' => 'FOREIGN', 'name' => 'Foreign zone', 'is_active' => true]);
        $foreign->areas()->create(['region_code' => '01', 'province_code' => '0101', 'city_municipality_code' => '010101', 'barangay_code' => '010101001', 'area_key' => '01:0101:010101:010101001']);
        $this->assertNull($resolver->resolve($provider, $sellerOrder->order)['zone']);

        $one = LogisticsSortingZone::create(['logistics_provider_id' => $provider->id, 'logistics_hub_id' => $destinationHub->id, 'zone_code' => 'SC-A', 'name' => 'Zone A', 'priority' => 10, 'is_active' => true]);
        $two = LogisticsSortingZone::create(['logistics_provider_id' => $provider->id, 'logistics_hub_id' => $destinationHub->id, 'zone_code' => 'SC-B', 'name' => 'Zone B', 'priority' => 10, 'is_active' => true]);
        foreach ([$one, $two] as $zone) $zone->areas()->create(['region_code' => '01', 'province_code' => '0101', 'city_municipality_code' => '010101', 'barangay_code' => '010101001', 'area_key' => '01:0101:010101:010101001']);
        $result = $resolver->resolve($provider, $sellerOrder->order);
        $this->assertTrue($result['ambiguous']);
        $this->assertNull($result['zone']);
    }

    public function test_valid_override_is_provider_scoped_audited_and_requires_a_reason(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));
        $override = LogisticsSortingZone::create(['logistics_provider_id' => $provider->id, 'logistics_hub_id' => $destinationHub->id, 'zone_code' => 'SC-02', 'name' => 'Santa Cruz North', 'is_active' => true]);
        $override->areas()->create(['region_code' => '01', 'province_code' => '0101', 'city_municipality_code' => '010101', 'barangay_code' => '010101002', 'area_key' => '01:0101:010101:010101002']);
        $this->actingAs($logisticsUser)->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $shipment->tracking_code, 'mode' => 'sort'])->assertRedirect(route('logistics.shipment-scanner'));
        $this->actingAs($logisticsUser)->from(route('logistics.shipment-scanner'))->post(route('logistics.shipment-scanner.action', $shipment), ['action' => 'mark_sorted', 'sorting_zone_id' => $override->id])->assertSessionHasErrors('override_reason');
        $this->actingAs($logisticsUser)->post(route('logistics.shipment-scanner.action', $shipment), ['action' => 'mark_sorted', 'sorting_zone_id' => $override->id, 'override_reason' => 'hub_capacity'])->assertRedirect(route('logistics.shipment-scanner'));
        $this->assertSame($override->id, $shipment->fresh()->sorting_zone_id);
        $this->assertDatabaseHas('delivery_events', ['shipment_id' => $shipment->id, 'status' => 'SORTED', 'user_id' => $logisticsUser->id]);
        $this->assertStringContainsString('hub_capacity', (string) $shipment->events()->where('status', 'SORTED')->value('note'));
        $this->assertNull($shipment->fresh()->rider_id);
    }

    public function test_foreign_zone_is_rejected_and_unmapped_destination_needs_explicit_override(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));
        $sortingZone->update(['is_active' => false]);
        [, $otherProvider] = $this->provider('foreign-zone');
        $foreignZone = LogisticsSortingZone::create(['logistics_provider_id' => $otherProvider->id, 'zone_code' => 'FOREIGN-01', 'name' => 'Foreign zone', 'is_active' => true]);

        $this->actingAs($logisticsUser)->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $shipment->tracking_code, 'mode' => 'sort'])->assertRedirect(route('logistics.shipment-scanner'));
        $this->get(route('logistics.shipment-scanner'))->assertOk()->assertSee('No configured sorting zone matches this destination');
        $this->actingAs($logisticsUser)->from(route('logistics.shipment-scanner'))->post(route('logistics.shipment-scanner.action', $shipment), ['action' => 'mark_sorted', 'sorting_zone_id' => $foreignZone->id, 'override_reason' => 'operational_exception'])->assertSessionHasErrors('sorting_zone_id');
        $this->assertSame(OrderStatus::AtSortingCenter, $sellerOrder->fresh()->workflow_status);
        $this->assertNull($shipment->fresh()->sorting_zone_id);
    }

    public function test_authorized_logistics_can_resolve_its_signed_shipment_qr(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));
        $qrPayload = $this->payload($shipment);
        $this->assertGreaterThan(100, strlen($qrPayload));

        $response = $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.resolve'), ['payload' => $qrPayload]);
        $response->assertRedirect(route('logistics.shipment-scanner'));
        $this->assertSame(route('logistics.shipment-scanner'), $response->headers->get('Location'));
        $this->assertNotSame(route('logistics.shipment-scanner.resolve'), $response->headers->get('Location'));

        $this->get(route('logistics.shipment-scanner'))
            ->assertOk()
            ->assertSee($shipment->tracking_code)
            ->assertSee('Shipment verified')
            ->assertSee('Shipment Scanner')
            ->assertSee('No scan action is available')
            ->assertSee('id="start-camera"', false)
            ->assertSee('id="stop-camera"', false)
            ->assertSee('id="camera-status"', false)
            ->assertSee('method="POST"', false)
            ->assertSee('action="'.route('logistics.shipment-scanner.resolve').'"', false)
            ->assertSee('id="qr-payload" type="hidden" name="payload" value=""', false)
            ->assertSee('id="scan-payload" name="tracking_code"', false)
            ->assertDontSee('value="'.$qrPayload.'"', false)
            ->assertSee('aria-live="polite"', false);

        $this->get(route('logistics.shipment-scanner'))->assertOk()->assertSee($shipment->tracking_code);
        $this->get(route('logistics.shipment-scanner.resolve'))->assertStatus(405);
    }

    public function test_logistics_cannot_resolve_another_providers_shipment(): void
    {
        extract($this->context());
        [, $otherProvider] = $this->provider('other');
        $shipment->update(['logistics_provider_id' => $otherProvider->id]);

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.resolve'), ['payload' => $this->payload($shipment->fresh())])
            ->assertForbidden();

        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.order.status', $shipment->sellerOrder), [
                'status' => 'sorted',
                'sorting_area' => 'Wrong Provider',
            ])
            ->assertForbidden();
        $this->assertSame(OrderStatus::AssignedToRider, $shipment->sellerOrder->fresh()->workflow_status);
    }

    public function test_assigned_rider_can_resolve_only_their_shipment(): void
    {
        extract($this->context());
        [, , $otherRider] = $this->rider($provider, 'other');
        $qrPayload = $this->payload($shipment);
        $this->assertGreaterThan(100, strlen($qrPayload));

        $this->actingAs($pickupUser)
            ->get(route('rider.shipment-scanner', ['tracking_code' => $shipment->tracking_code]))
            ->assertOk()
            ->assertSee('id="scan-payload" name="tracking_code" value="'.$shipment->tracking_code.'"', false)
            ->assertDontSee('Shipment verified');
        $this->assertNull(session(ShipmentScannerController::riderVerificationKey($pickupRider->id)));

        $response = $this->actingAs($pickupUser)
            ->post(route('rider.shipment-scanner.resolve'), ['payload' => $qrPayload]);
        $response->assertRedirect(route('rider.shipment-scanner'));
        $this->assertSame(route('rider.shipment-scanner'), $response->headers->get('Location'));
        $this->assertNotSame(route('rider.shipment-scanner.resolve'), $response->headers->get('Location'));

        $this->get(route('rider.shipment-scanner'))
            ->assertOk()
            ->assertSee($shipment->tracking_code)
            ->assertSee('Shipment verified')
            ->assertSee('method="POST"', false)
            ->assertSee('action="'.route('rider.shipment-scanner.resolve').'"', false);

        $this->actingAs($otherRider->user)
            ->post(route('rider.shipment-scanner.resolve'), ['payload' => $this->payload($shipment)])
            ->assertForbidden();
    }

    public function test_unassigned_rider_is_rejected_from_resolving_a_shipment(): void
    {
        extract($this->context());
        $shipment->update(['pickup_rider_id' => null, 'rider_id' => null]);

        $this->actingAs($pickupUser)
            ->post(route('rider.shipment-scanner.resolve'), ['payload' => $this->payload($shipment->fresh())])
            ->assertForbidden();
    }

    public function test_invalid_or_tampered_qr_payload_is_rejected_safely(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));
        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $shipment->tracking_code, 'mode' => 'sort'])
            ->assertRedirect(route('logistics.shipment-scanner'));

        $verifiedKey = ShipmentScannerController::verifiedResultKey('logistics', $provider->id);
        $sortingKey = ShipmentScannerController::sortingVerificationKey($provider->id);
        $this->assertNotNull(session($verifiedKey));
        $this->assertSame($shipment->id, session($sortingKey));

        $payload = $this->payload($shipment);
        $tampered = substr($payload, 0, -1).($payload[-1] === 'a' ? 'b' : 'a');

        $this->actingAs($logisticsUser)
            ->from(route('logistics.shipment-scanner'))
            ->post(route('logistics.shipment-scanner.resolve'), ['payload' => $tampered])
            ->assertRedirect(route('logistics.shipment-scanner'))
            ->assertSessionHasErrors('payload');

        $this->assertNull(session($verifiedKey));
        $this->assertNull(session($sortingKey));
    }

    public function test_arbitrary_get_tracking_code_does_not_establish_a_verified_shipment(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));

        $this->actingAs($logisticsUser)
            ->get(route('logistics.shipment-scanner', ['tracking_code' => $shipment->tracking_code]))
            ->assertOk()
            ->assertDontSee('Shipment verified')
            ->assertDontSee('Current status:')
            ->assertSee('value="'.$shipment->tracking_code.'"', false);

        $this->assertNull(session(ShipmentScannerController::verifiedResultKey('logistics', $provider->id)));
        $this->assertNull(session(ShipmentScannerController::sortingVerificationKey($provider->id)));
    }

    public function test_manual_tracking_code_stays_strict_and_short_manual_lookup_still_works(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $shipment->tracking_code])
            ->assertRedirect(route('logistics.shipment-scanner'));
        $this->get(route('logistics.shipment-scanner'))
            ->assertOk()
            ->assertSee('Shipment verified')
            ->assertSee($shipment->tracking_code)
            ->assertSee('No scan action is available');

        $tooLong = str_repeat('A', 101);
        $this->from(route('logistics.shipment-scanner'))
            ->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $tooLong])
            ->assertRedirect(route('logistics.shipment-scanner'))
            ->assertSessionHasErrors('tracking_code');
        $this->assertNull(session(ShipmentScannerController::verifiedResultKey('logistics', $provider->id)));
        $this->assertNull(session(ShipmentScannerController::sortingVerificationKey($provider->id)));
    }

    public function test_resolving_another_logistics_shipment_replaces_the_previous_verified_result(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));
        $otherOrderRecord = $sellerOrder->order->replicate();
        $otherOrderRecord->order_number = 'SCAN-REPLACE-'.uniqid();
        $otherOrderRecord->reference = 'SCAN-REPLACE-'.uniqid();
        $otherOrderRecord->save();
        $otherOrder = $sellerOrder->replicate();
        $otherOrder->order_id = $otherOrderRecord->id;
        $otherOrder->save();
        $otherShipment = $shipment->replicate();
        $otherShipment->seller_order_id = $otherOrder->id;
        $otherShipment->tracking_code = 'SUKI-REPLACE-'.strtoupper(uniqid());
        $otherShipment->save();

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $shipment->tracking_code])
            ->assertRedirect(route('logistics.shipment-scanner'));
        $this->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $otherShipment->tracking_code])
            ->assertRedirect(route('logistics.shipment-scanner'));

        $this->get(route('logistics.shipment-scanner'))
            ->assertOk()
            ->assertSee($otherShipment->tracking_code)
            ->assertDontSee($shipment->tracking_code);
        $this->assertSame($otherShipment->id, session(ShipmentScannerController::sortingVerificationKey($provider->id)));

        $this->post(route('logistics.shipment-scanner.action', $shipment), ['action' => 'mark_sorted', 'sorting_zone_id' => $sortingZone->id])
            ->assertSessionHasErrors('action');
    }

    public function test_pickup_scans_use_the_existing_acceptance_and_transition_workflow(): void
    {
        extract($this->context(OrderStatus::ReadyForPickup));

        $this->actingAs($pickupUser)
            ->post(route('rider.shipment-scanner.resolve'), ['tracking_code' => $shipment->tracking_code, 'mode' => 'pickup'])
            ->assertRedirect(route('rider.shipment-scanner'));
        $this->actingAs($pickupUser)
            ->post(route('rider.shipment-scanner.action', $shipment), ['action' => 'accept_pickup'])
            ->assertRedirect(route('rider.shipment-scanner'));
        $this->assertNotNull($shipment->fresh()->pickup_accepted_at);
        $this->assertSame(OrderStatus::ReadyForPickup, $sellerOrder->fresh()->workflow_status);

        $this->actingAs($pickupUser)
            ->post(route('rider.shipment-scanner.resolve'), ['tracking_code' => $shipment->tracking_code, 'mode' => 'pickup'])
            ->assertRedirect(route('rider.shipment-scanner'));
        $this->assertSame($shipment->id, session(ShipmentScannerController::riderVerificationKey($pickupRider->id))['shipment_id']);

        $this->actingAs($pickupUser)
            ->post(route('rider.shipment-scanner.action', $shipment), ['action' => 'confirm_pickup'])
            ->assertRedirect(route('rider.shipment-scanner'));
        $this->assertSame(OrderStatus::PickedUp, $sellerOrder->fresh()->workflow_status);
        $this->assertNull(session(ShipmentScannerController::riderVerificationKey($pickupRider->id)));
    }

    public function test_rider_lifecycle_scanner_actions_reject_forged_posts_without_exact_resolve(): void
    {
        $pickup = $this->context(OrderStatus::ReadyForPickup);
        $pickup['shipment']->update(['pickup_accepted_at' => now()]);
        $arrival = $this->context(OrderStatus::PickedUp);
        $delivery = $this->context(OrderStatus::AssignedToRider);
        $delivery['shipment']->update(['delivery_accepted_at' => now()]);
        $delivery['shipment']->assignments()->where('assignment_type', 'DELIVERY')->update(['status' => 'ACCEPTED', 'responded_at' => now()]);
        $delivery['shipment']->assignments()->where('assignment_type', 'DELIVERY')->update(['status' => 'ACCEPTED', 'responded_at' => now()]);

        foreach ([
            [$pickup, 'confirm_pickup', $pickup['pickupUser'], OrderStatus::ReadyForPickup],
            [$arrival, 'arrive_sorting', $arrival['pickupUser'], OrderStatus::PickedUp],
            [$delivery, 'begin_delivery', $delivery['deliveryUser'], OrderStatus::AssignedToRider],
        ] as [$context, $action, $actor, $expectedStatus]) {
            $this->actingAs($actor)
                ->from(route('rider.shipment-scanner'))
                ->post(route('rider.shipment-scanner.action', $context['shipment']), ['action' => $action])
                ->assertRedirect(route('rider.shipment-scanner'))
                ->assertSessionHasErrors('action');

            $this->assertSame($expectedStatus, $context['sellerOrder']->fresh()->workflow_status);
            $this->assertSame($expectedStatus, $context['shipment']->fresh()->workflow_status);
        }
    }

    public function test_resolving_one_rider_shipment_cannot_authorize_another_assigned_shipment(): void
    {
        extract($this->context(OrderStatus::ReadyForPickup));
        $shipment->update(['pickup_accepted_at' => now()]);

        $otherOrderRecord = $sellerOrder->order->replicate();
        $otherOrderRecord->order_number = 'RIDER-SCAN-OTHER-'.uniqid();
        $otherOrderRecord->reference = 'RIDER-SCAN-OTHER-'.uniqid();
        $otherOrderRecord->save();
        $otherSellerOrder = $sellerOrder->replicate();
        $otherSellerOrder->order_id = $otherOrderRecord->id;
        $otherSellerOrder->save();
        $otherShipment = $shipment->replicate();
        $otherShipment->seller_order_id = $otherSellerOrder->id;
        $otherShipment->tracking_code = 'SUKI-RIDER-OTHER-'.strtoupper(uniqid());
        $otherShipment->save();

        $this->actingAs($pickupUser)
            ->post(route('rider.shipment-scanner.resolve'), ['tracking_code' => $shipment->tracking_code])
            ->assertRedirect(route('rider.shipment-scanner'));

        $this->from(route('rider.shipment-scanner'))
            ->post(route('rider.shipment-scanner.action', $otherShipment), ['action' => 'confirm_pickup'])
            ->assertRedirect(route('rider.shipment-scanner'))
            ->assertSessionHasErrors('action');

        $this->assertSame(OrderStatus::ReadyForPickup, $sellerOrder->fresh()->workflow_status);
        $this->assertSame(OrderStatus::ReadyForPickup, $otherSellerOrder->fresh()->workflow_status);
        $this->assertSame($shipment->id, session(ShipmentScannerController::riderVerificationKey($pickupRider->id))['shipment_id']);
    }

    public function test_rider_resolve_then_scan_advances_arrival_and_delivery_transitions(): void
    {
        $arrival = $this->context(OrderStatus::PickedUp);
        $this->actingAs($arrival['pickupUser'])
            ->post(route('rider.shipment-scanner.resolve'), ['tracking_code' => $arrival['shipment']->tracking_code, 'mode' => 'sorting_center_arrival'])
            ->assertRedirect(route('rider.shipment-scanner'));
        $this->post(route('rider.shipment-scanner.action', $arrival['shipment']), ['action' => 'arrive_sorting'])
            ->assertRedirect(route('rider.shipment-scanner'));
        $this->assertSame(OrderStatus::AtSortingCenter, $arrival['sellerOrder']->fresh()->workflow_status);
        $this->assertNull(session(ShipmentScannerController::riderVerificationKey($arrival['pickupRider']->id)));

        $delivery = $this->context(OrderStatus::AssignedToRider);
        $delivery['shipment']->update(['delivery_accepted_at' => now()]);
        $delivery['shipment']->assignments()->where('assignment_type', 'DELIVERY')->update(['status' => 'ACCEPTED', 'responded_at' => now()]);
        $this->actingAs($delivery['deliveryUser'])
            ->post(route('rider.shipment-scanner.resolve'), ['tracking_code' => $delivery['shipment']->tracking_code, 'mode' => 'last_mile_dispatch'])
            ->assertRedirect(route('rider.shipment-scanner'));
        $this->post(route('rider.shipment-scanner.action', $delivery['shipment']), ['action' => 'begin_delivery'])
            ->assertRedirect(route('rider.shipment-scanner'));
        $this->assertSame(OrderStatus::OutForDelivery, $delivery['sellerOrder']->fresh()->workflow_status);
        $this->assertNull(session(ShipmentScannerController::riderVerificationKey($delivery['deliveryRider']->id)));
    }

    public function test_database_rider_delivery_cards_send_lifecycle_actions_to_the_scanner(): void
    {
        $pickup = $this->context(OrderStatus::ReadyForPickup);
        $pickup['shipment']->update(['pickup_accepted_at' => now()]);
        $this->actingAs($pickup['pickupUser'])->get(route('rider.deliveries'))
            ->assertOk()
            ->assertSee('Scan to Confirm Collection')
            ->assertSee('&amp;mode=pickup', false)
            ->assertDontSee('name="status" value="picked_up"', false);

        $arrival = $this->context(OrderStatus::PickedUp);
        $this->actingAs($arrival['pickupUser'])->get(route('rider.deliveries'))
            ->assertOk()
            ->assertSee('Scan Arrival at Sorting Center')
            ->assertSee('&amp;mode=sorting_center_arrival', false)
            ->assertDontSee('name="status" value="at_sorting_center"', false);

        $delivery = $this->context(OrderStatus::AssignedToRider);
        $delivery['shipment']->update(['delivery_accepted_at' => now()]);
        $this->actingAs($delivery['deliveryUser'])->get(route('rider.deliveries'))
            ->assertOk()
            ->assertSee('Scan to Start Delivery')
            ->assertSee('&amp;mode=last_mile_dispatch', false)
            ->assertDontSee('name="status" value="out_for_delivery"', false);
    }

    public function test_generic_rider_status_endpoint_rejects_database_lifecycle_bypasses(): void
    {
        $cases = [
            [$this->context(OrderStatus::ReadyForPickup), 'PICKED_UP', 'pickupUser', OrderStatus::ReadyForPickup],
            [$this->context(OrderStatus::PickedUp), 'AT_SORTING_CENTER', 'pickupUser', OrderStatus::PickedUp],
            [$this->context(OrderStatus::AssignedToRider), 'OUT_FOR_DELIVERY', 'deliveryUser', OrderStatus::AssignedToRider],
        ];

        foreach ($cases as [$context, $status, $actorKey, $expectedStatus]) {
            if ($status === 'PICKED_UP') {
                $context['shipment']->update(['pickup_accepted_at' => now()]);
            } elseif ($status === 'OUT_FOR_DELIVERY') {
                $context['shipment']->update(['delivery_accepted_at' => now()]);
            }

            $this->actingAs($context[$actorKey])
                ->from(route('rider.deliveries'))
                ->post(route('rider.database.order.status', $context['sellerOrder']), ['status' => $status])
                ->assertRedirect(route('rider.deliveries'))
                ->assertSessionHasErrors('status');

            $this->assertSame($expectedStatus, $context['sellerOrder']->fresh()->workflow_status);
            $this->assertSame($expectedStatus, $context['shipment']->fresh()->workflow_status);
        }
    }

    public function test_invalid_pickup_status_scan_is_rejected(): void
    {
        extract($this->context(OrderStatus::PickedUp));

        $this->actingAs($pickupUser)
            ->from(route('rider.shipment-scanner'))
            ->post(route('rider.shipment-scanner.action', $shipment), ['action' => 'accept_pickup'])
            ->assertRedirect(route('rider.shipment-scanner'))
            ->assertSessionHasErrors('action');
        $this->assertSame(OrderStatus::PickedUp, $sellerOrder->fresh()->workflow_status);
    }

    public function test_sorting_scans_receive_then_transition_the_existing_workflow_to_sorted(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));
        $hub = $destinationHub;
        $hub->update(['name' => 'Santa Cruz Hub']);
        $sortingZone->update(['logistics_hub_id' => $hub->id]);

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.action', $shipment), ['action' => 'mark_sorted', 'sorting_zone_id' => $sortingZone->id])
            ->assertSessionHasErrors('action');
        $this->assertSame(OrderStatus::AtSortingCenter, $sellerOrder->fresh()->workflow_status);

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $shipment->tracking_code, 'mode' => 'sort'])
            ->assertRedirect(route('logistics.shipment-scanner'));

        $this->get(route('logistics.shipment-scanner'))
            ->assertOk()
            ->assertSee('Sort to Santa Cruz Hub')
            ->assertSee('SCAN-')
            ->assertSee('Scanner Store')
            ->assertSee('Barangay, City, Province')
            ->assertSee('Automatically determined from barangay destination codes.')
            ->assertSee('Change Zone')
            ->assertSee('Pickup rider');

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.action', $shipment), ['action' => 'mark_sorted', 'sorting_zone_id' => $sortingZone->id])
            ->assertRedirect(route('logistics.shipment-scanner'));
        $this->assertSame(OrderStatus::Sorted, $sellerOrder->fresh()->workflow_status);
        $this->assertSame(OrderStatus::Sorted, $shipment->fresh()->workflow_status);
        $this->assertNotNull($sellerOrder->fresh()->sorted_at);
        $this->assertSame($provider->id, $shipment->fresh()->logistics_provider_id);
        $this->assertNull($shipment->fresh()->rider_id);
        $this->assertSame($sortingZone->id, $shipment->fresh()->sorting_zone_id);
        $this->assertSame($hub->id, $shipment->fresh()->logistics_hub_id);
        $this->assertSame($hub->id, $shipment->fresh()->destination_hub_id);
        $this->assertDatabaseHas('shipment_assignments', ['shipment_id' => $shipment->id, 'assignment_type' => 'HUB_TRANSFER', 'destination_hub_id' => $hub->id, 'status' => 'ASSIGNED']);
        $this->assertSame('SC-01 — Santa Cruz Central', $shipment->fresh()->sorting_area);
    }

    public function test_wrong_resolved_shipment_cannot_be_used_to_sort_a_different_parcel(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));
        $otherOrderRecord = $sellerOrder->order->replicate();
        $otherOrderRecord->order_number = 'SCAN-OTHER-'.uniqid();
        $otherOrderRecord->reference = 'SCAN-OTHER-'.uniqid();
        $otherOrderRecord->save();
        $otherOrder = $sellerOrder->replicate();
        $otherOrder->order_id = $otherOrderRecord->id;
        $otherOrder->save();
        $otherShipment = $shipment->replicate();
        $otherShipment->seller_order_id = $otherOrder->id;
        $otherShipment->tracking_code = 'SUKI-OTHER-'.strtoupper(uniqid());
        $otherShipment->save();

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $otherShipment->tracking_code])
            ->assertRedirect(route('logistics.shipment-scanner'));

        $this->get(route('logistics.shipment-scanner'))
            ->assertOk()
            ->assertSee($otherShipment->tracking_code);

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.action', $shipment), ['action' => 'mark_sorted', 'sorting_zone_id' => $sortingZone->id])
            ->assertSessionHasErrors('action');

        $this->assertSame(OrderStatus::AtSortingCenter, $sellerOrder->fresh()->workflow_status);
        $this->assertSame(OrderStatus::AtSortingCenter, $otherOrder->fresh()->workflow_status);
    }

    public function test_direct_forged_sort_post_is_rejected_without_scanner_verification(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));

        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.order.status', $sellerOrder), [
                'status' => 'sorted',
                'sorting_area' => 'Forged Area',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::AtSortingCenter, $sellerOrder->fresh()->workflow_status);
        $this->assertNull($sellerOrder->fresh()->sorted_at);
        $this->assertSame(OrderStatus::AtSortingCenter, $shipment->fresh()->workflow_status);
        $this->assertNull($shipment->fresh()->rider_id);
    }

    public function test_receive_at_sorting_center_is_rejected_after_pickup_arrival_state(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));

        $this->actingAs($logisticsUser)->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $shipment->tracking_code, 'mode' => 'sort'])->assertRedirect(route('logistics.shipment-scanner'));
        $this->actingAs($logisticsUser)->post(route('logistics.shipment-scanner.action', $shipment), ['action' => 'receive_sorting'])->assertSessionHasErrors('action');
        $this->assertSame(OrderStatus::AtSortingCenter, $sellerOrder->fresh()->workflow_status);
        $this->assertSame(0, $shipment->events()->where('status', 'RECEIVED_AT_SORTING_CENTER')->count());
    }

    public function test_logistics_can_receive_only_a_picked_up_shipment_after_exact_scan(): void
    {
        $context = $this->context(OrderStatus::PickedUp);
        $this->actingAs($context['logisticsUser'])->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $context['shipment']->tracking_code, 'mode' => 'sorting_center_arrival'])->assertRedirect(route('logistics.shipment-scanner'));
        $this->post(route('logistics.shipment-scanner.action', $context['shipment']), ['action' => 'receive_sorting'])->assertRedirect(route('logistics.shipment-scanner'));
        $this->assertSame(OrderStatus::AtSortingCenter, $context['sellerOrder']->fresh()->workflow_status);
        $this->assertSame(OrderStatus::AtSortingCenter, $context['shipment']->fresh()->workflow_status);
    }

    public function test_sorted_shipment_requires_scanned_hub_dispatch_then_destination_hub_receipt(): void
    {
        $context = $this->context(OrderStatus::AtSortingCenter);
        $this->actingAs($context['logisticsUser'])->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $context['shipment']->tracking_code, 'mode' => 'sort'])->assertRedirect(route('logistics.shipment-scanner'));
        $this->post(route('logistics.shipment-scanner.action', $context['shipment']), ['action' => 'mark_sorted', 'sorting_zone_id' => $context['sortingZone']->id])->assertRedirect(route('logistics.shipment-scanner'));

        $this->actingAs($context['logisticsUser'])->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $context['shipment']->tracking_code, 'mode' => 'hub_transfer_dispatch'])->assertRedirect(route('logistics.shipment-scanner'));
        $context['deliveryRider']->update(['logistics_hub_id' => $context['provider']->main_sorting_hub_id]);
        $this->post(route('logistics.database.hub-transfer.assign', $context['sellerOrder']), ['rider_id' => $context['deliveryRider']->id])->assertSessionHasNoErrors();
        $this->actingAs($context['deliveryUser'])->post(route('rider.shipment-scanner.resolve'), ['tracking_code' => $context['shipment']->tracking_code, 'mode' => 'hub_transfer_dispatch'])->assertRedirect(route('rider.shipment-scanner'));
        $this->post(route('rider.shipment-scanner.action', $context['shipment']), ['action' => 'accept_hub_transfer'])->assertRedirect(route('rider.shipment-scanner'));
        $this->post(route('rider.shipment-scanner.resolve'), ['tracking_code' => $context['shipment']->tracking_code, 'mode' => 'hub_transfer_dispatch'])->assertRedirect(route('rider.shipment-scanner'));
        $this->post(route('rider.shipment-scanner.action', $context['shipment']), ['action' => 'dispatch_hub'])->assertRedirect(route('rider.shipment-scanner'));
        $this->assertSame(OrderStatus::InTransitToHub, $context['sellerOrder']->fresh()->workflow_status);

        $this->actingAs($context['logisticsUser'])->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $context['shipment']->tracking_code, 'mode' => 'hub_arrival', 'hub_id' => $context['destinationHub']->id])->assertRedirect(route('logistics.shipment-scanner'));
        $this->post(route('logistics.shipment-scanner.action', $context['shipment']), ['action' => 'arrive_hub'])->assertRedirect(route('logistics.shipment-scanner'));
        $this->assertSame(OrderStatus::AtDestinationHub, $context['sellerOrder']->fresh()->workflow_status);
        $this->assertNotNull($context['shipment']->fresh()->assignments()->where('assignment_type', 'HUB_TRANSFER')->latest('id')->first()->arrived_at);
    }

    public function test_hub_arrival_scan_is_bound_to_the_exact_active_destination_hub(): void
    {
        $context = $this->context(OrderStatus::InTransitToHub);
        $transfer = $context['shipment']->assignments()->create([
            'logistics_provider_id' => $context['provider']->id, 'assignment_type' => 'HUB_TRANSFER', 'status' => 'ACCEPTED',
            'origin_hub_id' => $context['provider']->main_sorting_hub_id, 'destination_hub_id' => $context['destinationHub']->id,
            'rider_id' => $context['deliveryRider']->id, 'assigned_by' => $context['logisticsUser']->id, 'dispatched_at' => now(),
        ]);
        $wrongHub = LogisticsHub::create(['logistics_provider_id' => $context['provider']->id, 'name' => 'Wrong Hub', 'region' => 'Region', 'region_code' => '01', 'province' => 'Province', 'province_code' => '0101', 'city' => 'City', 'city_municipality_code' => '010101', 'barangay' => 'Other', 'barangay_code' => '010101003', 'is_active' => true]);
        $this->actingAs($context['logisticsUser'])->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $context['shipment']->tracking_code, 'mode' => 'hub_arrival', 'hub_id' => $wrongHub->id])->assertSessionHasErrors('hub_id');
        $this->assertSame(OrderStatus::InTransitToHub, $context['sellerOrder']->fresh()->workflow_status);
        $this->assertNull($transfer->fresh()->arrived_at);
    }

    public function test_transfer_decline_preserves_history_and_logistics_can_reassign_before_dispatch(): void
    {
        $context = $this->context(OrderStatus::Sorted);
        $context['deliveryRider']->update(['logistics_hub_id' => $context['provider']->main_sorting_hub_id]);
        $assignment = $context['shipment']->assignments()->create([
            'logistics_provider_id' => $context['provider']->id, 'assignment_type' => 'HUB_TRANSFER', 'status' => 'ASSIGNED',
            'rider_id' => $context['deliveryRider']->id, 'origin_hub_id' => $context['provider']->main_sorting_hub_id,
            'destination_hub_id' => $context['destinationHub']->id, 'assigned_by' => $context['logisticsUser']->id,
        ]);
        $this->actingAs($context['deliveryUser'])->post(route('rider.shipment-scanner.resolve'), ['tracking_code' => $context['shipment']->tracking_code, 'mode' => 'hub_transfer_dispatch'])->assertRedirect(route('rider.shipment-scanner'));
        $this->post(route('rider.shipment-scanner.action', $context['shipment']), ['action' => 'decline_hub_transfer', 'transfer_decline_reason' => 'Vehicle unavailable'])->assertRedirect(route('rider.shipment-scanner'));
        $this->assertSame('DECLINED', $assignment->fresh()->status);
        $this->assertSame(OrderStatus::Sorted, $context['sellerOrder']->fresh()->workflow_status);
        $this->actingAs($context['logisticsUser'])->post(route('logistics.database.hub-transfer.assign', $context['sellerOrder']), ['rider_id' => $context['deliveryRider']->id])->assertSessionHasNoErrors();
        $this->assertSame(2, $context['shipment']->assignments()->where('assignment_type', 'HUB_TRANSFER')->count());
        $this->assertSame('ASSIGNED', $context['shipment']->assignments()->where('assignment_type', 'HUB_TRANSFER')->latest('id')->first()->status);
    }

    public function test_scanner_cannot_bypass_delivery_proof_or_cod_completion_rules(): void
    {
        extract($this->context(OrderStatus::OutForDelivery));

        $this->actingAs($deliveryUser)
            ->from(route('rider.shipment-scanner'))
            ->post(route('rider.shipment-scanner.action', $shipment), ['action' => 'delivered'])
            ->assertRedirect(route('rider.shipment-scanner'))
            ->assertSessionHasErrors('action');
        $this->assertSame(OrderStatus::OutForDelivery, $sellerOrder->fresh()->workflow_status);
        $this->assertFalse($shipment->fresh()->cod_collected);
        $this->assertNull($shipment->fresh()->proof_path);
    }

    public function test_inactive_hub_rider_cannot_operate_a_scanner_action(): void
    {
        extract($this->context());
        $hub = LogisticsHub::create([
            'logistics_provider_id' => $provider->id, 'name' => 'Inactive hub',
            'region' => 'Region', 'region_code' => '01', 'province' => 'Province', 'province_code' => '0101',
            'city' => 'City', 'city_municipality_code' => '010101', 'barangay' => 'Other Barangay', 'barangay_code' => '010101004',
            'is_active' => false,
        ]);
        $pickupRider->update(['logistics_hub_id' => $hub->id]);

        $this->actingAs($pickupUser)
            ->post(route('rider.shipment-scanner.resolve'), ['payload' => $this->payload($shipment)])
            ->assertForbidden();
    }

    private function context(OrderStatus $status = OrderStatus::AssignedToRider): array
    {
        [$logisticsUser, $provider] = $this->provider('scanner');
        [$pickupUser, , $pickupRider] = $this->rider($provider, 'pickup');
        [$deliveryUser, , $deliveryRider] = $this->rider($provider, 'delivery');
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false]);
        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $this->role($buyer, 'buyer'); $this->role($sellerUser, 'seller');
        $seller = Seller::create(['user_id' => $sellerUser->id, 'name' => 'Scanner Store', 'slug' => 'scanner-store-'.uniqid(), 'status' => 'approved']);
        $pickup = Address::create(['user_id' => $sellerUser->id, 'label' => 'Pickup', 'recipient' => 'Store', 'phone' => '0912', 'line1' => 'Street', 'region' => 'Region', 'region_code' => '01', 'province' => 'Province', 'province_code' => '0101', 'city' => 'City', 'city_municipality_code' => '010101', 'barangay' => 'Barangay', 'barangay_code' => '010101001', 'is_default' => true]);
        $seller->update(['pickup_address_id' => $pickup->id]);
        $order = Order::create(['order_number' => 'SCAN-'.uniqid(), 'reference' => 'SCAN-'.uniqid(), 'buyer_id' => $buyer->id, 'seller_id' => $sellerUser->id, 'status' => $status->value, 'payment_method' => 'cod', 'payment_status' => 'UNPAID', 'subtotal' => 100, 'shipping_fee' => 50, 'discount_amount' => 0, 'total_amount' => 150, 'total_minor' => 15000, 'recipient_name' => 'Buyer', 'recipient_phone' => '0912', 'region' => 'Region', 'region_code' => '01', 'province' => 'Province', 'province_code' => '0101', 'municipality' => 'City', 'city_municipality_code' => '010101', 'barangay' => 'Barangay', 'barangay_code' => '010101001', 'street_address' => 'Street', 'shipping_address' => []]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'logistics_provider_id' => $provider->id, 'subtotal_minor' => 10000, 'shipping_fee_minor' => 5000, 'commission_minor' => 0, 'status' => $status->legacySellerStatus(), 'workflow_status' => $status]);
        $shipment = Shipment::create(['seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id, 'pickup_rider_id' => $pickupRider->id, 'rider_id' => $status === OrderStatus::AtSortingCenter ? null : $deliveryRider->id, 'tracking_code' => 'SUKI-SCAN-'.strtoupper(uniqid()), 'status' => $status->legacyShipmentStatus(), 'workflow_status' => $status, 'fee_minor' => 5000, 'cod_amount_minor' => 15000, 'cod_collected' => false, 'attempts' => 0]);

        if ($status !== OrderStatus::ReadyForPickup) {
            $shipment->update(['pickup_accepted_at' => now()]);
        }
        $shipment->assignments()->create(['logistics_provider_id' => $provider->id, 'assignment_type' => 'PICKUP', 'status' => $status === OrderStatus::ReadyForPickup ? 'ASSIGNED' : 'COMPLETED', 'rider_id' => $pickupRider->id, 'assigned_by' => $logisticsUser->id, 'responded_at' => $status === OrderStatus::ReadyForPickup ? null : now()]);
        if (in_array($status, [OrderStatus::AssignedToRider, OrderStatus::OutForDelivery, OrderStatus::Delivered, OrderStatus::Completed], true)) {
            $shipment->assignments()->create(['logistics_provider_id' => $provider->id, 'assignment_type' => 'DELIVERY', 'status' => $status === OrderStatus::AssignedToRider ? 'ASSIGNED' : ($status === OrderStatus::Completed || $status === OrderStatus::Delivered ? 'COMPLETED' : 'ACCEPTED'), 'rider_id' => $deliveryRider->id, 'assigned_by' => $logisticsUser->id, 'destination_hub_id' => null, 'responded_at' => $status === OrderStatus::AssignedToRider ? null : now()]);
        }

        $destinationHub = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'name' => 'Duhat Destination Hub', 'region' => 'Region', 'region_code' => '01', 'province' => 'Province', 'province_code' => '0101', 'city' => 'City', 'city_municipality_code' => '010101', 'barangay' => 'Barangay', 'barangay_code' => '010101001', 'is_active' => true]);
        $mainHub = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'name' => 'Main Sorting Center', 'region' => 'Region', 'region_code' => '01', 'province' => 'Province', 'province_code' => '0101', 'city' => 'City', 'city_municipality_code' => '010101', 'barangay' => 'Sorting Center', 'barangay_code' => '010101002', 'is_active' => true]);
        $provider->update(['main_sorting_hub_id' => $mainHub->id]);
        $sortingZone = LogisticsSortingZone::create(['logistics_provider_id' => $provider->id, 'logistics_hub_id' => $destinationHub->id, 'zone_code' => 'SC-01', 'name' => 'Santa Cruz Central', 'priority' => 100, 'is_active' => true]);
        $sortingZone->areas()->create(['region_code' => '01', 'province_code' => '0101', 'city_municipality_code' => '010101', 'barangay_code' => '010101001', 'area_key' => '01:0101:010101:010101001']);
        if (in_array($status, [OrderStatus::Sorted, OrderStatus::InTransitToHub, OrderStatus::AtDestinationHub, OrderStatus::AssignedToRider, OrderStatus::OutForDelivery, OrderStatus::Delivered, OrderStatus::Completed], true)) {
            $shipment->update(['sorting_zone_id' => $sortingZone->id, 'destination_hub_id' => $destinationHub->id, 'logistics_hub_id' => $destinationHub->id]);
        }

        return compact('logisticsUser', 'provider', 'pickupUser', 'pickupRider', 'deliveryUser', 'deliveryRider', 'sellerOrder', 'shipment', 'sortingZone', 'destinationHub', 'mainHub');
    }

    private function provider(string $key): array
    {
        $user = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'is_suspended' => false]);
        $this->role($user, 'logistics');
        return [$user, LogisticsProvider::create(['user_id' => $user->id, 'name' => $key, 'slug' => $key.'-'.uniqid(), 'status' => 'approved'])];
    }

    private function rider(LogisticsProvider $provider, string $key): array
    {
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active', 'is_suspended' => false]);
        $this->role($user, 'rider');
        $rider = Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => strtoupper($key).'-'.uniqid(), 'is_active' => true, 'availability' => 'ONLINE', 'approved_at' => now()]);
        return [$user, $provider, $rider];
    }

    private function role(User $user, string $role): void
    {
        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => $role])->id]);
    }

    private function payload(Shipment $shipment): string
    {
        return app(ShipmentQrService::class)->payload($shipment);
    }

    private function fakePsgc(): void
    {
        Http::fake([
            'https://psgc.cloud/api/v2/regions' => Http::response(['data' => [['code' => '01', 'name' => 'Region']]]),
            'https://psgc.cloud/api/v2/regions/01/provinces' => Http::response(['data' => [['code' => '0101', 'name' => 'Province']]]),
            'https://psgc.cloud/api/v2/provinces/0101/cities-municipalities' => Http::response(['data' => [['code' => '010101', 'name' => 'City']]]),
            'https://psgc.cloud/api/v2/cities-municipalities/010101/barangays' => Http::response(['data' => [['code' => '010101001', 'name' => 'Barangay']]]),
        ]);
    }

    private function fakeDuhatPsgc(): void
    {
        Http::fake([
            'https://psgc.cloud/api/v2/regions' => Http::response(['data' => [['code' => '0400000000', 'name' => 'Region IV-A / CALABARZON']]]),
            'https://psgc.cloud/api/v2/regions/0400000000/provinces' => Http::response(['data' => [['code' => '0403400000', 'name' => 'Laguna']]]),
            'https://psgc.cloud/api/v2/provinces/0403400000/cities-municipalities' => Http::response(['data' => [['code' => '0403426000', 'name' => 'Santa Cruz']]]),
            'https://psgc.cloud/api/v2/cities-municipalities/0403426000/barangays' => Http::response(['data' => [
                ['code' => '0403426005', 'name' => 'Duhat'], ['code' => '0403426006', 'name' => 'Poblacion'],
            ]]),
        ]);
    }
}
