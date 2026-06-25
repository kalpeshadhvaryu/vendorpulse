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
            'mode' => ['sometimes', 'string', 'in:quick,site_crawl'],
            'max_pages' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'max_depth' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'authorized' => ['required_if:mode,site_crawl', 'accepted'],
        ];
    }
}
