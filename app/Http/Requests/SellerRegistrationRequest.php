<?php

namespace App\Http\Requests;

use App\Support\IdentityNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'sex' => ['required', Rule::in(['Male', 'Female'])],
            'birthday' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'phone' => ['required', 'string', 'regex:/^09\d{9}$/', Rule::unique('users', 'phone')],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users')->where(fn ($query) => $query->whereRaw('LOWER(email) = ?', [$this->input('email')]))],
            'province' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'business_name' => ['required', 'string', 'max:150'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'seller_type' => ['required', Rule::in(['individual', 'business'])],
            'tin' => ['required', 'string', 'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/'],
            'valid_id' => array_merge(['required'], $documentRules),
            'business_permit' => array_merge(['required_if:seller_type,business'], $documentRules),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'birthday.before_or_equal' => 'Sellers must be at least 18 years old.',
            'phone.regex' => 'The phone number must use the 09XXXXXXXXX format.',
            'tin.regex' => 'The TIN must use the XXX-XXX-XXX-XXX format.',
            'business_permit.required_if' => 'A business permit is required for business sellers.',
        ];
    }
}
