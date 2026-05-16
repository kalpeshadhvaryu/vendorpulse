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
        $targetUrl = (string) $request->validated('target_url');
        $report = $this->seoAuditService->audit($targetUrl);

        return ApiResponse::success($report, 'On-page SEO audit generated.');
    }
}
