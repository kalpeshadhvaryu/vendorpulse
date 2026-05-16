<?php

namespace App\Http\Requests\Api\V1\MarketingSeo;

use Illuminate\Foundation\Http\FormRequest;

class RunOnPageSeoAuditRequest extends FormRequest
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
        ];
    }
}
