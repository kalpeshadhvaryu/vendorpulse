<?php

namespace App\Http\Requests\Api\V1\MonitoringChecks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMonitoringLogsRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'downtime_only' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['ok', 'failed', 'error', 'degraded', 'skipped'])],
            'from_at' => ['sometimes', 'nullable', 'date'],
            'to_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:from_at'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
