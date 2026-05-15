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
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in(self::TYPES)],
            'endpoint' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'configuration' => ['sometimes', 'nullable', 'array'],
            'configuration.server_provider' => ['sometimes', 'nullable', 'string', Rule::in(['cpanel', 'whm', 'plesk', 'generic'])],
            'configuration.os' => ['sometimes', 'nullable', 'string', 'max:120'],
            'configuration.hostname' => ['sometimes', 'nullable', 'string', 'max:255'],
            'configuration.api_base_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'configuration.api_path' => ['sometimes', 'nullable', 'string', 'max:512'],
            'configuration.auth_type' => ['sometimes', 'nullable', 'string', Rule::in(['cpanel_token', 'whm_token', 'bearer', 'basic', 'none'])],
            'configuration.api_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'configuration.api_token' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'configuration.api_password' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'configuration.verify_ssl' => ['sometimes', 'nullable', 'boolean'],
            'configuration.metrics_paths' => ['sometimes', 'nullable', 'array'],
            'configuration.metrics_paths.load_1m' => ['sometimes', 'nullable', 'string', 'max:255'],
            'configuration.metrics_paths.load_5m' => ['sometimes', 'nullable', 'string', 'max:255'],
            'configuration.metrics_paths.load_15m' => ['sometimes', 'nullable', 'string', 'max:255'],
            'configuration.metrics_paths.cpu_percent' => ['sometimes', 'nullable', 'string', 'max:255'],
            'configuration.metrics_paths.memory_used_percent' => ['sometimes', 'nullable', 'string', 'max:255'],
            'configuration.metrics_paths.disk_used_percent' => ['sometimes', 'nullable', 'string', 'max:255'],
            'configuration.metrics_paths.bandwidth_used_bytes' => ['sometimes', 'nullable', 'string', 'max:255'],
            'configuration.metrics_paths.process_count' => ['sometimes', 'nullable', 'string', 'max:255'],
            'configuration.thresholds' => ['sometimes', 'nullable', 'array'],
            'interval_seconds' => ['sometimes', 'nullable', 'integer', 'min:60', 'max:86400'],
            'enabled' => ['sometimes', 'boolean'],
            'vendor_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('vendors', 'id')->where('company_id', $organizationId)],
        ];
    }
}
