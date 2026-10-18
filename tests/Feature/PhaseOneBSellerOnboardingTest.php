<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Mail\AccountVerificationOtpMail;
use App\Models\Application;
use App\Models\Category;
use App\Models\Role;
use App\Models\SellerDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseOneBSellerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private SellerOnboardingSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new SellerOnboardingSmsSender();
        $this->app->instance(SmsSender::class, $this->sms);
        Mail::fake();
    }

    public function test_registration_creates_a_complete_pending_seller_application(): void
    {
        Storage::fake('seller_documents');
        $this->fakePsgc();
        $category = $this->category();

        $response = $this->post(route('seller.register.submit'), $this->validPayload($category));

        $response->assertRedirect(route('verification.contact.show'));
        $response->assertSessionHas('success');

        $user = User::query()->where('email', 'new-seller@example.com')->firstOrFail();
        $seller = $user->sellers()->firstOrFail();
        $application = $user->applications()->where('application_type', 'seller')->firstOrFail();

        $this->assertSame('seller', $user->role);
        $this->assertSame('pending', $user->status);
        $this->assertFalse($user->is_suspended);
        $this->assertTrue(Hash::check('SellerPass1!', $user->password));
        $this->assertNotSame('SellerPass1!', $user->password);
        $this->assertArrayNotHasKey('password_confirmation', $user->getAttributes());
        $this->assertTrue($user->roles->contains(fn (Role $role) => $role->name === 'seller'));

        $this->assertSame('pending', $seller->status);
        $this->assertSame($category->id, $seller->category_id);
        $this->assertSame('business', $seller->seller_type);
        $this->assertSame('123-456-789-000', $seller->tin);
        $this->assertTrue($seller->allowedCategories->contains($category));
        $this->assertSame($seller->pickup_address_id, $user->addresses()->where('is_default', true)->value('id'));
        $this->assertDatabaseHas('addresses', [
            'id' => $seller->pickup_address_id,
            'region' => 'Region A',
            'province' => 'Province A',
            'city' => 'City A',
            'barangay' => 'Barangay A',
            'line1' => '10 Market Street',
            'postal_code' => '4009',
        ]);

        $this->assertSame('pending', $application->status);
        $this->assertSame($seller->id, $application->application_data['seller_id']);
        $this->assertCount(2, $application->sellerDocuments);

        $this->assertDatabaseHas('account_verifications', [
            'user_id' => $user->id,
            'channel' => 'email',
            'verified_at' => null,
        ]);
        $this->assertDatabaseMissing('account_verifications', ['user_id' => $user->id, 'channel' => 'unified']);
        $this->assertDatabaseMissing('account_verifications', ['user_id' => $user->id, 'channel' => 'sms']);
        Mail::assertSent(AccountVerificationOtpMail::class, 1);
        $this->assertCount(0, $this->sms->messages);

        foreach ($application->sellerDocuments as $document) {
            $this->assertSame('seller_documents', $document->disk);
            $this->assertSame('pending', $document->verification_status);
            $this->assertSame(64, strlen($document->checksum));
            Storage::disk($document->disk)->assertExists($document->path);
        }
    }

    public function test_invalid_documents_and_invalid_identity_data_are_rejected_without_partial_records(): void
    {
        Storage::fake('seller_documents');
        $category = $this->category();
        $payload = $this->validPayload($category);
        $payload['birthday'] = now()->subYears(17)->toDateString();
        $payload['phone'] = '12345';
        $payload['tin'] = 'invalid-tin';
        $payload['valid_id'] = UploadedFile::fake()->create('script.exe', 20, 'application/x-msdownload');

        $response = $this->from(route('seller.register'))
            ->post(route('seller.register.submit'), $payload);

        $response->assertRedirect(route('seller.register'));
        $response->assertSessionHasErrors(['birthday', 'phone', 'tin', 'valid_id']);
        $this->assertDatabaseMissing('users', ['email' => 'new-seller@example.com']);
        $this->assertSame(0, SellerDocument::query()->count());
        $this->assertSame([], Storage::disk('seller_documents')->allFiles());
    }

    public function test_seller_password_requires_the_domain_complexity_policy_and_confirmation(): void
    {
        Storage::fake('seller_documents');
        $this->fakePsgc();
        $category = $this->category();

        foreach (['' , 'Aa1!abc', 'lowercaseonly', 'PASSWORD1!', 'PasswordOnly!', 'Password123'] as $password) {
            $this->post(route('seller.register.submit'), $this->validPayload($category, [
                'password' => $password,
                'password_confirmation' => $password,
            ]))->assertSessionHasErrors('password');
        }

        $this->post(route('seller.register.submit'), $this->validPayload($category, [
            'password' => 'SellerPass1!',
            'password_confirmation' => 'different-password',
        ]))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('sellers', 0);
        $this->assertDatabaseCount('applications', 0);
        $this->assertSame([], Storage::disk('seller_documents')->allFiles());
    }

    public function test_business_permit_is_required_for_business_but_optional_for_individual_seller(): void
    {
        Storage::fake('seller_documents');
        $this->fakePsgc();
        $category = $this->category();

        $business = $this->validPayload($category);
        unset($business['business_permit']);
        $this->post(route('seller.register.submit'), $business)
            ->assertSessionHasErrors('business_permit');

        $individual = $this->validPayload($category);
        $individual['email'] = 'individual@example.com';
        $individual['phone'] = '09170000002';
        $individual['seller_type'] = 'individual';
        unset($individual['business_permit']);

        $this->post(route('seller.register.submit'), $individual)
            ->assertRedirect(route('verification.contact.show'));

        $application = Application::query()
            ->whereHas('user', fn ($query) => $query->where('email', 'individual@example.com'))
            ->firstOrFail();
        $this->assertSame(['valid_id'], $application->sellerDocuments->pluck('document_type')->all());
    }

    public function test_registration_page_uses_active_database_categories(): void
    {
        $active = $this->category();
        Category::query()->create([
            'name' => 'Inactive Category',
            'slug' => 'inactive-category',
            'position' => 2,
            'is_active' => false,
        ]);

        $this->get(route('seller.register'))
            ->assertOk()
            ->assertSee($active->name)
            ->assertSee('id="region"', false)
            ->assertDontSee('/api/psgc')
            ->assertSee('Submit for Admin Review')
            ->assertSee('Maximum 5 MB')
            ->assertSee('Your account will remain pending')
            ->assertDontSee('Inactive Category');
    }

    public function test_seller_accepts_a_valid_no_province_address_without_persisting_a_fake_province(): void
    {
        Storage::fake('seller_documents');
        $this->fakePsgc();
        $payload = $this->validPayload($this->category());
        $payload = array_merge($payload, [
            'email' => 'ncr-seller@example.com',
            'phone' => '09170000009',
            'business_name' => 'NCR Store',
            'region' => '13',
            'province' => null,
            'municipality' => '130001',
            'barangay' => '130001001',
        ]);

        $this->post(route('seller.register.submit'), $payload)
            ->assertRedirect(route('verification.contact.show'));

        $this->assertDatabaseHas('addresses', [
            'region' => 'NCR-style',
            'province' => null,
            'city' => 'NCR City',
            'barangay' => 'NCR Barangay',
        ]);
    }

    public function test_tampered_seller_hierarchies_create_no_partial_records(): void
    {
        Storage::fake('seller_documents');
        $this->fakePsgc();
        $category = $this->category();

        $this->post(route('seller.register.submit'), $this->validPayload($category, [
            'region' => '13', 'province' => '0101',
        ]))->assertSessionHasErrors('province');
        $this->post(route('seller.register.submit'), $this->validPayload($category, [
            'municipality' => '130001',
        ]))->assertSessionHasErrors('municipality');
        $this->post(route('seller.register.submit'), $this->validPayload($category, [
            'barangay' => '130001001',
        ]))->assertSessionHasErrors('barangay');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('addresses', 0);
        $this->assertDatabaseCount('sellers', 0);
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_google_seller_preserves_trusted_onboarding_state_when_psgc_verification_fails(): void
    {
        Storage::fake('seller_documents');
        Http::fake(['https://psgc.cloud/api/v2/regions' => Http::response([], 500)]);
        $identity = [
            'intent' => 'seller',
            'email' => 'google-seller@example.com',
            'provider_user_id' => 'google-seller-subject',
            'issued_at' => now()->timestamp,
        ];

        $this->withSession(['google_registration' => $identity])
            ->from(route('seller.register'))
            ->post(route('seller.register.submit'), $this->validPayload($this->category(), [
                'email' => 'google-seller@example.com',
            ]))
            ->assertRedirect(route('seller.register'))
            ->assertSessionHasErrors('region')
            ->assertSessionHas('google_registration');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('applications', 0);
        $this->assertSame([], Storage::disk('seller_documents')->allFiles());
    }

    public function test_unavailable_or_malformed_psgc_data_rejects_seller_registration_without_partial_records(): void
    {
        Storage::fake('seller_documents');
        $category = $this->category();

        Http::fake(['https://psgc.cloud/api/v2/regions' => Http::response([], 500)]);
        $this->from(route('seller.register'))
            ->post(route('seller.register.submit'), $this->validPayload($category))
            ->assertRedirect(route('seller.register'))
            ->assertSessionHasErrors('region');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('addresses', 0);
        $this->assertDatabaseCount('sellers', 0);
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_malformed_psgc_payload_rejects_seller_registration_without_partial_records(): void
    {
        Storage::fake('seller_documents');
        Http::fake(['https://psgc.cloud/api/v2/regions' => Http::response(['unexpected' => []], 200)]);

        $this->from(route('seller.register'))
            ->post(route('seller.register.submit'), $this->validPayload($this->category()))
            ->assertRedirect(route('seller.register'))
            ->assertSessionHasErrors('region');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('addresses', 0);
        $this->assertDatabaseCount('sellers', 0);
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_psgc_timeout_rejects_seller_registration_without_partial_records(): void
    {
        Storage::fake('seller_documents');
        Http::fake(static function (): void {
            throw new ConnectionException('Timed out while loading address data.');
        });

        $this->from(route('seller.register'))
            ->post(route('seller.register.submit'), $this->validPayload($this->category()))
            ->assertRedirect(route('seller.register'))
            ->assertSessionHasErrors('region');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('addresses', 0);
        $this->assertDatabaseCount('sellers', 0);
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_only_an_active_admin_can_view_a_private_seller_document(): void
    {
        Storage::fake('seller_documents');
        $this->fakePsgc();
        $category = $this->category();
        $this->post(route('seller.register.submit'), $this->validPayload($category));
        $application = Application::query()->where('application_type', 'seller')->firstOrFail();
        $document = $application->sellerDocuments()->where('document_type', 'valid_id')->firstOrFail();

        $this->get(route('superadmin.applications.documents.show', [$application, 'valid_id']))
            ->assertRedirect(route('login'));

        $sellerUser = $application->user;
        $this->actingAs($sellerUser)
            ->get(route('superadmin.applications.documents.show', [$application, 'valid_id']))
            ->assertForbidden();

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'is_suspended' => false,
        ]);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $admin->roles()->attach($adminRole);

        $this->actingAs($admin)
            ->get(route('superadmin.applications.documents.show', [$application, 'valid_id']))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        Storage::disk($document->disk)->assertExists($document->path);
    }

    public function test_admin_approval_synchronizes_seller_and_document_review_status(): void
    {
        Notification::fake();
        Storage::fake('seller_documents');
        $this->fakePsgc();
        $this->post(route('seller.register.submit'), $this->validPayload($this->category()));
        $application = Application::query()->where('application_type', 'seller')->firstOrFail();

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'is_suspended' => false,
        ]);
        $admin->roles()->attach(Role::query()->firstOrCreate(['name' => 'admin']));
        $application->user->update(['email_verified_at' => now(), 'phone_verified_at' => now()]);

        $this->actingAs($admin)
            ->post(route('superadmin.applications.approve', $application))
            ->assertRedirect();

        $this->assertSame('approved', $application->fresh()->status);
        $this->assertSame('active', $application->user->fresh()->status);
        $this->assertSame('approved', $application->user->sellers()->firstOrFail()->status);
        $this->assertSame(
            ['approved'],
            $application->sellerDocuments()->distinct()->pluck('verification_status')->all()
        );
        $this->assertSame(
            [$admin->id],
            $application->sellerDocuments()->distinct()->pluck('reviewed_by')->all()
        );
    }

    private function category(): Category
    {
        return Category::query()->create([
            'name' => 'Apparel',
            'slug' => 'apparel',
            'position' => 1,
            'is_active' => true,
        ]);
    }

    private function fakePsgc(): void
    {
        Http::fake([
            'https://psgc.cloud/api/v2/regions' => Http::response(['data' => [
                ['code' => '01', 'name' => 'Region A'], ['code' => '13', 'name' => 'NCR-style'],
            ]]),
            'https://psgc.cloud/api/v2/regions/01/provinces' => Http::response(['data' => [['code' => '0101', 'name' => 'Province A']]]),
            'https://psgc.cloud/api/v2/provinces/0101/cities-municipalities' => Http::response(['data' => [['code' => '010101', 'name' => 'City A']]]),
            'https://psgc.cloud/api/v2/cities-municipalities/010101/barangays' => Http::response(['data' => [['code' => '010101001', 'name' => 'Barangay A']]]),
            'https://psgc.cloud/api/v2/regions/13/provinces' => Http::response(['data' => []]),
            'https://psgc.cloud/api/v2/regions/13/cities-municipalities' => Http::response(['data' => [['code' => '130001', 'name' => 'NCR City']]]),
            'https://psgc.cloud/api/v2/cities-municipalities/130001/barangays' => Http::response(['data' => [['code' => '130001001', 'name' => 'NCR Barangay']]]),
        ]);
    }

    private function validPayload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Sela',
            'last_name' => 'Merchant',
            'middle_initial' => 'M',
            'sex' => 'Female',
            'birthday' => '1995-05-15',
            'phone' => '09170000001',
            'email' => 'new-seller@example.com',
            'region' => '01',
            'province' => '0101',
            'municipality' => '010101',
            'barangay' => '010101001',
            'address' => '10 Market Street',
            'postal_code' => '4009',
            'business_name' => 'Sela Store',
            'category_id' => $category->id,
            'seller_type' => 'business',
            'tin' => '123-456-789-000',
            'valid_id' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
            'password' => 'SellerPass1!',
            'password_confirmation' => 'SellerPass1!',
            'terms' => '1',
            'status' => 'approved',
        ], $overrides);
    }
}

class SellerOnboardingSmsSender implements SmsSender
{
    public array $messages = [];

    public function send(string $phone, string $message): void
    {
        $this->messages[] = compact('phone', 'message');
    }
}
