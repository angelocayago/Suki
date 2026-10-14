<?php

namespace Tests\Feature;

use App\Mail\BuyerEmailVerificationMail;
use App\Models\BuyerEmailVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuyerRegistrationValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_empty_registration_is_rejected_without_creating_an_account(): void
    {
        $response = $this->from(route('register.buyer'))
            ->post(route('register.submit'), []);

        $response->assertSessionHasErrors([
            'last_name',
            'first_name',
            'sex',
            'email',
            'phone',
            'birthday',
            'region',
            'province',
            'municipality',
            'barangay',
            'street_address',
            'government_id',
            'password',
            'password_confirmation',
            'terms',
        ]);

        $formResponse = $this->get(route('register.buyer'));
        foreach ([
            'last_name',
            'first_name',
            'sex',
            'email',
            'phone',
            'birthday',
            'region',
            'province',
            'municipality',
            'barangay',
            'street_address',
            'government_id',
            'password',
            'password_confirmation',
            'terms',
        ] as $field) {
            $formResponse->assertSee('data-field-error="'.$field.'"', false);
        }

        $formResponse->assertSee('novalidate', false)
            ->assertSee('name="region"', false)
            ->assertSee('/api/psgc', false)
            ->assertSee('text-red-600', false);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('buyer_email_verifications', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_names_accept_unicode_letters_and_reject_numbers_or_symbols(): void
    {
        $this->assertBuyerRegistrationSucceeds([
            'first_name' => 'Dela Cruz',
            'last_name' => 'Niño',
        ], [
            'first_name' => 'Dela Cruz',
            'last_name' => 'Niño',
        ]);

        foreach ([
            ['first_name' => 'Maria2'],
            ['first_name' => 'Juan@'],
            ['last_name' => 'Dela Cruz#'],
            ['last_name' => 'Angelo123!'],
        ] as $invalidNames) {
            $this->assertBuyerRegistrationFails($invalidNames, [
                array_key_first($invalidNames),
            ], expectedUserCount: 1);
        }
    }

    public function test_names_accept_two_and_fifty_characters(): void
    {
        $this->assertBuyerRegistrationSucceeds([
            'first_name' => 'Jo',
            'last_name' => 'Li',
        ], [
            'first_name' => 'Jo',
            'last_name' => 'Li',
        ]);

        $fiftyCharacterName = str_repeat('A', 50);

        $this->assertBuyerRegistrationSucceeds([
            'first_name' => $fiftyCharacterName,
            'last_name' => $fiftyCharacterName,
            'email' => 'another-buyer@example.com',
            'phone' => '09171234568',
        ], [
            'first_name' => $fiftyCharacterName,
            'last_name' => $fiftyCharacterName,
        ]);
    }

    public function test_names_reject_one_and_fifty_one_characters_without_creating_an_account(): void
    {
        foreach ([
            ['first_name' => 'A'],
            ['last_name' => 'A'],
            ['first_name' => str_repeat('A', 51)],
            ['last_name' => str_repeat('A', 51)],
        ] as $invalidName) {
            $this->assertBuyerRegistrationFails($invalidName, [
                array_key_first($invalidName),
            ]);
        }
    }

    public function test_birthday_must_be_a_valid_date_for_a_buyer_who_is_at_least_eighteen(): void
    {
        $this->assertBuyerRegistrationFails([
            'birthday' => Carbon::today('Asia/Manila')
                ->subYears(18)
                ->addDay()
                ->toDateString(),
        ], ['birthday']);

        $this->assertBuyerRegistrationFails([
            'birthday' => Carbon::today('Asia/Manila')
                ->addDay()
                ->toDateString(),
        ], ['birthday']);

        $this->assertBuyerRegistrationFails([
            'birthday' => '2025-02-30',
        ], ['birthday']);
    }

    public function test_buyer_who_is_exactly_eighteen_is_accepted(): void
    {
        $this->assertBuyerRegistrationSucceeds([
            'birthday' => Carbon::today('Asia/Manila')
                ->subYears(18)
                ->toDateString(),
        ]);
    }

    public function test_email_format_is_validated_and_email_is_trimmed_and_lowercased(): void
    {
        foreach ([
            'juanexample.com',
            'juan@',
            '@example.com',
            'juan example@email.com',
            'aso@gmaicom',
            'aso@gmailcom',
        ] as $invalidEmail) {
            $this->assertBuyerRegistrationFails([
                'email' => $invalidEmail,
            ], ['email']);
        }

        $this->assertBuyerRegistrationSucceeds([
            'email' => 'buyer.test01@example.com',
            'phone' => '09171234568',
        ], [
            'email' => 'buyer.test01@example.com',
        ]);

        $this->assertBuyerRegistrationSucceeds([
            'email' => '  BUYER@Example.COM  ',
            'phone' => '09171234569',
        ], [
            'email' => 'buyer@example.com',
        ]);
    }

    public function test_email_duplicates_are_case_insensitive(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $this->assertBuyerRegistrationFails([
            'email' => 'EXISTING@EXAMPLE.COM',
        ], ['email'], expectedUserCount: 1);
        $this->assertDatabaseCount('buyer_email_verifications', 0);

        User::factory()->create([
            'email' => 'ANOTHER@EXAMPLE.COM',
        ]);

        $this->assertBuyerRegistrationFails([
            'email' => 'another@example.com',
        ], ['email'], expectedUserCount: 2);
        $this->assertDatabaseCount('buyer_email_verifications', 0);
    }

    public function test_philippine_mobile_numbers_are_validated_normalized_and_deduplicated(): void
    {
        $this->assertBuyerRegistrationSucceeds([
            'phone' => '+639171234567',
        ], [
            'phone' => '09171234567',
        ]);

        User::factory()->create([
            'phone' => '+639285551234',
        ]);

        $this->assertBuyerRegistrationFails([
            'phone' => '09285551234',
        ], ['phone'], expectedUserCount: 2);

        User::factory()->create([
            'phone' => '09185551234',
        ]);

        $this->assertBuyerRegistrationFails([
            'phone' => '+639185551234',
        ], ['phone'], expectedUserCount: 3);

        foreach ([
            '0917123456',
            '08171234567',
            '0917ABC4567',
            '0917-123-4567',
            '+638171234567',
            '639171234567',
        ] as $phone) {
            $this->assertBuyerRegistrationFails([
                'phone' => $phone,
            ], ['phone'], expectedUserCount: 3);
        }
    }

    public function test_password_requires_all_character_types_and_confirmation_match(): void
    {
        $this->assertBuyerRegistrationSucceeds([
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
        ]);

        foreach ([
            'password@1',
            'PASSWORD@1',
            'Password@',
            'Password12',
            'Ab1!',
        ] as $password) {
            $this->assertBuyerRegistrationFails([
                'password' => $password,
                'password_confirmation' => $password,
            ], ['password'], expectedUserCount: 1);
        }

        $this->assertBuyerRegistrationFails([
            'password' => 'Password@1',
            'password_confirmation' => 'Password@2',
        ], ['password_confirmation'], expectedUserCount: 1);
    }

    public function test_terms_must_be_accepted_and_policy_documents_render_separately(): void
    {
        $this->fakePsgcData();

        $response = $this->from(route('register.buyer'))
            ->post(route('register.submit'), array_replace(
                $this->validBuyerData(),
                ['terms' => ''],
            ));

        $response->assertSessionHasErrors([
            'terms' => "You must agree to SUKI SHOP's Terms & Conditions and Privacy Policy before continuing.",
        ]);
        $this->assertDatabaseCount('users', 0);

        $formResponse = $this->get(route('register.buyer'));
        $formResponse
            ->assertSee('data-open-policy="termsDialog"', false)
            ->assertSee('data-open-policy="privacyDialog"', false)
            ->assertSee('id="termsDialog"', false)
            ->assertSee('SUKI SHOP Terms and Conditions')
            ->assertSee('id="privacyDialog"', false)
            ->assertSee('SUKI SHOP Privacy Policy')
            ->assertSee('Information Collected')
            ->assertSee('User Privacy Rights')
            ->assertSee('showModal()', false)
            ->assertSee('data-field-error="terms"', false);
    }

    public function test_registration_creates_pending_buyer_and_sends_hashed_expiring_otp(): void
    {
        Storage::fake('public');
        $this->fakePsgcData();

        $response = $this->post(route('register.submit'), $this->validBuyerData());

        $response->assertRedirect(route('buyer.verification.show'))
            ->assertSessionHas('buyer_verification_user_id');

        $user = User::query()->where('email', 'buyer@example.com')->firstOrFail();
        $this->assertSame(
            $user->id,
            session('buyer_verification_user_id'),
        );
        $this->assertSame('Maria L. DelaCruz', $user->name);
        $this->assertSame('L.', $user->middle_initial);
        $this->assertSame('Female', $user->sex);
        $this->assertSame('1996-01-01', $user->birthday);
        $this->assertNotNull($user->government_id);
        Storage::disk('public')->assertExists($user->government_id);
        $this->assertSame('buyer', $user->role);
        $this->assertSame('pending', $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'label' => 'Home',
            'is_default' => true,
        ]);

        $verification = $user->emailVerification()->firstOrFail();
        $this->assertSame($user->id, $verification->user->id);

        $this->assertNotSame('123456', $verification->otp_hash);
        $this->assertTrue($verification->expires_at->isFuture());
        $this->assertTrue($verification->expires_at->isAfter(now()->addMinutes(9)));

        Mail::assertSent(BuyerEmailVerificationMail::class, function ($mail) use ($verification) {
            $renderedMail = $mail->render();

            return $mail->hasTo('buyer@example.com')
                && preg_match('/\A[0-9]{6}\z/', $mail->otp) === 1
                && Hash::check($mail->otp, $verification->otp_hash)
                && $mail->expiresInMinutes === config('auth.buyer_otp_expiration_minutes')
                && str_contains($renderedMail, $mail->otp)
                && str_contains($renderedMail, 'expires in');
        });

        $verificationPage = $this->get(route('buyer.verification.show'));
        $verificationPage
            ->assertOk()
            ->assertSee('Verify your email address')
            ->assertSee('buyer@example.com')
            ->assertSee('name="otp"', false)
            ->assertSee('RESEND OTP');

        $registrationPage = $this->get(route('register.buyer'));
        $registrationPage->assertDontSee('name="otp"', false);
    }

    public function test_buyer_otp_rejects_invalid_and_wrong_codes_then_verifies_email(): void
    {
        $this->registerBuyerForVerification();

        foreach ([
            ['otp' => ''],
            ['otp' => '12345'],
            ['otp' => '1234567'],
            ['otp' => '12AB56'],
        ] as $invalidCode) {
            $this->post(route('buyer.verification.verify'), $invalidCode)
                ->assertSessionHasErrors(['otp']);
        }

        $mail = Mail::sent(BuyerEmailVerificationMail::class)->sole();
        $this->post(route('buyer.verification.verify'), [
            'otp' => $mail->otp === '000000' ? '999999' : '000000',
        ])->assertSessionHasErrors(['otp' => 'That verification code is incorrect.']);

        $this->get(route('buyer.verification.show'))
            ->assertSee('That verification code is incorrect.')
            ->assertSee('text-red-600', false);

        $user = User::query()->where('email', 'buyer@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('pending', $user->status);

        $this->post(route('buyer.verification.verify'), ['otp' => $mail->otp])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('pending', $user->status);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('buyer_email_verifications', [
            'user_id' => $user->id,
        ]);
    }

    public function test_buyer_requires_email_verification_before_admin_approval(): void
    {
        $buyer = $this->registerBuyerForVerification();
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.buyers.approve', $buyer))
            ->assertSessionHas('error');
        $this->assertSame('pending', $buyer->fresh()->status);

        $this->actingAs($superadmin)
            ->post(route('superadmin.buyers.status', $buyer), [
                'status' => 'active',
            ])
            ->assertSessionHas('error');
        $this->assertSame('pending', $buyer->fresh()->status);

        $this->actingAs($superadmin)
            ->post(route('superadmin.users.status', $buyer), [
                'status' => 'active',
            ])
            ->assertSessionHas('error');
        $this->assertSame('pending', $buyer->fresh()->status);

        $mail = Mail::sent(BuyerEmailVerificationMail::class)->sole();
        $this->post(route('buyer.verification.verify'), ['otp' => $mail->otp])
            ->assertRedirect(route('login'));

        $buyer->refresh();
        $this->assertNotNull($buyer->email_verified_at);
        $this->assertSame('pending', $buyer->status);

        $this->actingAs($admin)
            ->post(route('admin.buyers.approve', $buyer))
            ->assertSessionHas('success');

        $this->assertSame('active', $buyer->fresh()->status);
    }

    public function test_expired_otp_can_be_resent_and_only_the_latest_code_works(): void
    {
        $this->registerBuyerForVerification();

        $user = User::query()->where('email', 'buyer@example.com')->firstOrFail();
        $originalMail = Mail::sent(BuyerEmailVerificationMail::class)->sole();
        $verification = BuyerEmailVerification::query()
            ->where('user_id', $user->id)
            ->firstOrFail();
        $verification->update([
            'expires_at' => now()->subSecond(),
        ]);

        $this->post(route('buyer.verification.verify'), ['otp' => $originalMail->otp])
            ->assertSessionHasErrors([
                'otp' => 'That verification code is invalid or expired. Request a new code.',
            ]);

        $this->post(route('buyer.verification.resend'))
            ->assertRedirect(route('buyer.verification.show'))
            ->assertSessionHas('status');

        $sentMails = Mail::sent(BuyerEmailVerificationMail::class);
        $this->assertCount(2, $sentMails);
        $this->assertDatabaseCount('buyer_email_verifications', 1);
        $latestMail = $sentMails->last();
        $this->assertNotSame($originalMail->otp, $latestMail->otp);

        $this->post(route('buyer.verification.verify'), ['otp' => $originalMail->otp])
            ->assertSessionHasErrors(['otp']);
        $this->assertNull($user->fresh()->email_verified_at);

        $this->post(route('buyer.verification.verify'), ['otp' => $latestMail->otp])
            ->assertRedirect(route('login'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_unverified_buyer_is_redirected_to_verification_before_login(): void
    {
        $user = $this->registerBuyerForVerification();
        $user->update(['status' => 'active']);

        $this->post(route('login.submit'), [
            'login' => 'buyer@example.com',
            'password' => 'Password@1',
        ])->assertRedirect(route('buyer.verification.show'));

        $this->assertGuest();
        $this->assertNull($user->fresh()->email_verified_at);

        $mail = Mail::sent(BuyerEmailVerificationMail::class)->sole();
        $this->post(route('buyer.verification.verify'), ['otp' => $mail->otp])
            ->assertRedirect(route('login'));

        $this->post(route('login.submit'), [
            'login' => 'buyer@example.com',
            'password' => 'Password@1',
        ])->assertRedirect(route('buyer.home'));

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_buyer_government_id_keeps_backend_file_validation(): void
    {
        $this->fakePsgcData();

        $response = $this->from(route('register.buyer'))
            ->post(route('register.submit'), array_replace(
                $this->validBuyerData(),
                [
                    'government_id' => UploadedFile::fake()->create(
                        'unsupported.gif',
                        10,
                        'image/gif',
                    ),
                ],
            ));

        $response->assertSessionHasErrors(['government_id']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_buyer_government_id_preview_has_remove_and_file_reset_behavior(): void
    {
        $response = $this->get(route('register.buyer'));

        $response
            ->assertSee('id="governmentIdPreview"', false)
            ->assertSee('id="governmentIdPreviewName"', false)
            ->assertSee('id="removeGovernmentId"', false)
            ->assertSee('function updateBuyerGovernmentIdPreview(input)', false)
            ->assertSee('function resetBuyerGovernmentIdPreview()', false)
            ->assertSee('URL.createObjectURL(file)', false)
            ->assertSee('URL.revokeObjectURL(preview.src)', false)
            ->assertSee('Government ID may not exceed 2 MB.', false);
    }

    public function test_contact_and_password_errors_render_beside_their_fields(): void
    {
        $this->fakePsgcData();

        $response = $this->followingRedirects()
            ->from(route('register.buyer'))
            ->post(route('register.submit'), array_replace(
                $this->validBuyerData(),
                [
                    'email' => 'invalid email',
                    'phone' => '0917ABC4567',
                    'password' => 'mamimili@2026',
                    'password_confirmation' => 'Mamimili@2027',
                ],
            ));

        $response
            ->assertSee('Enter a valid email address.')
            ->assertSee('Enter a valid Philippine mobile number in local or +63 format.')
            ->assertSee('Password must include an uppercase letter, a lowercase letter, a number, and a special character.')
            ->assertSee('Password confirmation must match the password.')
            ->assertSee('data-field-error="email"', false)
            ->assertSee('data-field-error="phone"', false)
            ->assertSee('data-field-error="password"', false)
            ->assertSee('data-field-error="password_confirmation"', false)
            ->assertSee('value="invalid email"', false)
            ->assertSee('value="0917ABC4567"', false)
            ->assertSee('aria-invalid="true"', false);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_form_keeps_showing_validation_errors(): void
    {
        $response = $this->followingRedirects()
            ->from(route('register.buyer'))
            ->post(route('register.submit'), array_replace(
                $this->validBuyerData(),
                [
                    'first_name' => 'Maria2',
                    'last_name' => 'Dela Cruz#',
                ],
            ));

        $response
            ->assertSee('First name may contain letters only, with spaces allowed between names.')
            ->assertSee('Last name may contain letters only, with spaces allowed between names.')
            ->assertSee('name="first_name"', false)
            ->assertSee('value="Maria2"', false)
            ->assertSee('data-field-error="first_name"', false)
            ->assertSee('data-field-error="last_name"', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_registration_form_filters_name_input_before_submission(): void
    {
        $response = $this->get(route('register.buyer'));

        $response
            ->assertSee('beforeinput', false)
            ->assertSee('function filterNameInsertion(input, event)', false)
            ->assertSee('function filterNameInput(input)', false)
            ->assertSee('const namePattern = /^\\p{L}+(?: \\p{L}+)*$/u;', false)
            ->assertSee('function allowPhoneInsertion(input, event)', false)
            ->assertSee('function filterPhoneInput(input)', false)
            ->assertSee('maxlength="13"', false)
            ->assertSee('maxlength="255"', false);
    }

    public function test_initial_registration_form_does_not_show_validation_errors(): void
    {
        $response = $this->get(route('register.buyer'));

        $response
            ->assertDontSee('Please correct the highlighted fields below.')
            ->assertDontSee('First name is required.')
            ->assertDontSee('Last name is required.')
            ->assertSee('data-field-error="first_name" class="mt-1 text-xs text-red-600 hidden"', false)
            ->assertSee('data-field-error="last_name" class="mt-1 text-xs text-red-600 hidden"', false);
    }

    public function test_registration_form_sections_follow_the_existing_field_order(): void
    {
        $response = $this->get(route('register.buyer'));
        $content = $response->getContent();

        $sectionPositions = [
            strpos($content, 'Personal Information'),
            strpos($content, 'Contact Information'),
            strpos($content, 'Address Information'),
            strpos($content, 'Account Information'),
        ];

        foreach ($sectionPositions as $sectionPosition) {
            $this->assertNotFalse($sectionPosition);
        }

        $this->assertLessThan($sectionPositions[1], $sectionPositions[0]);
        $this->assertLessThan($sectionPositions[2], $sectionPositions[1]);
        $this->assertLessThan($sectionPositions[3], $sectionPositions[2]);

        foreach (['01', '02', '03', '04'] as $sectionNumber) {
            $response->assertSee($sectionNumber);
        }
    }

    public function test_address_selection_must_follow_the_psgc_hierarchy(): void
    {
        $this->assertBuyerRegistrationFails([
            'province' => '0199000000',
        ], ['province']);
    }

    public function test_province_is_not_required_for_regions_without_provinces(): void
    {
        Storage::fake('public');
        $this->fakePsgcData(provinceLessRegion: true);

        $response = $this->from(route('register.buyer'))
            ->post(route('register.submit'), array_replace(
                $this->validBuyerData(),
                [
                    'region' => '1300000000',
                    'province' => '',
                    'municipality' => '1300100000',
                    'barangay' => '1300100001',
                ],
            ));

        $response
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('buyer.verification.show'));

        $user = User::query()->latest('id')->firstOrFail();
        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'region' => 'National Capital Region',
            'province' => null,
            'city' => 'Test City',
            'barangay' => 'Test Barangay',
        ]);
    }

    private function assertBuyerRegistrationSucceeds(
        array $overrides = [],
        array $expectedAttributes = [],
    ): void {
        Storage::fake('public');
        $this->fakePsgcData();

        $response = $this->from(route('register.buyer'))
            ->post(route('register.submit'), array_replace(
                $this->validBuyerData(),
                $overrides,
            ));

        $response
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('buyer.verification.show'));

        $user = User::query()->latest('id')->firstOrFail();

        foreach ($expectedAttributes as $attribute => $expectedValue) {
            $this->assertSame($expectedValue, $user->{$attribute});
        }

        if (isset($expectedAttributes['phone'])) {
            $this->assertDatabaseHas('addresses', [
                'user_id' => $user->id,
                'phone' => $expectedAttributes['phone'],
            ]);
        }

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'region' => 'Region I',
            'province' => 'Test Province',
            'city' => 'Test City',
            'barangay' => 'Test Barangay',
            'line1' => '123 Test Street',
        ]);
    }

    private function assertBuyerRegistrationFails(
        array $overrides,
        array $errorFields,
        int $expectedUserCount = 0,
    ): void {
        Storage::fake('public');
        $this->fakePsgcData();

        $response = $this->from(route('register.buyer'))
            ->post(route('register.submit'), array_replace(
                $this->validBuyerData(),
                $overrides,
            ));

        $response->assertSessionHasErrors($errorFields);
        $this->assertDatabaseCount('users', $expectedUserCount);
        $this->assertSame([], Storage::disk('public')->allFiles('government-ids'));
    }

    private function validBuyerData(): array
    {
        return [
            'last_name' => 'DelaCruz',
            'first_name' => 'Maria',
            'middle_initial' => 'L.',
            'sex' => 'Female',
            'email' => 'buyer@example.com',
            'phone' => '09171234567',
            'birthday' => '1996-01-01',
            'region' => '0100000000',
            'province' => '0101000000',
            'municipality' => '0101010000',
            'barangay' => '0101010001',
            'street_address' => '123 Test Street',
            'government_id' => UploadedFile::fake()->create(
                'government-id.pdf',
                10,
                'application/pdf',
            ),
            'password' => 'Password@1',
            'password_confirmation' => 'Password@1',
            'terms' => '1',
        ];
    }

    private function fakePsgcData(bool $provinceLessRegion = false): void
    {
        if ($provinceLessRegion) {
            Http::fake([
                'https://psgc.cloud/api/v2/regions' => Http::response([
                    'data' => [
                        ['code' => '1300000000', 'name' => 'National Capital Region'],
                    ],
                ]),
                'https://psgc.cloud/api/v2/regions/1300000000/provinces' => Http::response([
                    'data' => [],
                ]),
                'https://psgc.cloud/api/v2/regions/1300000000/cities-municipalities' => Http::response([
                    'data' => [
                        ['code' => '1300100000', 'name' => 'Test City'],
                    ],
                ]),
                'https://psgc.cloud/api/v2/cities-municipalities/1300100000/barangays' => Http::response([
                    'data' => [
                        ['code' => '1300100001', 'name' => 'Test Barangay'],
                    ],
                ]),
            ]);

            return;
        }

        Http::fake([
            'https://psgc.cloud/api/v2/regions' => Http::response([
                'data' => [
                    ['code' => '0100000000', 'name' => 'Region I'],
                ],
            ]),
            'https://psgc.cloud/api/v2/regions/0100000000/provinces' => Http::response([
                'data' => [
                    ['code' => '0101000000', 'name' => 'Test Province'],
                ],
            ]),
            'https://psgc.cloud/api/v2/provinces/0101000000/cities-municipalities' => Http::response([
                'data' => [
                    ['code' => '0101010000', 'name' => 'Test City'],
                ],
            ]),
            'https://psgc.cloud/api/v2/cities-municipalities/0101010000/barangays' => Http::response([
                'data' => [
                    ['code' => '0101010001', 'name' => 'Test Barangay'],
                ],
            ]),
        ]);
    }

    private function registerBuyerForVerification(): User
    {
        Storage::fake('public');
        $this->fakePsgcData();

        $this->post(route('register.submit'), $this->validBuyerData())
            ->assertRedirect(route('buyer.verification.show'));

        return User::query()->where('email', 'buyer@example.com')->firstOrFail();
    }
}
