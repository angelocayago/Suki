<?php

namespace Tests\Feature;

use Tests\TestCase;

class SellerRegistrationNameValidationTest extends TestCase
{
    public function test_first_name_rejects_numbers_and_symbols(): void
    {
        foreach (['Maria123', 'Juan@', 'John##'] as $name) {
            $this->post(route('seller.register.submit'), [
                'first_name' => $name,
            ])->assertSessionHasErrors('first_name');
        }
    }

    public function test_last_name_rejects_invalid_characters(): void
    {
        foreach (['Peñaranda2', 'D’Souza', 'Smith!'] as $name) {
            $this->post(route('seller.register.submit'), [
                'last_name' => $name,
            ])->assertSessionHasErrors('last_name');
        }
    }

    public function test_names_accept_unicode_letters_and_allowed_punctuation(): void
    {
        foreach ([
            ['first_name' => 'Niña', 'last_name' => "Peñaranda-D'Souza"],
            ['first_name' => 'Maria Clara', 'last_name' => 'Ñuñez'],
        ] as $names) {
            $response = $this->post(route('seller.register.submit'), $names);

            $response->assertSessionDoesntHaveErrors([
                'first_name',
                'last_name',
            ]);
        }
    }

    public function test_middle_initial_is_optional_but_accepts_letters_only(): void
    {
        $this->post(route('seller.register.submit'), [
            'middle_initial' => '',
        ])->assertSessionDoesntHaveErrors('middle_initial');

        foreach (['M3', 'M.', 'M-N', 'ABC'] as $middleInitial) {
            $this->post(route('seller.register.submit'), [
                'middle_initial' => $middleInitial,
            ])->assertSessionHasErrors('middle_initial');
        }

        foreach (['M', 'Ñ', 'AB'] as $middleInitial) {
            $this->post(route('seller.register.submit'), [
                'middle_initial' => $middleInitial,
            ])->assertSessionDoesntHaveErrors('middle_initial');
        }
    }

    public function test_names_must_be_between_two_and_fifty_characters(): void
    {
        $this->post(route('seller.register.submit'), [
            'first_name' => 'A',
            'last_name' => str_repeat('B', 51),
        ])->assertSessionHasErrors([
            'first_name',
            'last_name',
        ]);

        $response = $this->post(route('seller.register.submit'), [
            'first_name' => 'Al',
            'last_name' => str_repeat('B', 50),
        ]);

        $response->assertSessionDoesntHaveErrors([
            'first_name',
            'last_name',
        ]);
    }

    public function test_business_name_must_be_between_three_and_thirty_characters(): void
    {
        foreach ([
            'a' => true,
            'ab' => true,
            'abc' => false,
            str_repeat('B', 30) => false,
            str_repeat('C', 31) => true,
        ] as $businessName => $hasError) {
            $response = $this->post(route('seller.register.submit'), [
                'business_name' => $businessName,
            ]);

            if ($hasError) {
                $response->assertSessionHasErrors('business_name');
            } else {
                $response->assertSessionDoesntHaveErrors('business_name');
            }
        }
    }

    public function test_registration_form_shows_field_errors_and_preserves_old_input(): void
    {
        $response = $this->followingRedirects()
            ->from(route('seller.register'))
            ->post(route('seller.register.submit'), [
                'first_name' => 'Maria123',
                'last_name' => 'A',
                'business_name' => 'ab',
                'middle_initial' => 'M3',
            ]);

        $response
            ->assertSee('name="first_name"', false)
            ->assertSee('value="Maria123"', false)
            ->assertSee('name="last_name"', false)
            ->assertSee('value="A"', false)
            ->assertSee('value="M3"', false)
            ->assertSee('name="business_name"', false)
            ->assertSee('value="ab"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('minlength="2"', false)
            ->assertSee('maxlength="50"', false)
            ->assertSee('pattern="[\p{L}]+(?:[\x20\x27\x2D][\p{L}]+)*"', false)
            ->assertSee('minlength="3"', false)
            ->assertSee('maxlength="30"', false)
            ->assertSee('First name must contain letters only')
            ->assertSee('Last name must be at least 2 characters.')
            ->assertSee('Middle initial may contain letters only.')
            ->assertSee('Business name must be between 3 and 30 characters.');
    }

    public function test_empty_submission_shows_errors_beside_every_missing_required_field(): void
    {
        $requiredFields = [
            'email',
            'password',
            'password_confirmation',
            'first_name',
            'last_name',
            'sex',
            'birthday',
            'phone',
            'region',
            'municipality',
            'barangay',
            'address',
            'postal_code',
            'business_name',
            'business_category',
            'seller_type',
            'tin',
            'terms',
        ];

        $response = $this->from(route('seller.register'))
            ->post(route('seller.register.submit'), []);
        $response->assertSessionHasErrors($requiredFields);

        $response = $this->get(route('seller.register'));

        foreach ([
            ...$requiredFields,
            'province',
            'age',
        ] as $field) {
            $response->assertSee('data-field-error="' . $field . '"', false);
        }

        foreach ([
            'Email is required.',
            'Password is required.',
            'Confirm password is required.',
            'First name is required.',
            'Last name is required.',
            'Select your sex.',
            'Birthday is required.',
            'Contact number is required.',
            'Street address is required.',
            'Postal code is required.',
            'Business name is required.',
            'Select a line of business.',
            'Select a seller type.',
            'TIN is required.',
        ] as $message) {
            $response->assertSee($message);
        }

        $response->assertSee('text-red-600', false)
            ->assertSee('data-field-error="age"', false)
            ->assertSee('novalidate', false);
    }
}
