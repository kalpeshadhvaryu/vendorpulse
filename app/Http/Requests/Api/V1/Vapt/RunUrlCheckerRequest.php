<?php

namespace App\Http\Requests\Api\V1\Vapt;

use Illuminate\Foundation\Http\FormRequest;

class RunUrlCheckerRequest extends FormRequest
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
            'target_url' => ['required', 'url', 'max:2048'],
            'max_links' => ['sometimes', 'integer', 'min:1', 'max:300'],
        ];
    }
}
