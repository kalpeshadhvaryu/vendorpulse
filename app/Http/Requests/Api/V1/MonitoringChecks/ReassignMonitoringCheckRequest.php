<?php

namespace App\Http\Requests\Api\V1\MonitoringChecks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReassignMonitoringCheckRequest extends FormRequest
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
            'from_organization_id' => ['sometimes', 'uuid', Rule::exists('organizations', 'id')],
            'to_organization_id' => ['required', 'uuid', Rule::exists('organizations', 'id')],
            'execute' => ['sometimes', 'boolean'],
        ];
    }
}
