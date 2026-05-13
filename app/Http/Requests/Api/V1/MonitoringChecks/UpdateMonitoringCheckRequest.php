<?php

namespace App\Http\Requests\Api\V1\MonitoringChecks;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMonitoringCheckRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in(self::TYPES)],
            'endpoint' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'configuration' => ['sometimes', 'nullable', 'array'],
            'interval_seconds' => ['sometimes', 'nullable', 'integer', 'min:60', 'max:86400'],
            'enabled' => ['sometimes', 'boolean'],
            'vendor_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('vendors', 'id')->where('company_id', $organizationId)],
        ];
    }
}
