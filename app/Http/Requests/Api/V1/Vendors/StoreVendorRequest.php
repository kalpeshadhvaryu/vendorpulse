<?php

namespace App\Http\Requests\Api\V1\Vendors;

use App\Enums\BillingCycle;
use App\Enums\VendorStatus;
use App\Enums\VendorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'vendor_type' => ['required', Rule::enum(VendorType::class)],
            'billing_email' => ['nullable', 'string', 'email', 'max:255'],
            'support_email' => ['nullable', 'string', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:2048'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'expected_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
            'renewal_date' => ['nullable', 'date'],
            'auto_detect_invoices' => ['sometimes', 'boolean'],
            'auto_fetch_email' => ['sometimes', 'boolean'],
            'match_inbound_from_website_domain' => ['sometimes', 'boolean'],
            'monitoring_enabled' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(VendorStatus::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('currency')) {
            $this->merge(['currency' => 'USD']);
        }

        if (! $this->has('status')) {
            $this->merge(['status' => VendorStatus::Active->value]);
        }

        if (! $this->has('auto_detect_invoices')) {
            $this->merge(['auto_detect_invoices' => false]);
        }

        if (! $this->has('auto_fetch_email')) {
            $this->merge(['auto_fetch_email' => true]);
        }

        if (! $this->has('match_inbound_from_website_domain')) {
            $this->merge(['match_inbound_from_website_domain' => false]);
        }

        if (! $this->has('monitoring_enabled')) {
            $this->merge(['monitoring_enabled' => false]);
        }
    }
}
