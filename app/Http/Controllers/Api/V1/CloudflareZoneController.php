<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CloudflareZone;
use App\Services\Cloudflare\CloudflareZoneService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CloudflareZoneController extends BaseApiController
{
    public function __construct(private readonly CloudflareZoneService $zones) {}

    public function status(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'configured' => $this->zones->isConfigured(),
            'allow_org_purge_cache' => (bool) config('services.cloudflare.allow_org_purge_cache', true),
        ]);
    }

    /** Admin: full catalog + assignment state. */
    public function adminIndex(Request $request): JsonResponse
    {
        if ($error = $this->requireAdmin($request)) {
            return $error;
        }

        $query = CloudflareZone::query()->with('organization')->orderBy('name');

        if ($request->query('assigned') === '1') {
            $query->whereNotNull('organization_id');
        } elseif ($request->query('assigned') === '0') {
            $query->whereNull('organization_id');
        }

        if ($orgId = $request->query('organization_id')) {
            $query->where('organization_id', $orgId);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where('name', 'ilike', '%'.$search.'%');
        }

        $items = $query->limit(500)->get()->map(fn (CloudflareZone $z) => $this->zones->present($z));

        return ApiResponse::success([
            'configured' => $this->zones->isConfigured(),
            'items' => $items,
        ]);
    }

    /** Admin: pull zones from company Cloudflare account. */
    public function sync(Request $request): JsonResponse
    {
        if ($error = $this->requireAdmin($request)) {
            return $error;
        }

        try {
            $result = $this->zones->syncFromCloudflare();
        } catch (Throwable $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_BAD_GATEWAY);
        }

        return ApiResponse::success($result, 'Cloudflare zones synced.');
    }

    /** Admin: assign or unassign a zone to an organization. */
    public function assign(Request $request, CloudflareZone $cloudflareZone): JsonResponse
    {
        if ($error = $this->requireAdmin($request)) {
            return $error;
        }

        $validated = $request->validate([
            'organization_id' => ['nullable', 'uuid', Rule::exists('organizations', 'id')],
        ]);

        $zone = $this->zones->assign(
            $cloudflareZone,
            $validated['organization_id'] ?? null,
            $request->user()?->id
        );

        return ApiResponse::success(
            $this->zones->present($zone->load('organization')),
            ($validated['organization_id'] ?? null) ? 'Domain assigned to organization.' : 'Domain unassigned.'
        );
    }

    /** Org (or admin): domains assigned to the current organization. */
    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId();

        $items = CloudflareZone::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get()
            ->map(fn (CloudflareZone $z) => $this->zones->present($z, includeAssignment: false));

        return ApiResponse::success([
            'configured' => $this->zones->isConfigured(),
            'allow_org_purge_cache' => (bool) config('services.cloudflare.allow_org_purge_cache', true),
            'items' => $items,
        ]);
    }

    public function show(Request $request, CloudflareZone $cloudflareZone): JsonResponse
    {
        if ($error = $this->authorizeZoneAccess($request, $cloudflareZone)) {
            return $error;
        }

        return ApiResponse::success($this->zones->present(
            $cloudflareZone->loadMissing('organization'),
            includeAssignment: (bool) $request->user()?->isAdmin()
        ));
    }

    public function refresh(Request $request, CloudflareZone $cloudflareZone): JsonResponse
    {
        if ($error = $this->authorizeZoneAccess($request, $cloudflareZone)) {
            return $error;
        }

        try {
            $zone = $this->zones->refresh($cloudflareZone);
        } catch (Throwable $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_BAD_GATEWAY);
        }

        return ApiResponse::success(
            $this->zones->present($zone->loadMissing('organization'), includeAssignment: (bool) $request->user()?->isAdmin()),
            'Domain status refreshed from Cloudflare.'
        );
    }

    public function purgeCache(Request $request, CloudflareZone $cloudflareZone): JsonResponse
    {
        if ($error = $this->authorizeZoneAccess($request, $cloudflareZone)) {
            return $error;
        }

        $isAdmin = (bool) $request->user()?->isAdmin();
        if (! $isAdmin && ! (bool) config('services.cloudflare.allow_org_purge_cache', true)) {
            return ApiResponse::error('Cache purge is disabled for organization users.', Response::HTTP_FORBIDDEN);
        }

        try {
            $this->zones->purgeCache($cloudflareZone);
        } catch (Throwable $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_BAD_GATEWAY);
        }

        return ApiResponse::success(['purged' => true], 'Cloudflare cache purge requested.');
    }

    private function requireAdmin(Request $request): ?JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return ApiResponse::error('Only admins can manage the Cloudflare domain catalog.', Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    private function authorizeZoneAccess(Request $request, CloudflareZone $zone): ?JsonResponse
    {
        if ($request->user()?->isAdmin()) {
            return null;
        }

        $organizationId = $this->organizationIdOrNull();
        if ($organizationId === null || (string) $zone->organization_id !== $organizationId) {
            return ApiResponse::error('Domain not found for this organization.', Response::HTTP_NOT_FOUND);
        }

        return null;
    }
}
