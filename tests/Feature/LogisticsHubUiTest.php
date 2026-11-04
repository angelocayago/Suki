<?php

namespace Tests\Feature;

use App\Models\{LogisticsHub, LogisticsProvider, Rider, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Tests\TestCase;

class LogisticsHubUiTest extends TestCase
{
    use RefreshDatabase;
    private User $actor;
    private LogisticsProvider $provider;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Memory-only Hub UI fixtures required.');
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertStringStartsWith(strtolower(str_replace('\\', '/', sys_get_temp_dir())).'/', strtolower(str_replace('\\', '/', storage_path())));
        Http::preventStrayRequests();
        $this->actor = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'is_suspended' => false]);
        $this->provider = LogisticsProvider::create(['user_id' => $this->actor->id, 'name' => 'UI Provider', 'slug' => 'ui-provider', 'status' => 'approved']);
        $this->actingAs($this->actor);
    }

    private function hub(bool $active = true): LogisticsHub
    {
        return $this->provider->hubs()->create(['name' => 'Canonical Hub', 'region' => 'Fixture Region', 'region_code' => '01', 'province' => 'Fixture Province', 'province_code' => '0101', 'city' => 'Fixture City', 'city_municipality_code' => '010101', 'barangay' => 'Fixture Barangay', 'barangay_code' => '010101001', 'postal_code' => '1234', 'is_active' => $active]);
    }

    private function rider(bool $approved = true): Rider
    {
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active', 'is_suspended' => false, 'first_name' => 'Fixture', 'last_name' => 'Rider']);
        return Rider::forceCreate(['id' => 41, 'user_id' => $user->id, 'logistics_provider_id' => $this->provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'UI-41', 'approved_at' => $approved ? now() : null, 'is_active' => $approved, 'availability' => 'ONLINE']);
    }

    public function test_sidebar_active_link_setup_guide_and_empty_states_do_not_create_records(): void
    {
        $page = $this->get(route('logistics.hubs.index'))->assertOk();
        $page->assertSee('Barangay Hubs')->assertSee('No hubs created.')->assertSee('No active hubs available.')->assertSee('No approved Riders available for hub assignment.')
            ->assertSee('Configure the Rider')->assertSee('Hub assignment does not grant service-area coverage')->assertSee(route('logistics.assignments'), false);
        $this->assertMatchesRegularExpression('/<a\b[^>]*href="'.preg_quote(route('logistics.hubs.index'), '/').'"[^>]*aria-current="page"[^>]*>/s', $page->getContent());
        foreach (['logistics.riders', 'logistics.assignments', 'logistics.parcels', 'logistics.sorting', 'logistics.monitoring'] as $route) $page->assertSee(route($route), false);
        $page->assertDontSee('name="logistics_hub_id"', false);
        $this->assertDatabaseCount('logistics_hubs', 0);
        $this->assertDatabaseCount('riders', 0);
    }

    public function test_hub_details_associated_riders_and_service_area_link_use_correct_identity(): void
    {
        $hub = $this->hub(); $rider = $this->rider(); $rider->update(['logistics_hub_id' => $hub->id]);
        $this->get(route('logistics.hubs.index'))->assertOk()->assertSee('Canonical Hub')->assertSee('Fixture Region')->assertSee('Fixture Province')->assertSee('Fixture City')->assertSee('Fixture Barangay')->assertSee('1234')->assertSee('Associated Riders (1)')->assertSee('Fixture Rider')
            ->assertSee(route('logistics.riders.service-areas', 41), false)->assertSee(route('logistics.hubs.riders.assign', 41), false)->assertSee('Remove hub assignment');
        $this->assertDatabaseCount('rider_service_areas', 0);
    }

    public function test_unassigned_rider_without_active_hubs_has_guidance_instead_of_unusable_assignment_form(): void
    {
        $rider = $this->rider();
        $this->get(route('logistics.hubs.index'))->assertOk()->assertSee('No hub assigned')->assertSee('No active hubs available.')->assertSee(route('logistics.riders.service-areas', $rider), false)->assertDontSee('name="logistics_hub_id"', false);
        $this->assertNull($rider->fresh()->logistics_hub_id);
    }

    public function test_inactive_hub_can_be_activated_and_assigned_or_removed_using_existing_actions(): void
    {
        $hub = $this->hub(false); $rider = $this->rider();
        $this->get(route('logistics.hubs.index'))->assertSee('Inactive')->assertSee('No active hubs available.');
        $this->patch(route('logistics.hubs.toggle', $hub))->assertSessionHas('success');
        $this->get(route('logistics.hubs.index'))->assertSee('Active')->assertSee('Assign hub')->assertDontSee('No active hubs available.');
        $this->patch(route('logistics.hubs.riders.assign', $rider), ['logistics_hub_id' => $hub->id])->assertSessionHasNoErrors();
        $this->assertSame($hub->id, $rider->fresh()->logistics_hub_id);
        $this->patch(route('logistics.hubs.riders.assign', $rider), ['logistics_hub_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($rider->fresh()->logistics_hub_id);
    }

    public function test_pending_riders_are_not_presented_as_approved_assignment_candidates(): void
    {
        $this->hub(); $rider = $this->rider(false);
        $this->get(route('logistics.hubs.index'))->assertOk()->assertSee('No approved Riders available for hub assignment.')->assertDontSee(route('logistics.hubs.riders.assign', $rider), false)->assertDontSee(route('logistics.riders.service-areas', $rider), false);
    }

    public function test_foreign_hubs_and_riders_are_not_exposed_and_assignment_stays_scoped(): void
    {
        $hub = $this->hub(); $rider = $this->rider();
        $otherActor = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'is_suspended' => false]);
        $otherProvider = LogisticsProvider::create(['user_id' => $otherActor->id, 'name' => 'Other', 'slug' => 'ui-other', 'status' => 'approved']);
        $foreignHub = $hub->replicate();
        $foreignHub->fill(['logistics_provider_id' => $otherProvider->id, 'name' => 'Foreign Hub'])->save();
        $this->actingAs($otherActor)->get(route('logistics.hubs.index'))->assertOk()->assertDontSee('Canonical Hub')->assertDontSee('Fixture Rider')->assertDontSee(route('logistics.riders.service-areas', $rider), false);
        $this->patch(route('logistics.hubs.toggle', $hub))->assertNotFound();
        $this->patch(route('logistics.hubs.riders.assign', $rider), ['logistics_hub_id' => $hub->id])->assertNotFound();
        $this->actingAs($this->actor)->get(route('logistics.hubs.index'))->assertDontSee('Foreign Hub');
        $this->patch(route('logistics.hubs.riders.assign', $rider), ['logistics_hub_id' => $foreignHub->id])->assertNotFound();
        $this->assertNull($rider->fresh()->logistics_hub_id);
    }

    public function test_invalid_forms_display_feedback_and_keep_old_values_without_saving(): void
    {
        $this->from(route('logistics.hubs.index'))->post(route('logistics.hubs.store'), ['name' => 'Remember my hub', 'region' => '', 'municipality' => '', 'barangay' => ''])->assertSessionHasErrors(['region', 'municipality', 'barangay']);
        $this->get(route('logistics.hubs.index'))->assertOk()->assertSee('Your changes were not saved.')->assertSee('value="Remember my hub"', false)->assertSee('for="hubRegion"', false)->assertSee('for="hubPostalCode"', false);
        $this->assertDatabaseCount('logistics_hubs', 0);
    }

    public function test_non_logistics_and_unapproved_accounts_are_rejected_and_guest_is_redirected(): void
    {
        foreach (['buyer', 'seller', 'rider'] as $role) $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active']))->get(route('logistics.hubs.index'))->assertForbidden();
        $this->actor->update(['status' => 'pending']);
        $this->actingAs($this->actor)->get(route('logistics.hubs.index'))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->get(route('logistics.hubs.index'))->assertRedirect(route('login'));
    }
}
