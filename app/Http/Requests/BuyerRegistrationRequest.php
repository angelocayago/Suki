<?php

namespace App\Http\Requests;

use App\Support\IdentityNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class BuyerRegistrationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => IdentityNormalizer::email($this->input('email')),
            'phone' => IdentityNormalizer::philippinePhone($this->input('phone')),
        ]);
    }

    public function rules(): array
    {
        $resumeUserId = data_get($this->session()->get('google_registration'), 'intent') === 'buyer'
            ? data_get($this->session()->get('google_registration'), 'resume_user_id')
            : null;

        return [
            'last_name' => ['required', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \'\-][\pL]+)*$/u'],
            'first_name' => ['required', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \'\-][\pL]+)*$/u'],
            'middle_initial' => ['nullable', 'string', 'regex:/^\pL$/u'],
            'sex' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users')->ignore($resumeUserId)->where(fn ($q) => $q->whereRaw('LOWER(email) = ?', [$this->input('email')]))],
            'phone' => ['required', 'regex:/^09\d{9}$/', Rule::unique('users', 'phone')->ignore($resumeUserId)],
            'birthday' => ['required', 'date', 'before:today'],
            'region' => ['required', 'regex:/^\d+$/'],
            'province' => ['nullable', 'regex:/^\d+$/'],
            'municipality' => ['required', 'regex:/^\d+$/'],
            'barangay' => ['required', 'regex:/^\d+$/'],
            'street_address' => ['required', 'string', 'max:255'],
            'government_id' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            'password' => [
                'required',
                'string',
                Password::min(8)->mixedCase()->numbers()->symbols(),
                'confirmed',
            ],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.regex' => 'Please enter a valid name.',
            'last_name.regex' => 'Please enter a valid name.',
            'middle_initial.regex' => 'Middle initial must be one letter.',
            'phone.regex' => 'Mobile number must be 11 digits and start with 09.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.mixed' => 'Password must include uppercase and lowercase letters.',
            'password.numbers' => 'Password must include at least one number.',
            'password.symbols' => 'Password must include at least one special character.',
            'password.confirmed' => 'Password confirmation must match the password.',
        ];
    }
}
