<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Vapt\RunUrlCheckerRequest;
use App\Http\Requests\Api\V1\Vapt\RunWebsiteSpeedtestRequest;
use App\Models\Vendor;
use App\Models\WebsiteSpeedtestRun;
use App\Services\Vapt\UrlCheckerService;
use App\Services\Vapt\WebsiteSpeedtestService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VaptWebHealthController extends BaseApiController
{
    public function __construct(
        private readonly UrlCheckerService $urlChecker,
        private readonly WebsiteSpeedtestService $websiteSpeedtest,
    ) {}

    public function urlChecker(RunUrlCheckerRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $targetUrl = (string) ($validated['target_url'] ?? '');
        $maxLinks = (int) ($validated['max_links'] ?? 200);

        $report = $this->urlChecker->scan($targetUrl, $maxLinks);

        return ApiResponse::success($report, 'URL checker report generated.');
    }

    public function websiteSpeedtest(RunWebsiteSpeedtestRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $targetUrl = (string) ($validated['target_url'] ?? '');
        $timeoutSeconds = (int) ($validated['timeout_seconds'] ?? 20);
        $organizationId = $this->organizationIdOrNull();

        $report = $this->websiteSpeedtest->run($targetUrl, $timeoutSeconds);

        $resolvedTargetUrl = (string) ($report['final_url'] ?? $report['target_url'] ?? $targetUrl);
        $shouldPersist = $organizationId
            ? $this->domainBelongsToOrganization($organizationId, $resolvedTargetUrl)
            : false;

        if ($shouldPersist) {
            $run = WebsiteSpeedtestRun::query()->create([
                'organization_id' => $organizationId,
                'created_by' => $request->user()?->id,
                'target_url' => $report['target_url'] ?? $targetUrl,
                'checked_from' => $report['checked_from'] ?? null,
                'final_url' => $report['final_url'] ?? null,
                'status_code' => $report['status_code'] ?? null,
                'success' => (bool) ($report['success'] ?? false),
                'error' => $report['error'] ?? null,
                'timeout_seconds' => $report['timeout_seconds'] ?? $timeoutSeconds,
                'total_time_ms' => $report['metrics']['total_time_ms'] ?? null,
                'ttfb_ms' => $report['metrics']['ttfb_ms'] ?? null,
                'dns_lookup_ms' => $report['metrics']['dns_lookup_ms'] ?? null,
                'tcp_connect_ms' => $report['metrics']['tcp_connect_ms'] ?? null,
                'tls_handshake_ms' => $report['metrics']['tls_handshake_ms'] ?? null,
                'redirect_time_ms' => $report['metrics']['redirect_time_ms'] ?? null,
                'download_speed_kbps' => $report['metrics']['download_speed_kbps'] ?? null,
                'downloaded_bytes' => $report['metrics']['downloaded_bytes'] ?? null,
                'metrics' => $report['metrics'] ?? null,
                'tested_at' => $report['tested_at'] ?? now(),
            ]);

            $report['run_id'] = $run->id;
            $report['persisted'] = true;
            $report['persistence_note'] = 'Saved to organization history.';
        } else {
            $report['run_id'] = null;
            $report['persisted'] = false;
            $report['persistence_note'] = $organizationId
                ? 'Domain is not mapped to this organization, so this result was not saved.'
                : 'No organization selected. Speedtest ran live, but this result was not saved.';
        }

        return ApiResponse::success($report, 'Website speedtest report generated.');
    }

    private function domainBelongsToOrganization(string $organizationId, string $url): bool
    {
        $targetHost = $this->extractHost($url);

        if ($targetHost === null) {
            return false;
        }

        $vendorWebsites = Vendor::query()
            ->where('company_id', $organizationId)
            ->whereNotNull('website')
            ->pluck('website');

        foreach ($vendorWebsites as $website) {
            $vendorHost = $this->extractHost((string) $website);

            if ($vendorHost === null) {
                continue;
            }

            if ($targetHost === $vendorHost || str_ends_with($targetHost, '.'.$vendorHost)) {
                return true;
            }
        }

        return false;
    }

    private function extractHost(string $url): ?string
    {
        $value = trim($url);

        if ($value === '') {
            return null;
        }

        if (! str_contains($value, '://')) {
            $value = 'https://'.$value;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        return strtolower(trim($host));
    }

    public function websiteSpeedtestRuns(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_url' => ['sometimes', 'string', 'max:2048'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'all_organizations' => ['sometimes', 'boolean'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);
        $allOrganizations = (bool) ($validated['all_organizations'] ?? false);
        $currentOrganizationId = $this->organizationIdOrNull();
        $isAdmin = $request->user()?->isAdmin() === true;

        $query = WebsiteSpeedtestRun::query()
            ->with('organization:id,name')
            ->orderByDesc('tested_at')
            ->orderByDesc('created_at');

        if ($allOrganizations) {
            // Admin-only global read mode; stays read-only and does not affect write paths.
            if (! $isAdmin || $currentOrganizationId !== null) {
                return ApiResponse::error('All organizations history is available only in global admin mode.', 403);
            }
        } else {
            if (! $currentOrganizationId) {
                return ApiResponse::error(
                    'Organization context is required. Select an organization or use all_organizations=1 as global admin.',
                    422
                );
            }

            $query->where('organization_id', $currentOrganizationId);
        }

        if (! empty($validated['target_url'])) {
            $query->where('target_url', (string) $validated['target_url']);
        }

        $rows = $query->limit($limit)->get()->map(function (WebsiteSpeedtestRun $run): array {
            $data = $run->toArray();
            $data['organization_name'] = $run->organization?->name;

            return $data;
        });

        return ApiResponse::success($rows);
    }
}
