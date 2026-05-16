<?php

namespace App\Http\Requests\Api\V1\SocialAccounts;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDomainSocialAccountsRequest extends FormRequest
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

        $this->merge([
            'organization_id' => $organizationId,
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
                'sometimes',
                'uuid',
                Rule::exists('monitoring_checks', 'id')->where('organization_id', $organizationId),
            ],
        ];
    }
}
