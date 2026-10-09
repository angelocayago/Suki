<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SellerRegistrationAddressValidationTest extends TestCase
{
    public function test_registration_form_includes_region_and_postal_code_fields(): void
    {
        $response = $this->get(route('seller.register'));

        $response->assertOk();
        $response->assertSee('name="region"', false);
        $response->assertSee('name="postal_code"', false);
        $response->assertSee('Select region');
        $response->assertSee('0000');
    }

    public function test_region_and_postal_code_are_required(): void
    {
        $this->post(route('seller.register.submit'), [])
            ->assertSessionHasErrors(['region', 'postal_code']);
    }

    public function test_postal_code_accepts_only_four_digits(): void
    {
        foreach (['112', '11210', '11A1'] as $postalCode) {
            $this->post(route('seller.register.submit'), [
                'postal_code' => $postalCode,
            ])->assertSessionHasErrors('postal_code');
        }

        $this->post(route('seller.register.submit'), [
            'postal_code' => '1121',
        ])->assertSessionDoesntHaveErrors('postal_code');
    }

    public function test_psgc_proxy_repairs_upstream_mojibake_without_changing_location_codes(): void
    {
        Http::fake([
            'https://psgc.cloud/api/v2/provinces/0403400000/cities-municipalities' => Http::response([
                'data' => [
                    [
                        'code' => '0403403000',
                        'name' => 'City of BiÃ±an',
                    ],
                    [
                        'code' => '0403411000',
                        'name' => 'Los BaÃ±os',
                    ],
                    [
                        'code' => '0403401000',
                        'name' => 'Alaminos',
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson(
            '/api/psgc/provinces/0403400000/cities-municipalities'
        );

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('0.code', '0403403000')
            ->assertJsonPath('0.name', 'City of Biñan')
            ->assertJsonPath('1.code', '0403411000')
            ->assertJsonPath('1.name', 'Los Baños')
            ->assertJsonPath('2.code', '0403401000')
            ->assertJsonPath('2.name', 'Alaminos');
    }

    public function test_psgc_proxy_preserves_already_correct_unicode_names(): void
    {
        Http::fake([
            'https://psgc.cloud/api/v2/provinces/0403400000/cities-municipalities' => Http::response([
                'data' => [
                    [
                        'code' => '0403403000',
                        'name' => 'City of Biñan',
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/psgc/provinces/0403400000/cities-municipalities')
            ->assertOk()
            ->assertJsonPath('0.code', '0403403000')
            ->assertJsonPath('0.name', 'City of Biñan');
    }
}
