<?php

namespace App\Http\Requests\Api\V1\MonitoringChecks;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMonitoringCheckRequest extends FormRequest
{
    private const TYPES = [
        'uptime',
        'ssl',
        'domain',
        'http',
        'https',
        'tls',
        'whois',
        'tcp',
        'ping',
        'dns',
        'custom',
    ];

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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(self::TYPES)],
            'endpoint' => [
                'nullable',
                'string',
                'max:2048',
                Rule::requiredIf(fn () => in_array((string) $this->input('type'), [
                    'uptime', 'ssl', 'domain', 'http', 'https', 'tls', 'whois',
                ], true)),
            ],
            'configuration' => ['nullable', 'array'],
            'interval_seconds' => ['nullable', 'integer', 'min:60', 'max:86400'],
            'enabled' => ['sometimes', 'boolean'],
            'vendor_id' => ['nullable', 'uuid', Rule::exists('vendors', 'id')->where('company_id', $organizationId)],
        ];
    }
}
