<?php

namespace App\Http\Requests\Api\V1\Vapt;

use Illuminate\Foundation\Http\FormRequest;

class RunDnsCheckRequest extends FormRequest
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
        ];
    }
}
