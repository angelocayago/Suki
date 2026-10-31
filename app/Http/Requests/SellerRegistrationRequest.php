<?php

namespace App\Http\Requests;

use App\Support\IdentityNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SellerRegistrationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => IdentityNormalizer::email($this->input('email')),
            'phone' => IdentityNormalizer::philippinePhone($this->input('phone')),
        ]);
    }
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $extensions = implode(',', config('seller_documents.extensions'));
        $mimeTypes = implode(',', config('seller_documents.mime_types'));
        $documentRules = [
            'file',
            'max:'.config('seller_documents.max_kilobytes'),
            'mimes:'.$extensions,
            'mimetypes:'.$mimeTypes,
            'extensions:'.$extensions,
        ];

        return [
            'first_name' => ['required', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \'\-][\pL]+)*$/u'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \'\-][\pL]+)*$/u'],
            'middle_initial' => ['nullable', 'string', 'regex:/^\pL$/u'],
            'sex' => ['required', Rule::in(['Male', 'Female'])],
            'birthday' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'phone' => ['required', 'string', 'regex:/^09\d{9}$/', Rule::unique('users', 'phone')],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users')->where(fn ($query) => $query->whereRaw('LOWER(email) = ?', [$this->input('email')]))],
            'region' => ['required', 'regex:/^\d+$/'],
            'province' => ['nullable', 'regex:/^\d+$/'],
            'municipality' => ['required', 'regex:/^\d+$/'],
            'barangay' => ['required', 'regex:/^\d+$/'],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'business_name' => ['required', 'string', 'max:150'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('parent_id')),
            ],
            'seller_type' => ['required', Rule::in(['individual', 'business'])],
            'tin' => ['required', 'string', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/'],
            'valid_id' => array_merge(['required'], $documentRules),
            'business_permit' => array_merge(['required_if:seller_type,business'], $documentRules),
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'birthday.before_or_equal' => 'Sellers must be at least 18 years old.',
            'phone.regex' => 'The phone number must use the 09XXXXXXXXX format.',
            'first_name.regex' => 'Please enter a valid name.',
            'last_name.regex' => 'Please enter a valid name.',
            'middle_initial.regex' => 'Middle initial must be one letter.',
            'tin.regex' => 'The TIN must use the XXX-XXX-XXX-XXX format.',
            'business_permit.required_if' => 'A business permit is required for business sellers.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.mixed' => 'Password must include uppercase and lowercase letters.',
            'password.numbers' => 'Password must include at least one number.',
            'password.symbols' => 'Password must include at least one special character.',
            'password.confirmed' => 'Password confirmation must match the password.',
        ];
    }
}
