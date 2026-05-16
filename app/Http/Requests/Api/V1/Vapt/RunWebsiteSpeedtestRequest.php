<?php

namespace App\Http\Requests\Api\V1\Vapt;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RunWebsiteSpeedtestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $targetUrl = trim((string) $this->input('target_url', ''));

        if ($targetUrl !== '' && ! Str::startsWith($targetUrl, ['http://', 'https://'])) {
            $targetUrl = 'https://'.$targetUrl;
        }

        $this->merge([
            'target_url' => $targetUrl,
        ]);
    }

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
            'timeout_seconds' => ['sometimes', 'integer', 'min:5', 'max:60'],
        ];
    }
}
