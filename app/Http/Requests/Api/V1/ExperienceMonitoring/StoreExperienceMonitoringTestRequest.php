<?php

namespace App\Http\Requests\Api\V1\ExperienceMonitoring;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExperienceMonitoringTestRequest extends FormRequest
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
            'login_url' => ['required', 'url', 'max:2048'],
            'login_username' => ['required', 'string', 'max:255'],
            'login_password' => ['required', 'string', 'max:2048'],
            'dashboard_url' => ['required', 'url', 'max:2048'],
            'interval_seconds' => ['nullable', 'integer', 'min:60', 'max:86400'],
            'browser_type' => ['nullable', 'string', Rule::in(['chromium', 'firefox', 'webkit'])],
            'timeout_ms' => ['nullable', 'integer', 'min:1000', 'max:180000'],
            'concurrent_sessions' => ['nullable', 'integer', 'min:1', 'max:25'],
            'configuration' => ['nullable', 'array'],
            'thresholds' => ['nullable', 'array'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
