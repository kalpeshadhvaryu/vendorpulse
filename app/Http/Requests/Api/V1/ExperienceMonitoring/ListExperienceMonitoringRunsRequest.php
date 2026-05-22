<?php

namespace App\Http\Requests\Api\V1\ExperienceMonitoring;

use Illuminate\Foundation\Http\FormRequest;

class ListExperienceMonitoringRunsRequest extends FormRequest
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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', 'max:32'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ];
    }
}
