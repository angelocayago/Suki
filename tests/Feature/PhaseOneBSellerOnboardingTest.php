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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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
        $this->assertTrue(Hash::check('seller-password', $user->password));
        $this->assertTrue($user->roles->contains(fn (Role $role) => $role->name === 'seller'));

        $this->assertSame('pending', $seller->status);
        $this->assertSame($category->id, $seller->category_id);
        $this->assertSame('business', $seller->seller_type);
        $this->assertSame('123-456-789-000', $seller->tin);
        $this->assertTrue($seller->allowedCategories->contains($category));
        $this->assertSame($seller->pickup_address_id, $user->addresses()->where('is_default', true)->value('id'));

        $this->assertSame('pending', $application->status);
        $this->assertSame($seller->id, $application->application_data['seller_id']);
        $this->assertCount(2, $application->sellerDocuments);

        $this->assertDatabaseHas('account_verifications', [
            'user_id' => $user->id,
            'channel' => 'unified',
            'verified_at' => null,
        ]);
        $this->assertDatabaseMissing('account_verifications', ['user_id' => $user->id, 'channel' => 'email']);
        $this->assertDatabaseMissing('account_verifications', ['user_id' => $user->id, 'channel' => 'sms']);
        Mail::assertSent(AccountVerificationOtpMail::class, 1);
        $this->assertCount(1, $this->sms->messages);

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

    public function test_business_permit_is_required_for_business_but_optional_for_individual_seller(): void
    {
        Storage::fake('seller_documents');
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
            ->assertSee('Submit for Admin Review')
            ->assertSee('Maximum 5 MB')
            ->assertSee('Your account will remain pending')
            ->assertDontSee('Inactive Category');
    }

    public function test_only_an_active_admin_can_view_a_private_seller_document(): void
    {
        Storage::fake('seller_documents');
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

    private function validPayload(Category $category): array
    {
        return [
            'first_name' => 'Sela',
            'last_name' => 'Merchant',
            'middle_initial' => 'M',
            'sex' => 'Female',
            'birthday' => '1995-05-15',
            'phone' => '09170000001',
            'email' => 'new-seller@example.com',
            'province' => 'Laguna',
            'municipality' => 'Santa Cruz',
            'barangay' => 'Poblacion',
            'address' => '10 Market Street',
            'postal_code' => '4009',
            'business_name' => 'Sela Store',
            'category_id' => $category->id,
            'seller_type' => 'business',
            'tin' => '123-456-789-000',
            'valid_id' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
            'password' => 'seller-password',
            'password_confirmation' => 'seller-password',
            'terms' => '1',
            'status' => 'approved',
        ];
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
