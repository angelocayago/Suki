<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsRegistrationEmailValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_trimmed_before_validation_and_old_input_is_normalized(): void
    {
        $response = $this->from(route('logistics.register'))
            ->post(route('logistics.register.submit'), [
                'email' => '  logistics.test01@example.com  ',
            ]);

        $response
            ->assertSessionDoesntHaveErrors('email')
            ->assertSessionHas('_old_input.email', 'logistics.test01@example.com');
    }

    public function test_invalid_email_formats_are_rejected(): void
    {
        foreach ([
            'operations.bilishatidexample.com',
            'operations.bilishatid@',
            '@example.com',
            'operations bilishatid@example.com',
        ] as $email) {
            $this->from(route('logistics.register'))
                ->post(route('logistics.register.submit'), [
                    'email' => $email,
                ])
                ->assertSessionHasErrors('email');
        }
    }

    public function test_existing_email_is_rejected_case_insensitively(): void
    {
        User::factory()->create([
            'email' => 'operations@bilishatid.example.com',
        ]);

        $response = $this->from(route('logistics.register'))
            ->post(route('logistics.register.submit'), [
                'email' => 'OPERATIONS@BILISHATID.EXAMPLE.COM',
            ]);

        $response->assertSessionHasErrors([
            'email' => 'Email is already registered.',
        ]);
    }
}
