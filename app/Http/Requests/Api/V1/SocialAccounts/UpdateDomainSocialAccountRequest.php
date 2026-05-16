<?php

namespace App\Http\Requests\Api\V1\SocialAccounts;

use App\Models\DomainSocialAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDomainSocialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $platform = $this->input('platform_name');
        if (is_string($platform)) {
            $this->merge([
                'platform_name' => strtolower(trim($platform)),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var DomainSocialAccount|null $socialAccount */
        $socialAccount = $this->route('domain_social_account');

        $organizationId = $socialAccount?->organization_id;
        $monitoringCheckId = $socialAccount?->monitoring_check_id;

        return [
            'platform_name' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('domain_social_accounts', 'platform_name')
                    ->where('organization_id', $organizationId)
                    ->where('monitoring_check_id', $monitoringCheckId)
                    ->ignore($socialAccount?->id),
            ],
            'social_handle_or_url' => ['sometimes', 'string', 'max:2048'],
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
        ];
    }
}
