<?php

namespace App\Http\Requests\Api\V1\Vapt;

use Illuminate\Foundation\Http\FormRequest;

class RunSecurityAuditRequest extends FormRequest
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
            'target_url' => ['required', 'string', 'max:2048'],
            'authorized' => ['accepted'],
            'depth' => ['sometimes', 'in:standard,deep'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'authorized.accepted' => 'Confirm you are authorized to security-test this domain before running the audit.',
        ];
    }
}
