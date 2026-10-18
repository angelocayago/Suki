<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BuyerPhilippineAddressTest extends TestCase
{
    use RefreshDatabase;

    private function fakePsgc(array $overrides = []): void
    {
        Http::fake(array_merge([
            'https://psgc.cloud/api/v2/regions' => Http::response(['data'=>[['code'=>'01','name'=>'Region A'],['code'=>'13','name'=>'NCR-style']]]),
            'https://psgc.cloud/api/v2/regions/01/provinces' => Http::response(['data'=>[['code'=>'0101','name'=>'Province A']]]),
            'https://psgc.cloud/api/v2/provinces/0101/cities-municipalities' => Http::response(['data'=>[['code'=>'010101','name'=>'City A']]]),
            'https://psgc.cloud/api/v2/cities-municipalities/010101/barangays' => Http::response(['data'=>[['code'=>'010101001','name'=>'Barangay A']]]),
            'https://psgc.cloud/api/v2/regions/13/provinces' => Http::response(['data'=>[]]),
            'https://psgc.cloud/api/v2/regions/13/cities-municipalities' => Http::response(['data'=>[['code'=>'130001','name'=>'NCR City']]]),
            'https://psgc.cloud/api/v2/cities-municipalities/130001/barangays' => Http::response(['data'=>[['code'=>'130001001','name'=>'NCR Barangay']]]),
        ], $overrides));
    }

    private function buyer(array $address = []): array
    {
        return array_merge(['first_name'=>'Ana','last_name'=>'Buyer','sex'=>'Female','birthday'=>'1995-01-01','phone'=>'09171234567','email'=>'ana@example.com','password'=>'Password1!','password_confirmation'=>'Password1!','terms'=>'1','region'=>'01','province'=>'0101','municipality'=>'010101','barangay'=>'010101001','street_address'=>'1 Main St','government_id'=>UploadedFile::fake()->create('id.jpg', 20, 'image/jpeg')], $address);
    }

    public function test_internal_routes_and_no_province_hierarchy_are_scoped_and_resolved(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->fakePsgc();
        $this->getJson('/address/regions')->assertOk()->assertJsonPath('0.code','01');
        $this->getJson('/address/regions/01/provinces')->assertOk()->assertJsonPath('0.name','Province A');
        $this->getJson('/address/provinces/0101/cities-municipalities')->assertOk()->assertJsonPath('0.code','010101');
        $this->getJson('/address/regions/13/cities-municipalities')->assertOk()->assertJsonPath('0.name','NCR City');
        $this->getJson('/address/cities-municipalities/130001/barangays')->assertOk()->assertJsonPath('0.name','NCR Barangay');
        $this->post(route('register.submit'), $this->buyer(['region'=>'13','province'=>null,'municipality'=>'130001','barangay'=>'130001001']))->assertRedirect(route('verification.contact.show'));
        $this->assertDatabaseHas('addresses',['region'=>'NCR-style','province'=>null,'city'=>'NCR City','barangay'=>'NCR Barangay']);
    }

    public function test_tampered_or_unverifiable_hierarchy_creates_nothing(): void
    {
        $this->fakePsgc();
        $this->post(route('register.submit'), $this->buyer(['region'=>'13','province'=>'0101']))->assertSessionHasErrors('province');
        $this->post(route('register.submit'), $this->buyer(['municipality'=>'130001']))->assertSessionHasErrors('municipality');
        $this->post(route('register.submit'), $this->buyer(['barangay'=>'130001001']))->assertSessionHasErrors('barangay');
        $this->assertDatabaseCount('users',0); $this->assertDatabaseCount('addresses',0);
    }

    public function test_provider_failure_or_malformed_payload_is_safe_and_preserves_google_retry(): void
    {
        $this->fakePsgc(['https://psgc.cloud/api/v2/regions' => Http::response(['unexpected'=>[]], 200)]);
        $identity=['intent'=>'buyer','email'=>'ana@example.com','provider_user_id'=>'subject','issued_at'=>now()->timestamp];
        $this->withSession(['google_registration'=>$identity])->post(route('register.submit'),$this->buyer())->assertSessionHasErrors('region')->assertSessionHas('google_registration');
        $this->assertDatabaseCount('users',0); $this->assertDatabaseCount('addresses',0);
        $this->fakePsgc(['https://psgc.cloud/api/v2/regions' => Http::response([],500)]);
        $this->post(route('register.submit'),$this->buyer())->assertSessionHasErrors('region');
        $this->assertDatabaseCount('users',0);
    }

    public function test_buyer_password_requires_the_domain_complexity_policy_and_matching_confirmation_then_is_hashed(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->fakePsgc();

        foreach ([
            'missing-password@example.com' => '',
            'short-password@example.com' => 'Aa1!abc',
            'lowercase-only@example.com' => 'lowercaseonly',
            'no-number@example.com' => 'PasswordOnly!',
            'no-symbol@example.com' => 'Password123',
            'no-uppercase@example.com' => 'password1!',
            'no-lowercase@example.com' => 'PASSWORD1!',
        ] as $email => $password) {
            $response = $this->post(route('register.submit'), $this->buyer([
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
            ]));

            $this->assertDatabaseMissing('users', ['email' => $email]);
            $response->assertSessionHasErrors('password');
        }

        $this->post(route('register.submit'), $this->buyer([
            'email' => 'mismatch-password@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'different123',
        ]))->assertSessionHasErrors('password');

        $this->post(route('register.submit'), $this->buyer([
            'email' => 'password-success@example.com',
        ]))->assertRedirect(route('verification.contact.show'));

        $user = User::query()->where('email', 'password-success@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('Password1!', $user->password));
        $this->assertNotSame('Password1!', $user->password);
        $this->assertArrayNotHasKey('password_confirmation', $user->getAttributes());
    }

    public function test_google_buyer_onboarding_keeps_the_existing_local_password_requirement(): void
    {
        $this->fakePsgc();
        $identity = [
            'intent' => 'buyer',
            'email' => 'google-password@example.com',
            'provider_user_id' => 'google-password-subject',
            'issued_at' => now()->timestamp,
        ];

        $this->withSession(['google_registration' => $identity])
            ->post(route('register.submit'), $this->buyer([
                'email' => 'google-password@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]))
            ->assertSessionHasErrors('password')
            ->assertSessionHas('google_registration');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('addresses', 0);
    }
}
