<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsRegistrationCredentialValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_and_international_mobile_numbers_are_accepted_and_normalized(): void
    {
        foreach ([
            '09170000001',
            '+639170000001',
        ] as $mobileNumber) {
            $response = $this->from(route('logistics.register'))
                ->post(route('logistics.register.submit'), [
                    'mobile_number' => $mobileNumber,
                ]);

            $response
                ->assertSessionDoesntHaveErrors('mobile_number')
                ->assertSessionHas('_old_input.mobile_number', '09170000001');
        }
    }

    public function test_invalid_mobile_numbers_are_rejected(): void
    {
        foreach ([
            '0917ABC4567',
            '0917-123-456',
            '12345678901',
            '02-8123-4567',
            '0917000001',
            '+6391700000011',
        ] as $mobileNumber) {
            $this->from(route('logistics.register'))
                ->post(route('logistics.register.submit'), [
                    'mobile_number' => $mobileNumber,
                ])
                ->assertSessionHasErrors('mobile_number');
        }
    }

    public function test_mobile_number_is_required(): void
    {
        $this->from(route('logistics.register'))
            ->post(route('logistics.register.submit'), [
                'mobile_number' => '',
            ])
            ->assertSessionHasErrors([
                'mobile_number' => 'Mobile number is required.',
            ]);
    }

    public function test_local_and_international_duplicates_are_rejected(): void
    {
        User::factory()->create(['phone' => '09285551234']);
        User::factory()->create(['phone' => '+639285551235']);

        foreach ([
            '+639285551234',
            '09285551235',
        ] as $mobileNumber) {
            $this->from(route('logistics.register'))
                ->post(route('logistics.register.submit'), [
                    'mobile_number' => $mobileNumber,
                ])
                ->assertSessionHasErrors([
                    'mobile_number' => 'This mobile number is already registered.',
                ]);
        }
    }

    public function test_password_with_all_required_character_types_is_accepted(): void
    {
        $this->from(route('logistics.register'))
            ->post(route('logistics.register.submit'), [
                'password' => 'Abcdef1!',
                'password_confirmation' => 'Abcdef1!',
            ])
            ->assertSessionDoesntHaveErrors([
                'password',
                'password_confirmation',
            ]);
    }

    public function test_password_requires_uppercase_lowercase_number_and_special_character(): void
    {
        foreach ([
            'hatid@2026',
            'HATID@2026',
            'Hatid@abcd',
            'Hatid2026',
            'Abcdef1é',
        ] as $password) {
            $this->from(route('logistics.register'))
                ->post(route('logistics.register.submit'), [
                    'password' => $password,
                    'password_confirmation' => $password,
                ])
                ->assertSessionHasErrors('password');
        }
    }

    public function test_password_must_be_at_least_eight_characters(): void
    {
        $this->from(route('logistics.register'))
            ->post(route('logistics.register.submit'), [
                'password' => 'Ab1!',
                'password_confirmation' => 'Ab1!',
            ])
            ->assertSessionHasErrors([
                'password' => 'Password must be at least 8 characters.',
            ]);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->from(route('logistics.register'))
            ->post(route('logistics.register.submit'), [
                'password' => 'Hatid@2026',
                'password_confirmation' => 'Hatid@2027',
            ])
            ->assertSessionHasErrors([
                'password_confirmation' => 'Password confirmation must match the password.',
            ]);
    }
}
