<?php

namespace App\Http\Requests\Api\V1\SeoKeywords;

use App\Models\SeoKeyword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeoKeywordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('phrase') && is_string($this->input('phrase'))) {
            $data['phrase'] = trim(preg_replace('/\s+/', ' ', $this->input('phrase')) ?? $this->input('phrase'));
        }
        if ($this->has('priority')) {
            $data['priority'] = strtolower((string) $this->input('priority'));
        }
        if ($this->has('country')) {
            $data['country'] = strtolower((string) $this->input('country'));
        }
        if ($this->has('language')) {
            $data['language'] = strtolower((string) $this->input('language'));
        }
        if ($this->has('device')) {
            $data['device'] = strtolower((string) $this->input('device'));
        }
        if ($this->has('status')) {
            $data['status'] = strtolower((string) $this->input('status'));
        }
        if ($this->has('target_url')) {
            $url = trim((string) $this->input('target_url'));
            if ($url !== '' && ! str_contains($url, '://')) {
                $url = 'https://'.$url;
            }
            $data['target_url'] = $url === '' ? null : $url;
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var SeoKeyword|null $keyword */
        $keyword = $this->route('seo_keyword');

        return [
            'phrase' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('seo_keywords', 'phrase')
                    ->ignore($keyword?->id)
                    ->where('organization_id', $keyword?->organization_id)
                    ->where('country', $this->input('country', $keyword?->country))
                    ->where('language', $this->input('language', $keyword?->language))
                    ->where('device', $this->input('device', $keyword?->device)),
            ],
            'priority' => ['sometimes', Rule::in(['high', 'medium', 'low'])],
            'target_url' => ['nullable', 'url', 'max:2048'],
            'country' => ['sometimes', 'string', 'size:2'],
            'language' => ['sometimes', 'string', 'max:16'],
            'device' => ['sometimes', Rule::in(['mobile', 'desktop'])],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(['active', 'paused'])],
        ];
    }
}
