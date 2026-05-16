<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Vapt\RunUrlCheckerRequest;
use App\Services\Vapt\UrlCheckerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class VaptWebHealthController extends BaseApiController
{
    public function __construct(
        private readonly UrlCheckerService $urlChecker,
    ) {}

    public function urlChecker(RunUrlCheckerRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $targetUrl = (string) ($validated['target_url'] ?? '');
        $maxLinks = (int) ($validated['max_links'] ?? 200);

        $report = $this->urlChecker->scan($targetUrl, $maxLinks);

        return ApiResponse::success($report, 'URL checker report generated.');
    }
}
