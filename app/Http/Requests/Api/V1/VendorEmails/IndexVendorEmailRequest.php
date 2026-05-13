<?php

namespace App\Http\Requests\Api\V1\VendorEmails;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexVendorEmailRequest extends FormRequest
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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'vendor_id' => [
                'sometimes',
                'uuid',
                Rule::exists('vendors', 'id')->where('company_id', $organizationId),
            ],
        ];
    }
}
