<?php

namespace App\Http\Requests\Api\V1\MonitoringChecks;

use Illuminate\Foundation\Http\FormRequest;

class MonitoringLogsWindowRequest extends FormRequest
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
            'from_at' => ['sometimes', 'nullable', 'date'],
            'to_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:from_at'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
