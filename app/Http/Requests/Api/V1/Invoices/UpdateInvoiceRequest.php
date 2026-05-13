<?php

namespace App\Http\Requests\Api\V1\Invoices;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
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
            'number' => ['sometimes', 'string', 'max:64', Rule::unique('invoices', 'number')->where('organization_id', $organizationId)->ignore($this->route('invoice'))],
            'vendor_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('vendors', 'id')->where('company_id', $organizationId)],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['draft', 'open', 'paid', 'overdue', 'cancelled'])],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'amount_cents' => ['sometimes', 'integer', 'min:0'],
            'tax_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'issued_on' => ['sometimes', 'nullable', 'date'],
            'due_on' => ['sometimes', 'nullable', 'date'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
