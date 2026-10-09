<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Tests\TestCase;

class SellerDocumentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_requirements_follow_the_selected_seller_type(): void
    {
        $requirements = [
            'Individual' => [
                'government_id',
            ],
            'Sole Proprietorship' => [
                'government_id',
                'dti_certificate',
                'bir_form_2303',
            ],
            'Corporation' => [
                'government_id',
                'sec_certificate',
                'bir_form_2303',
            ],
        ];

        foreach ($requirements as $sellerType => $requiredDocuments) {
            $response = $this->from(route('seller.register'))
                ->post(route('seller.register.submit'), [
                    'seller_type' => $sellerType,
                ]);

            $response->assertSessionHasErrors($requiredDocuments);

            $irrelevantDocuments = array_diff(
                [
                    'dti_certificate',
                    'bir_form_2303',
                    'sec_certificate',
                ],
                $requiredDocuments
            );

            $response->assertSessionDoesntHaveErrors($irrelevantDocuments);
            $response->assertSessionDoesntHaveErrors('business_permit');
        }
    }

    public function test_missing_required_document_errors_render_beside_visible_upload_fields(): void
    {
        foreach ([
            'Sole Proprietorship' => [
                'DTI Certificate is required for the selected seller type.',
                'BIR Form 2303 is required for the selected seller type.',
            ],
            'Corporation' => [
                'SEC Certificate is required for the selected seller type.',
                'BIR Form 2303 is required for the selected seller type.',
            ],
        ] as $sellerType => $messages) {
            $response = $this->followingRedirects()
                ->from(route('seller.register'))
                ->post(route('seller.register.submit'), [
                    'seller_type' => $sellerType,
                ]);

            foreach ($messages as $message) {
                $response->assertSeeText($message);
            }

            $response->assertSee(
                'data-seller-document-field="government_id"',
                false
            );
            $response->assertSee('aria-invalid="true"', false);

            if ($sellerType === 'Sole Proprietorship') {
                $response->assertSee(
                    'data-seller-document-field="dti_certificate"',
                    false
                );
            } else {
                $response->assertSee(
                    'data-seller-document-field="sec_certificate"',
                    false
                );
            }
        }
    }

    public function test_local_and_international_mobile_numbers_are_stored_in_local_format(): void
    {
        Storage::fake('seller_documents');
        $this->fakeAddressApi();

        foreach ([
            '09189876543',
            '+639189876544',
        ] as $index => $phone) {
            $registration = $this->validRegistrationData('Individual');
            $registration['email'] = 'seller' . $index . '@example.test';
            $registration['phone'] = $phone;
            $registration['tin'] = '123-456-789-01' . $index;

            $this->post(route('seller.register.submit'), [
                ...$registration,
                'government_id' => $this->fakePdf('identity.pdf'),
            ])->assertRedirect(route('seller.dashboard'));
        }

        $this->assertDatabaseHas('users', ['phone' => '09189876543']);
        $this->assertDatabaseHas('users', ['phone' => '09189876544']);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('addresses', 2);
        $this->assertDatabaseHas('addresses', ['phone' => '09189876544']);
    }

    public function test_duplicate_local_mobile_number_is_rejected_when_submitted_in_international_format(): void
    {
        User::factory()->create([
            'phone' => '09189876543',
        ]);
        User::factory()->create([
            'phone' => '+639189876544',
        ]);

        $registration = $this->validRegistrationData('Individual');
        $registration['phone'] = '+639189876543';

        $this->from(route('seller.register'))
            ->post(route('seller.register.submit'), $registration)
            ->assertSessionHasErrors('phone')
            ->assertSessionHasInput('phone', '+639189876543');

        $registration['email'] = 'another-seller@example.test';
        $registration['phone'] = '09189876544';

        $this->from(route('seller.register'))
            ->post(route('seller.register.submit'), $registration)
            ->assertSessionHasErrors('phone')
            ->assertSessionHasInput('phone', '09189876544');

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('sellers', 0);
    }

    public function test_invalid_phone_characters_lengths_and_landlines_are_rejected(): void
    {
        foreach ([
            '0917ABC4567',
            '0917-123-456',
            '12345678901',
            '02-8123-4567',
            '0912345678',
            '+6391898765430',
        ] as $phone) {
            $registration = $this->validRegistrationData('Individual');
            $registration['phone'] = $phone;

            $this->from(route('seller.register'))
                ->post(route('seller.register.submit'), $registration)
                ->assertSessionHasErrors('phone');
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('sellers', 0);
    }

    public function test_seller_registration_accepts_a_birthday_exactly_eighteen_years_ago(): void
    {
        $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));
        Storage::fake('seller_documents');
        $this->fakeAddressApi();

        $registration = $this->validRegistrationData('Individual');
        $registration['birthday'] = '2008-10-10';

        $this->post(route('seller.register.submit'), [
            ...$registration,
            'government_id' => $this->fakePdf('identity.pdf'),
        ])
            ->assertRedirect(route('seller.dashboard'))
            ->assertSessionDoesntHaveErrors('birthday');
    }

    public function test_seller_registration_rejects_underage_and_future_birthdays(): void
    {
        $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));

        foreach ([
            '2008-10-11',
            '2008-11-01',
            '2008-12-31',
            '2026-10-11',
        ] as $birthday) {
            $registration = $this->validRegistrationData('Individual');
            $registration['birthday'] = $birthday;

            $this->from(route('seller.register'))
                ->post(route('seller.register.submit'), $registration)
                ->assertSessionHasErrors('birthday')
                ->assertSessionHasInput('birthday', $birthday);
        }

        $this->travelTo(Carbon::parse('2026-12-10 12:00:00'));
        $registration = $this->validRegistrationData('Individual');
        $registration['birthday'] = '2008-12-11';

        $this->from(route('seller.register'))
            ->post(route('seller.register.submit'), $registration)
            ->assertSessionHasErrors('birthday');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('sellers', 0);
    }

    public function test_birthday_later_in_the_year_is_valid_when_age_is_at_least_eighteen(): void
    {
        $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));

        foreach (['2007-11-01', '2007-12-31'] as $birthday) {
            $registration = $this->validRegistrationData('Individual');
            $registration['birthday'] = $birthday;

            $this->from(route('seller.register'))
                ->post(route('seller.register.submit'), $registration)
                ->assertSessionDoesntHaveErrors('birthday')
                ->assertSessionHasInput('birthday', $birthday);
        }

        $this->travelTo(Carbon::parse('2026-12-10 12:00:00'));

        foreach (['2008-11-01', '2008-12-10'] as $birthday) {
            $registration = $this->validRegistrationData('Individual');
            $registration['birthday'] = $birthday;

            $this->from(route('seller.register'))
                ->post(route('seller.register.submit'), $registration)
                ->assertSessionDoesntHaveErrors('birthday')
                ->assertSessionHasInput('birthday', $birthday);
        }
    }

    public function test_birthday_picker_maximum_date_updates_with_the_current_date(): void
    {
        $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));

        $this->get(route('seller.register'))
            ->assertOk()
            ->assertSee('max="2008-10-10"', false)
            ->assertDontSee('name="age"', false);

        $this->travelTo(Carbon::parse('2026-10-11 12:00:00'));

        $this->get(route('seller.register'))
            ->assertOk()
            ->assertSee('max="2008-10-11"', false);

        $this->travelTo(Carbon::parse('2026-12-10 12:00:00'));

        $this->get(route('seller.register'))
            ->assertOk()
            ->assertSee('max="2008-12-10"', false);
    }

    public function test_birthday_cutoff_uses_philippine_date_when_utc_is_still_the_previous_day(): void
    {
        $this->travelTo(Carbon::parse('2026-10-09 19:30:00', 'UTC'));

        $this->get(route('seller.register'))
            ->assertOk()
            ->assertSee('max="2008-10-10"', false);

        $registration = $this->validRegistrationData('Individual');
        $registration['birthday'] = '2008-10-11';

        $this->from(route('seller.register'))
            ->post(route('seller.register.submit'), $registration)
            ->assertSessionHasErrors('birthday');

        Storage::fake('seller_documents');
        $this->fakeAddressApi();
        $registration['birthday'] = '2008-10-10';

        $this->post(route('seller.register.submit'), [
            ...$registration,
            'government_id' => $this->fakePdf('identity.pdf'),
        ])
            ->assertRedirect(route('seller.dashboard'))
            ->assertSessionDoesntHaveErrors('birthday');
    }

    public function test_document_upload_accepts_supported_mimes_and_files_up_to_five_mb(): void
    {
        foreach ([
            UploadedFile::fake()->createWithContent(
                'identity.jpg',
                file_get_contents(public_path('images/suki-rider.jpg'))
            ),
            UploadedFile::fake()->createWithContent(
                'identity.png',
                file_get_contents(public_path('images/logo.png'))
            ),
            $this->fakePdf('identity.pdf', 5 * 1024),
        ] as $file) {
            $this->from(route('seller.register'))
                ->post(route('seller.register.submit'), [
                    'seller_type' => 'Individual',
                    'government_id' => $file,
                ])
                ->assertSessionDoesntHaveErrors('government_id');
        }
    }

    public function test_document_upload_rejects_unsupported_mimes_and_files_over_five_mb(): void
    {
        foreach (['identity.gif', 'identity.docx', 'identity.exe'] as $fileName) {
            $this->from(route('seller.register'))
                ->post(route('seller.register.submit'), [
                    'seller_type' => 'Individual',
                    'government_id' => UploadedFile::fake()->create($fileName, 4),
                ])
                ->assertSessionHasErrors('government_id');
        }

        $this->from(route('seller.register'))
            ->post(route('seller.register.submit'), [
                'seller_type' => 'Individual',
                'government_id' => $this->fakePdf('identity.pdf', 5 * 1024 + 1),
            ])
            ->assertSessionHasErrors('government_id');
    }

    public function test_documents_not_required_for_a_seller_type_are_rejected(): void
    {
        Storage::fake('seller_documents');

        $this->from(route('seller.register'))
            ->post(route('seller.register.submit'), [
                'seller_type' => 'Individual',
                'dti_certificate' => UploadedFile::fake()->createWithContent(
                    'dti.png',
                    file_get_contents(public_path('images/logo.png'))
                ),
            ])
            ->assertSessionHasErrors('dti_certificate');

        $this->assertSame([], Storage::disk('seller_documents')->allFiles());
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('sellers', 0);
    }

    public function test_registration_stores_required_documents_privately_and_links_them_to_seller(): void
    {
        Storage::fake('seller_documents');
        Storage::fake('public');
        $this->fakeAddressApi();

        $response = $this->post(route('seller.register.submit'), [
            ...$this->validRegistrationData('Sole Proprietorship'),
            'government_id' => UploadedFile::fake()->createWithContent(
                'identity.png',
                file_get_contents(public_path('images/logo.png'))
            ),
            'dti_certificate' => $this->fakePdf('dti.pdf'),
            'bir_form_2303' => $this->fakePdf('bir.pdf'),
        ]);

        $response->assertRedirect(route('seller.dashboard'));

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('sellers', 1);
        $this->assertDatabaseCount('addresses', 1);
        $this->assertDatabaseCount('seller_documents', 3);
        $this->assertDatabaseHas('seller_documents', [
            'document_type' => 'government_id',
        ]);
        $this->assertDatabaseHas('seller_documents', [
            'document_type' => 'dti_certificate',
        ]);
        $this->assertDatabaseHas('seller_documents', [
            'document_type' => 'bir_form_2303',
        ]);

        $seller = \App\Models\Seller::query()->firstOrFail();
        $this->assertCount(3, $seller->documents);

        foreach ($seller->documents as $document) {
            Storage::disk('seller_documents')->assertExists($document->path);
            Storage::disk('public')->assertMissing($document->path);
        }

        $this->assertSame(
            'registration/' . $seller->id,
            dirname($seller->documents->first()->path)
        );
        $this->assertFalse(config('filesystems.disks.seller_documents.serve'));
        $this->assertNull(session('seller_profile.documents.business_permit'));
    }

    public function test_optional_business_permit_is_saved_when_provided(): void
    {
        Storage::fake('seller_documents');
        Storage::fake('public');
        $this->fakeAddressApi();

        $this->post(route('seller.register.submit'), [
            ...$this->validRegistrationData('Individual'),
            'government_id' => $this->fakePdf('identity.pdf'),
            'business_permit' => $this->fakePdf('permit.pdf'),
        ])->assertRedirect(route('seller.dashboard'));

        $this->assertDatabaseCount('seller_documents', 2);
        $this->assertDatabaseHas('seller_documents', [
            'document_type' => 'business_permit',
        ]);
    }

    public function test_registration_rolls_back_database_rows_and_private_files_when_document_record_creation_fails(): void
    {
        Storage::fake('seller_documents');
        $this->fakeAddressApi();
        \Illuminate\Support\Facades\DB::statement(
            "CREATE TRIGGER reject_seller_documents BEFORE INSERT ON seller_documents
            BEGIN SELECT RAISE(ABORT, 'simulated document persistence failure'); END;"
        );

        try {
            $this->withoutExceptionHandling()->post(route('seller.register.submit'), [
                ...$this->validRegistrationData('Individual'),
                'government_id' => $this->fakePdf('identity.pdf'),
            ]);
            $this->fail('Expected the simulated document database failure.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('sellers', 0);
            $this->assertDatabaseCount('addresses', 0);
            $this->assertDatabaseCount('seller_documents', 0);
            $this->assertSame([], Storage::disk('seller_documents')->allFiles());
        }
    }

    private function validRegistrationData(string $sellerType): array
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'Seller',
            'sex' => 'Female',
            'birthday' => '1990-01-01',
            'email' => 'seller@example.test',
            'phone' => '09123456789',
            'region' => '0100000000',
            'province' => '0101000000',
            'municipality' => '0101010000',
            'barangay' => '0101010001',
            'address' => 'Test address',
            'postal_code' => '0123',
            'business_name' => 'Test shop',
            'business_category' => 'Pet Supplies',
            'seller_type' => $sellerType,
            'tin' => '123-456-789-012',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ];
    }

    private function fakeAddressApi(): void
    {
        Http::fake([
            'https://psgc.cloud/api/v2/regions' => Http::response([
                'data' => [
                    ['code' => '0100000000', 'name' => 'Region I'],
                ],
            ]),
            'https://psgc.cloud/api/v2/regions/0100000000/provinces' => Http::response([
                'data' => [
                    ['code' => '0101000000', 'name' => 'Province'],
                ],
            ]),
            'https://psgc.cloud/api/v2/provinces/0101000000/cities-municipalities' => Http::response([
                'data' => [
                    ['code' => '0101010000', 'name' => 'City'],
                ],
            ]),
            'https://psgc.cloud/api/v2/cities-municipalities/0101010000/barangays' => Http::response([
                'data' => [
                    ['code' => '0101010001', 'name' => 'Barangay'],
                ],
            ]),
        ]);
    }

    private function fakePdf(string $name, int $sizeInKilobytes = 1): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            "%PDF-1.4\n" . str_repeat('0', max(0, $sizeInKilobytes * 1024 - 9))
        );
    }
}
