<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\SeoKeywords\StoreSeoKeywordRequest;
use App\Http\Requests\Api\V1\SeoKeywords\UpdateSeoKeywordRequest;
use App\Models\SeoKeyword;
use App\Services\MarketingSeo\KeywordRankCheckService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SeoKeywordController extends BaseApiController
{
    public function __construct(private readonly KeywordRankCheckService $checks) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId();

        $query = SeoKeyword::query()
            ->where('organization_id', $organizationId)
            ->orderByRaw("case priority when 'high' then 0 when 'medium' then 1 else 2 end")
            ->orderBy('phrase');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }
        if ($search = trim((string) $request->query('q', ''))) {
            $query->where('phrase', 'ilike', '%'.$search.'%');
        }

        $items = $query->limit(500)->get()->map(fn (SeoKeyword $keyword) => $this->present($keyword));

        return ApiResponse::success([
            'provider' => $this->checks->providerName(),
            'provider_configured' => $this->checks->isProviderConfigured(),
            'items' => $items,
        ]);
    }

    public function store(StoreSeoKeywordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $domain = $this->checks->domainFromUrl($validated['target_url'] ?? null);

        $keyword = SeoKeyword::query()->create([
            ...$validated,
            'created_by' => $request->user()?->id,
            'target_domain' => $domain,
            'next_check_at' => now(),
        ]);

        return ApiResponse::success($this->present($keyword), 'Keyword added.', Response::HTTP_CREATED);
    }

    public function show(SeoKeyword $seoKeyword): JsonResponse
    {
        $this->assertOrg($seoKeyword);

        $checks = $seoKeyword->checks()
            ->orderByDesc('checked_at')
            ->limit(60)
            ->get();

        return ApiResponse::success([
            'keyword' => $this->present($seoKeyword),
            'checks' => $checks,
            'provider' => $this->checks->providerName(),
            'provider_configured' => $this->checks->isProviderConfigured(),
        ]);
    }

    public function update(UpdateSeoKeywordRequest $request, SeoKeyword $seoKeyword): JsonResponse
    {
        $this->assertOrg($seoKeyword);
        $validated = $request->validated();

        if (array_key_exists('target_url', $validated)) {
            $validated['target_domain'] = $this->checks->domainFromUrl($validated['target_url']);
        }

        $seoKeyword->update($validated);

        return ApiResponse::success($this->present($seoKeyword->fresh()), 'Keyword updated.');
    }

    public function destroy(SeoKeyword $seoKeyword): JsonResponse
    {
        $this->assertOrg($seoKeyword);
        $seoKeyword->delete();

        return ApiResponse::success(null, 'Keyword deleted.');
    }

    public function check(SeoKeyword $seoKeyword): JsonResponse
    {
        $this->assertOrg($seoKeyword);

        $result = $this->checks->check($seoKeyword, 'manual');

        return ApiResponse::success([
            'keyword' => $this->present($result['keyword']),
            'check' => $result['check'],
            'provider_configured' => $this->checks->isProviderConfigured(),
        ], $result['check']->error ? 'Check finished with errors.' : 'Rank check completed.');
    }

    private function assertOrg(SeoKeyword $keyword): void
    {
        if ((string) $keyword->organization_id !== $this->organizationId()) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SeoKeyword $keyword): array
    {
        return [
            'id' => $keyword->id,
            'organization_id' => $keyword->organization_id,
            'phrase' => $keyword->phrase,
            'priority' => $keyword->priority,
            'target_url' => $keyword->target_url,
            'target_domain' => $keyword->target_domain,
            'country' => $keyword->country,
            'language' => $keyword->language,
            'device' => $keyword->device,
            'tags' => $keyword->tags ?? [],
            'notes' => $keyword->notes,
            'status' => $keyword->status,
            'current_google_position' => $keyword->current_google_position,
            'current_google_url' => $keyword->current_google_url,
            'previous_google_position' => $keyword->previous_google_position,
            'position_change' => $keyword->positionChange(),
            'current_ai_cited' => $keyword->current_ai_cited,
            'last_checked_at' => optional($keyword->last_checked_at)?->toIso8601String(),
            'next_check_at' => optional($keyword->next_check_at)?->toIso8601String(),
            'created_at' => optional($keyword->created_at)?->toIso8601String(),
            'updated_at' => optional($keyword->updated_at)?->toIso8601String(),
        ];
    }
}
