<?php

namespace App\Http\Requests\Api\V1\SeoKeywords;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeoKeywordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $organizationId = app(CurrentOrganization::class)->id();
        $user = $this->user();

        if (! $organizationId && $user) {
            $organizationId = $user->default_organization_id
                ?: $user->organizations()->orderBy('organizations.created_at')->value('organizations.id');
        }

        $phrase = $this->input('phrase');
        if (is_string($phrase)) {
            $phrase = trim(preg_replace('/\s+/', ' ', $phrase) ?? $phrase);
        }

        $targetUrl = $this->input('target_url');
        if (is_string($targetUrl)) {
            $targetUrl = trim($targetUrl);
            if ($targetUrl !== '' && ! str_contains($targetUrl, '://')) {
                $targetUrl = 'https://'.$targetUrl;
            }
        }

        $this->merge([
            'organization_id' => $organizationId,
            'phrase' => $phrase,
            'target_url' => $targetUrl === '' ? null : $targetUrl,
            'priority' => strtolower((string) ($this->input('priority') ?: 'medium')),
            'country' => strtolower((string) ($this->input('country') ?: 'in')),
            'language' => strtolower((string) ($this->input('language') ?: 'en')),
            'device' => strtolower((string) ($this->input('device') ?: 'desktop')),
            'status' => strtolower((string) ($this->input('status') ?: 'active')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'uuid', Rule::exists('organizations', 'id')],
            'phrase' => [
                'required',
                'string',
                'max:255',
                Rule::unique('seo_keywords', 'phrase')
                    ->where('organization_id', $this->input('organization_id'))
                    ->where('country', $this->input('country'))
                    ->where('language', $this->input('language'))
                    ->where('device', $this->input('device')),
            ],
            'priority' => ['required', Rule::in(['high', 'medium', 'low'])],
            'target_url' => ['nullable', 'url', 'max:2048'],
            'country' => ['required', 'string', 'size:2'],
            'language' => ['required', 'string', 'max:16'],
            'device' => ['required', Rule::in(['mobile', 'desktop'])],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'paused'])],
        ];
    }
}
