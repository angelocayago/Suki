<?php

namespace App\Http\Requests;

use App\Support\IdentityNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogisticsRegistrationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => IdentityNormalizer::email($this->input('email')), 'phone' => IdentityNormalizer::philippinePhone($this->input('phone'))]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required','string','max:100','regex:/^[\pL]+(?:[ \'\-][\pL]+)*$/u'], 'last_name' => ['required','string','max:100','regex:/^[\pL]+(?:[ \'\-][\pL]+)*$/u'],
            'middle_initial' => ['nullable','string','regex:/^\pL$/u'], 'sex' => ['required','string','max:20'],
            'birthday' => ['required','date','before_or_equal:'.now()->subYears(18)->toDateString()],
            'email' => ['required','email:rfc','max:255', Rule::unique('users')->where(fn ($q) => $q->whereRaw('LOWER(email) = ?', [$this->input('email')]))],
            'phone' => ['required','regex:/^09\d{9}$/', Rule::unique('users','phone')],
            'business_name' => ['required','string','max:150', Rule::unique('logistics_providers','name')],
            'address' => ['required','string','max:1000'],
            'government_id' => ['required','file','mimes:jpg,jpeg,png,pdf','max:5120'],
            'business_permit' => ['required','file','mimes:jpg,jpeg,png,pdf','max:5120'],
            'password' => ['required','string','min:8','confirmed'], 'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return ['first_name.regex' => 'Please enter a valid name.', 'last_name.regex' => 'Please enter a valid name.', 'middle_initial.regex' => 'Middle initial must be one letter.', 'phone.regex' => 'Mobile number must be 11 digits and start with 09.'];
    }
}
