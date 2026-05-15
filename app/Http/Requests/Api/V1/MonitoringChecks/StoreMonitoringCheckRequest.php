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
        'server',
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
                    'uptime', 'ssl', 'domain', 'http', 'https', 'tls', 'whois', 'server',
                ], true)),
            ],
            'configuration' => ['nullable', 'array'],
            'configuration.server_provider' => ['nullable', 'string', Rule::in(['cpanel', 'whm', 'plesk', 'generic'])],
            'configuration.os' => ['nullable', 'string', 'max:120', Rule::requiredIf(fn () => (string) $this->input('type') === 'server')],
            'configuration.hostname' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => (string) $this->input('type') === 'server')],
            'configuration.api_base_url' => ['nullable', 'url', 'max:2048', Rule::requiredIf(fn () => (string) $this->input('type') === 'server')],
            'configuration.api_path' => ['nullable', 'string', 'max:512'],
            'configuration.auth_type' => ['nullable', 'string', Rule::in(['cpanel_token', 'whm_token', 'bearer', 'basic', 'none'])],
            'configuration.api_username' => ['nullable', 'string', 'max:255'],
            'configuration.api_token' => ['nullable', 'string', 'max:2048'],
            'configuration.api_password' => ['nullable', 'string', 'max:2048'],
            'configuration.verify_ssl' => ['nullable', 'boolean'],
            'configuration.metrics_paths' => ['nullable', 'array'],
            'configuration.metrics_paths.load_1m' => ['nullable', 'string', 'max:255'],
            'configuration.metrics_paths.load_5m' => ['nullable', 'string', 'max:255'],
            'configuration.metrics_paths.load_15m' => ['nullable', 'string', 'max:255'],
            'configuration.metrics_paths.cpu_percent' => ['nullable', 'string', 'max:255'],
            'configuration.metrics_paths.memory_used_percent' => ['nullable', 'string', 'max:255'],
            'configuration.metrics_paths.disk_used_percent' => ['nullable', 'string', 'max:255'],
            'configuration.metrics_paths.bandwidth_used_bytes' => ['nullable', 'string', 'max:255'],
            'configuration.metrics_paths.process_count' => ['nullable', 'string', 'max:255'],
            'configuration.thresholds' => ['nullable', 'array'],
            'interval_seconds' => ['nullable', 'integer', 'min:60', 'max:86400'],
            'enabled' => ['sometimes', 'boolean'],
            'vendor_id' => ['nullable', 'uuid', Rule::exists('vendors', 'id')->where('company_id', $organizationId)],
        ];
    }
}
