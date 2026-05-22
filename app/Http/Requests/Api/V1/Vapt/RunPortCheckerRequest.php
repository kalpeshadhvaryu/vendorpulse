<?php

namespace App\Http\Requests\Api\V1\Vapt;

use Illuminate\Foundation\Http\FormRequest;

class RunPortCheckerRequest extends FormRequest
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
            'target' => ['required', 'string', 'max:2048'],
            'mode' => ['sometimes', 'in:quick,extended'],
            'custom_ports' => ['nullable', 'string', 'max:2000'],
            'timeout_ms' => ['sometimes', 'integer', 'min:300', 'max:5000'],
        ];
    }
}
