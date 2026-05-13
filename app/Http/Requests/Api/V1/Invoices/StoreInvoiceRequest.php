<?php

namespace App\Http\Requests\Api\V1\Invoices;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
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
            'number' => ['required', 'string', 'max:64', Rule::unique('invoices', 'number')->where('organization_id', $organizationId)],
            'vendor_id' => ['nullable', 'uuid', Rule::exists('vendors', 'id')->where('company_id', $organizationId)],
            'status' => ['nullable', 'string', Rule::in(['draft', 'open', 'paid', 'overdue', 'cancelled'])],
            'currency' => ['nullable', 'string', 'size:3'],
            'amount_cents' => ['required', 'integer', 'min:0'],
            'tax_cents' => ['nullable', 'integer', 'min:0'],
            'issued_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date'],
            'paid_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
