<?php

namespace App\Http\Requests\Api\V1\MonitoringChecks;

use Illuminate\Foundation\Http\FormRequest;

class ListMonitoringChecksRequest extends FormRequest
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
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'nullable', 'string', \Illuminate\Validation\Rule::in(self::TYPES)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
