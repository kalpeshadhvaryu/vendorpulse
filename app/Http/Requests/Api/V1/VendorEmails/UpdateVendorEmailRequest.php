<?php

namespace App\Http\Requests\Api\V1\VendorEmails;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVendorEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['sometimes', 'string', 'email', 'max:255'],
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'purpose' => ['sometimes', 'nullable', 'string', Rule::in(['primary', 'billing', 'alerts', 'abuse', 'general'])],
            'is_monitored' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'last_checked_at' => ['sometimes', 'nullable', 'date'],
            'last_check_status' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
