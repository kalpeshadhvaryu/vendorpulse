<?php

namespace App\Http\Requests\Api\V1\ExperienceMonitoring;

use App\ExperienceMonitoring\Support\BrowserTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExperienceMonitoringTestRequest extends FormRequest
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
            'login_url' => ['sometimes', 'url', 'max:2048'],
            'login_username' => ['sometimes', 'string', 'max:255'],
            'login_password' => ['sometimes', 'string', 'max:2048'],
            'dashboard_url' => ['sometimes', 'url', 'max:2048'],
            'interval_seconds' => ['sometimes', 'nullable', 'integer', 'min:60', 'max:86400'],
            'browser_type' => ['sometimes', 'nullable', 'string', Rule::in(BrowserTypes::allowed())],
            'timeout_ms' => ['sometimes', 'nullable', 'integer', 'min:1000', 'max:180000'],
            'concurrent_sessions' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:25'],
            'configuration' => ['sometimes', 'nullable', 'array'],
            'thresholds' => ['sometimes', 'nullable', 'array'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
