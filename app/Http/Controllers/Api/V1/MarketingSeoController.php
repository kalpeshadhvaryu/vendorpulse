<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\MarketingSeo\RunOnPageSeoAuditRequest;
use App\Services\MarketingSeo\OnPageSeoAuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MarketingSeoController extends BaseApiController
{
    public function __construct(
        private readonly OnPageSeoAuditService $seoAuditService,
    ) {}

    public function onPageAudit(RunOnPageSeoAuditRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $targetUrl = (string) ($validated['target_url'] ?? '');
        $mode = (string) ($validated['mode'] ?? 'quick');

        if ($mode === 'site_crawl') {
            $report = $this->seoAuditService->crawlSite(
                $targetUrl,
                (int) ($validated['max_pages'] ?? 30),
                (int) ($validated['max_depth'] ?? 3),
            );
        } else {
            $report = $this->seoAuditService->audit($targetUrl);
        }

        return ApiResponse::success($report, 'On-page SEO audit generated.');
    }
}
