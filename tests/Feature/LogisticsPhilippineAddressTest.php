<?php

namespace Tests\Feature;

use App\Mail\AccountVerificationOtpMail;
use App\Models\Application;
use App\Models\LogisticsProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogisticsPhilippineAddressTest extends TestCase
{
    use RefreshDatabase;

    private function fakePsgc(): void
    {
        Http::fake([
            'https://psgc.cloud/api/v2/regions' => Http::response(['data' => [
                ['code' => '01', 'name' => 'Region A'],
                ['code' => '13', 'name' => 'NCR-style'],
            ]]),
            'https://psgc.cloud/api/v2/regions/01/provinces' => Http::response(['data' => [['code' => '0101', 'name' => 'Province A']]]),
            'https://psgc.cloud/api/v2/provinces/0101/cities-municipalities' => Http::response(['data' => [['code' => '010101', 'name' => 'City A']]]),
            'https://psgc.cloud/api/v2/cities-municipalities/010101/barangays' => Http::response(['data' => [['code' => '010101001', 'name' => 'Barangay A']]]),
            'https://psgc.cloud/api/v2/regions/13/provinces' => Http::response(['data' => []]),
            'https://psgc.cloud/api/v2/regions/13/cities-municipalities' => Http::response(['data' => [['code' => '130001', 'name' => 'NCR City']]]),
            'https://psgc.cloud/api/v2/cities-municipalities/130001/barangays' => Http::response(['data' => [['code' => '130001001', 'name' => 'NCR Barangay']]]),
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Lara', 'last_name' => 'Logistics', 'middle_initial' => 'Q',
            'sex' => 'Female', 'birthday' => '1990-05-15', 'phone' => '09175550001',
            'email' => 'logistics@example.com', 'business_name' => 'Lara Logistics',
            'address' => '10 Sorting Center Road', 'region' => '01', 'province' => '0101',
            'municipality' => '010101', 'barangay' => '010101001',
            'government_id' => UploadedFile::fake()->create('id.jpg', 50, 'image/jpeg'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 50, 'application/pdf'),
            'password' => 'Password1!', 'password_confirmation' => 'Password1!', 'terms' => '1',
        ], $overrides);
    }

    public function test_normal_logistics_registration_persists_structured_address_and_keeps_free_text_address(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->fakePsgc();

        $this->post(route('logistics.register.submit'), $this->payload())
            ->assertRedirect(route('verification.contact.show'));

        $user = User::query()->where('email', 'logistics@example.com')->firstOrFail();
        $provider = LogisticsProvider::query()->where('user_id', $user->id)->firstOrFail();
        $application = Application::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('pending', $user->status);
        $this->assertNull($user->phone_verified_at);
        $this->assertTrue(Hash::check('Password1!', $user->password));
        $this->assertNotSame('Password1!', $user->password);
        $this->assertArrayNotHasKey('password_confirmation', $user->getAttributes());
        $this->assertSame('10 Sorting Center Road', $provider->address);
        $this->assertSame('Region A', $provider->region);
        $this->assertSame('Province A', $provider->province);
        $this->assertSame('City A', $provider->city);
        $this->assertSame('Barangay A', $provider->barangay);
        $this->assertSame('pending', $application->status);
        $this->assertArrayNotHasKey('service_areas', $application->application_data);
        $this->assertDatabaseHas('account_verifications', ['user_id' => $user->id, 'channel' => 'email', 'verified_at' => null]);
        Mail::assertSent(AccountVerificationOtpMail::class, 1);
    }

    public function test_logistics_password_requires_the_domain_complexity_policy_and_confirmation(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('local');
        $this->fakePsgc();

        foreach (['', 'Aa1!abc', 'lowercaseonly', 'PASSWORD1!', 'PasswordOnly!', 'Password123'] as $password) {
            $response = $this->from(route('logistics.register'))->post(route('logistics.register.submit'), $this->payload([
                'password' => $password,
                'password_confirmation' => $password,
            ]));

            $response->assertSessionHasErrors('password');
        }

        $this->from(route('logistics.register'))->post(route('logistics.register.submit'), $this->payload([
            'password' => 'Password1!',
            'password_confirmation' => 'different-password',
        ]))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('logistics_providers', 0);
        $this->assertDatabaseCount('applications', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('application-documents/logistics'));
    }

    public function test_logistics_accepts_no_province_hierarchy_without_fabricating_a_province(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->fakePsgc();

        $this->post(route('logistics.register.submit'), $this->payload([
            'email' => 'ncr-logistics@example.com', 'phone' => '09175550002',
            'business_name' => 'NCR Logistics', 'region' => '13', 'province' => null,
            'municipality' => '130001', 'barangay' => '130001001',
        ]))->assertRedirect(route('verification.contact.show'));

        $provider = LogisticsProvider::query()->where('name', 'NCR Logistics')->firstOrFail();
        $this->assertSame('NCR-style', $provider->region);
        $this->assertNull($provider->province);
        $this->assertSame('NCR City', $provider->city);
        $this->assertSame('NCR Barangay', $provider->barangay);
    }

    public function test_tampered_logistics_hierarchies_are_rejected_before_any_persistence(): void
    {
        Storage::fake('local');
        $this->fakePsgc();

        $this->post(route('logistics.register.submit'), $this->payload(['region' => '99']))->assertSessionHasErrors('region');
        $this->post(route('logistics.register.submit'), $this->payload(['region' => '13', 'province' => '0101']))->assertSessionHasErrors('province');
        $this->post(route('logistics.register.submit'), $this->payload(['municipality' => '130001']))->assertSessionHasErrors('municipality');
        $this->post(route('logistics.register.submit'), $this->payload(['barangay' => '130001001']))->assertSessionHasErrors('barangay');
        $this->post(route('logistics.register.submit'), $this->payload(['province' => null]))->assertSessionHasErrors('province');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('logistics_providers', 0);
        $this->assertDatabaseCount('applications', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('application-documents/logistics'));
    }

    public function test_provider_failure_and_malformed_data_reject_logistics_without_partial_records(): void
    {
        Storage::fake('local');
        Http::fake(['https://psgc.cloud/api/v2/regions' => Http::response([], 500)]);

        $this->from(route('logistics.register'))
            ->post(route('logistics.register.submit'), $this->payload())
            ->assertRedirect(route('logistics.register'))
            ->assertSessionHasErrors('region');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('logistics_providers', 0);
        $this->assertDatabaseCount('applications', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('application-documents/logistics'));
    }

    public function test_malformed_psgc_response_is_safe_without_partial_records(): void
    {
        Storage::fake('local');
        Http::fake(['https://psgc.cloud/api/v2/regions' => Http::response(['unexpected' => []])]);
        $this->from(route('logistics.register'))
            ->post(route('logistics.register.submit'), $this->payload())
            ->assertSessionHasErrors('region');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('logistics_providers', 0);
        $this->assertDatabaseCount('applications', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('application-documents/logistics'));
    }

    public function test_timeout_is_safe_and_preserves_google_logistics_retry_state(): void
    {
        Storage::fake('local');

        Http::fake(static function (): void {
            throw new ConnectionException('Address provider timeout.');
        });
        $identity = ['intent' => 'logistics', 'email' => 'google-logistics@example.com', 'provider_user_id' => 'google-logistics-subject', 'issued_at' => now()->timestamp];
        $this->withSession(['google_registration' => $identity])
            ->from(route('logistics.register'))
            ->post(route('logistics.register.submit'), $this->payload(['email' => 'google-logistics@example.com']))
            ->assertRedirect(route('logistics.register'))
            ->assertSessionHasErrors('region')
            ->assertSessionHas('google_registration');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('logistics_providers', 0);
        $this->assertDatabaseCount('applications', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('application-documents/logistics'));
    }
}
