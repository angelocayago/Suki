<?php

namespace Tests\Feature;

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
}
