<?php

namespace App\Http\Requests\Api\V1\Vendors;

use App\Enums\BillingCycle;
use App\Enums\VendorStatus;
use App\Enums\VendorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVendorRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'vendor_type' => ['sometimes', Rule::enum(VendorType::class)],
            'billing_email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'support_email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'website' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'expected_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'billing_cycle' => ['sometimes', Rule::enum(BillingCycle::class)],
            'renewal_date' => ['sometimes', 'nullable', 'date'],
            'auto_detect_invoices' => ['sometimes', 'boolean'],
            'auto_fetch_email' => ['sometimes', 'boolean'],
            'match_inbound_from_website_domain' => ['sometimes', 'boolean'],
            'monitoring_enabled' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::enum(VendorStatus::class)],
        ];
    }
}
