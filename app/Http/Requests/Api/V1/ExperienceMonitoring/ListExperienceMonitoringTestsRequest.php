<?php

namespace App\Http\Requests\Api\V1\ExperienceMonitoring;

use Illuminate\Foundation\Http\FormRequest;

class ListExperienceMonitoringTestsRequest extends FormRequest
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
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:32'],
        ];
    }
}
