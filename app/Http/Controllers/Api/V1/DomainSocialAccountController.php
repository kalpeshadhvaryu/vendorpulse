<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\SocialAccounts\ListDomainSocialAccountsRequest;
use App\Http\Requests\Api\V1\SocialAccounts\StoreDomainSocialAccountRequest;
use App\Http\Requests\Api\V1\SocialAccounts\UpdateDomainSocialAccountRequest;
use App\Models\DomainSocialAccount;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DomainSocialAccountController extends BaseApiController
{
    public function index(ListDomainSocialAccountsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = DomainSocialAccount::query()
            ->where('organization_id', $validated['organization_id'])
            ->orderBy('platform_name')
            ->orderByDesc('updated_at');

        if (! empty($validated['monitoring_check_id'])) {
            $query->where('monitoring_check_id', $validated['monitoring_check_id']);
        }

        return ApiResponse::success($query->get());
    }

    public function store(StoreDomainSocialAccountRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $socialAccount = DomainSocialAccount::query()->create([
            'monitoring_check_id' => $validated['monitoring_check_id'],
            'organization_id' => $validated['organization_id'],
            'platform_name' => $validated['platform_name'],
            'social_handle_or_url' => $validated['social_handle_or_url'],
            'last_follower_count' => (int) ($validated['last_follower_count'] ?? 0),
        ]);

        return ApiResponse::success(
            $socialAccount,
            'Social account mapped to monitored domain.',
            Response::HTTP_CREATED
        );
    }

    public function update(UpdateDomainSocialAccountRequest $request, DomainSocialAccount $domainSocialAccount): JsonResponse
    {
        $domainSocialAccount->update($request->validated());

        return ApiResponse::success($domainSocialAccount->fresh(), 'Social account mapping updated.');
    }

    public function destroy(DomainSocialAccount $domainSocialAccount): JsonResponse
    {
        $domainSocialAccount->delete();

        return ApiResponse::success(null, 'Social account mapping deleted.');
    }
}
