<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\ApiResponse;
use App\Support\Organization\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationContext
{
    public function __construct(
        protected CurrentOrganization $currentOrganization
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $isAdmin = $user?->isAdmin() === true;

        if (! $user) {
            return ApiResponse::error('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }

        $headerOrganizationId = $request->header('X-Organization-Id');

        // In "All organizations" mode, the frontend intentionally omits this header.
        // Admin requests without an explicit header should remain unscoped.
        if ($isAdmin && ! $headerOrganizationId) {
            $this->currentOrganization->clear();

            return $next($request);
        }

        $candidateId = $headerOrganizationId ?: $user->default_organization_id;

        if (! $candidateId) {
            if ($isAdmin) {
                $this->currentOrganization->clear();

                return $next($request);
            }

            return ApiResponse::error(
                'Organization context is required. Send X-Organization-Id or assign a default organization to the user.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (! $isAdmin && ! $user->belongsToOrganization((string) $candidateId)) {
            return ApiResponse::error('You do not have access to this organization.', Response::HTTP_FORBIDDEN);
        }

        $organization = Organization::query()->whereKey($candidateId)->first();

        if (! $organization) {
            return ApiResponse::error('Organization not found.', Response::HTTP_NOT_FOUND);
        }

        $this->currentOrganization->set($organization);

        try {
            return $next($request);
        } finally {
            $this->currentOrganization->clear();
        }
    }
}
