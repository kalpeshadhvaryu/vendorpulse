<?php

namespace App\Http\Requests\Api\V1\Vendors;

use App\Enums\BillingCycle;
use App\Enums\VendorStatus;
use App\Enums\VendorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListVendorsRequest extends FormRequest
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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'array'],
            'status.*' => [Rule::enum(VendorStatus::class)],
            'vendor_type' => ['sometimes', 'array'],
            'vendor_type.*' => [Rule::enum(VendorType::class)],
            'billing_cycle' => ['sometimes', 'array'],
            'billing_cycle.*' => [Rule::enum(BillingCycle::class)],
            'currency' => ['sometimes', 'array'],
            'currency.*' => ['string', 'size:3', 'alpha'],
            'monitoring_enabled' => ['sometimes', 'boolean'],
            'auto_detect_invoices' => ['sometimes', 'boolean'],
            'auto_fetch_email' => ['sometimes', 'boolean'],
            'renewal_from' => ['sometimes', 'date'],
            'renewal_to' => [
                'sometimes',
                'date',
                Rule::when(
                    $this->filled('renewal_from'),
                    ['after_or_equal:renewal_from']
                ),
            ],
            'sort' => ['sometimes', 'string', Rule::in([
                'created_at', 'updated_at', 'name', 'renewal_date', 'expected_amount',
                'status', 'vendor_type', 'billing_cycle', 'currency',
            ])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->except(['per_page', 'page']);
    }
}
