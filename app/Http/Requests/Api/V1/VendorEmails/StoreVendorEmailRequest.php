<?php

namespace App\Http\Requests\Api\V1\VendorEmails;

use App\Models\Vendor;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorEmailRequest extends FormRequest
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
        $organizationId = app(CurrentOrganization::class)->id();

        return [
            'vendor_id' => [
                'required',
                'uuid',
                Rule::exists('vendors', 'id')->where('company_id', $organizationId),
            ],
            'email' => ['required', 'string', 'email', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', Rule::in(['primary', 'billing', 'alerts', 'abuse', 'general'])],
            'is_monitored' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function vendor(): Vendor
    {
        return Vendor::query()->findOrFail($this->validated('vendor_id'));
    }
}
