<?php

namespace App\Http\Requests\Api\V1\SocialAccounts;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDomainSocialAccountRequest extends FormRequest
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

        $platform = $this->input('platform_name');
        if (is_string($platform)) {
            $platform = strtolower(trim($platform));
        }

        $this->merge([
            'organization_id' => $organizationId,
            'platform_name' => $platform,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = $this->input('organization_id');

        return [
            'organization_id' => ['required', 'uuid', Rule::exists('organizations', 'id')],
            'monitoring_check_id' => [
                'required',
                'uuid',
                Rule::exists('monitoring_checks', 'id')->where('organization_id', $organizationId),
            ],
            'platform_name' => [
                'required',
                'string',
                'max:64',
                Rule::unique('domain_social_accounts', 'platform_name')
                    ->where('organization_id', $organizationId)
                    ->where('monitoring_check_id', (string) $this->input('monitoring_check_id')),
            ],
            'social_handle_or_url' => ['required', 'string', 'max:2048'],
            'last_follower_count' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'platform_name.unique' => 'This platform is already mapped to the selected monitored domain.',
            'monitoring_check_id.exists' => 'The selected monitored domain was not found in your organization context.',
        ];
    }
}
