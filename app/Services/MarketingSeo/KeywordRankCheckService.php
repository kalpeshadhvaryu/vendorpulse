<?php

namespace App\Services\MarketingSeo;

use App\Models\SeoKeyword;
use App\Models\SeoKeywordCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class KeywordRankCheckService
{
    public function isProviderConfigured(): bool
    {
        $provider = strtolower((string) config('seo-keywords.provider', 'none'));

        return match ($provider) {
            'serpapi' => trim((string) config('seo-keywords.serpapi.key')) !== '',
            'google_cse' => trim((string) config('seo-keywords.google_cse.key')) !== ''
                && trim((string) config('seo-keywords.google_cse.cx')) !== '',
            default => false,
        };
    }

    public function providerName(): string
    {
        return strtolower((string) config('seo-keywords.provider', 'none'));
    }

    /**
     * @return array{keyword: SeoKeyword, check: SeoKeywordCheck}
     */
    public function check(SeoKeyword $keyword, string $source = 'manual'): array
    {
        $provider = $this->providerName();
        $checkedAt = now();
        $googlePosition = null;
        $googleUrl = null;
        $aiResults = $this->emptyAiResults();
        $meta = ['provider' => $provider];
        $error = null;

        try {
            if (! $this->isProviderConfigured()) {
                throw new RuntimeException(
                    'No rank provider is configured. Set SEO_KEYWORDS_PROVIDER=serpapi (with SERPAPI_KEY) '
                    .'or SEO_KEYWORDS_PROVIDER=google_cse (with GOOGLE_CSE_API_KEY and GOOGLE_CSE_CX).'
                );
            }

            $result = match ($provider) {
                'serpapi' => $this->checkViaSerpApi($keyword),
                'google_cse' => $this->checkViaGoogleCse($keyword),
                default => throw new RuntimeException('Unsupported SEO_KEYWORDS_PROVIDER: '.$provider),
            };

            $googlePosition = $result['google_position'];
            $googleUrl = $result['google_url'];
            $aiResults = $result['ai_results'];
            $meta = array_merge($meta, $result['meta'] ?? []);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $check = SeoKeywordCheck::query()->create([
            'seo_keyword_id' => $keyword->id,
            'organization_id' => $keyword->organization_id,
            'checked_at' => $checkedAt,
            'source' => $source,
            'provider' => $provider,
            'google_position' => $googlePosition,
            'google_url' => $googleUrl,
            'ai_results' => $aiResults,
            'meta' => $meta,
            'error' => $error,
        ]);

        if ($error === null) {
            $keyword->previous_google_position = $keyword->current_google_position;
            $keyword->current_google_position = $googlePosition;
            $keyword->current_google_url = $googleUrl;
            $keyword->current_ai_cited = collect($aiResults)->contains(fn ($row) => ($row['cited'] ?? null) === true);
            $keyword->last_checked_at = $checkedAt;
            $keyword->next_check_at = $this->nextCheckAt($keyword->priority, $checkedAt);
            $keyword->save();
        } else {
            // Still push next_check so a failed check does not retry every minute forever
            $keyword->last_checked_at = $checkedAt;
            $keyword->next_check_at = $checkedAt->copy()->addHours(6);
            $keyword->save();
        }

        return ['keyword' => $keyword->fresh(), 'check' => $check];
    }

    public function nextCheckAt(string $priority, $from = null)
    {
        $from = $from ? Carbon::parse($from) : now();
        $days = (int) config('seo-keywords.check_interval_days.'.strtolower($priority), 3);

        return $from->copy()->addDays(max(1, $days));
    }

    public function domainFromUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $host = parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        return strtolower(preg_replace('/^www\./i', '', $host) ?? $host);
    }

    /**
     * @return array{google_position:?int, google_url:?string, ai_results:array<int,array<string,mixed>>, meta:array<string,mixed>}
     */
    private function checkViaSerpApi(SeoKeyword $keyword): array
    {
        $key = (string) config('seo-keywords.serpapi.key');
        $endpoint = (string) config('seo-keywords.serpapi.endpoint');
        $timeout = (int) config('seo-keywords.http_timeout_seconds', 20);

        $response = Http::timeout($timeout)->get($endpoint, [
            'engine' => 'google',
            'q' => $keyword->phrase,
            'gl' => strtolower($keyword->country),
            'hl' => $keyword->language,
            'device' => $keyword->device === 'mobile' ? 'mobile' : 'desktop',
            'num' => min(100, (int) config('seo-keywords.max_organic_results', 100)),
            'api_key' => $key,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('SerpAPI request failed (HTTP '.$response->status().').');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('SerpAPI returned an invalid response.');
        }

        $organic = is_array($payload['organic_results'] ?? null) ? $payload['organic_results'] : [];
        [$position, $url] = $this->matchOrganic($organic, $keyword);

        $aiResults = $this->emptyAiResults();
        $aiOverview = $payload['ai_overview'] ?? $payload['answer_box'] ?? null;
        $aiResults = $this->markEngine(
            $aiResults,
            'google_ai_overview',
            $this->textCitesBrand($aiOverview, $keyword),
            is_array($aiOverview) ? json_encode($aiOverview) : (is_string($aiOverview) ? $aiOverview : null)
        );

        return [
            'google_position' => $position,
            'google_url' => $url,
            'ai_results' => $aiResults,
            'meta' => [
                'organic_count' => count($organic),
                'has_ai_overview' => isset($payload['ai_overview']),
            ],
        ];
    }

    /**
     * @return array{google_position:?int, google_url:?string, ai_results:array<int,array<string,mixed>>, meta:array<string,mixed>}
     */
    private function checkViaGoogleCse(SeoKeyword $keyword): array
    {
        $key = (string) config('seo-keywords.google_cse.key');
        $cx = (string) config('seo-keywords.google_cse.cx');
        $endpoint = (string) config('seo-keywords.google_cse.endpoint');
        $timeout = (int) config('seo-keywords.http_timeout_seconds', 20);

        $response = Http::timeout($timeout)->get($endpoint, [
            'key' => $key,
            'cx' => $cx,
            'q' => $keyword->phrase,
            'gl' => strtolower($keyword->country),
            'hl' => $keyword->language,
            'num' => 10,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google CSE request failed (HTTP '.$response->status().').');
        }

        $payload = $response->json();
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $organic = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            $organic[] = [
                'position' => $index + 1,
                'link' => $item['link'] ?? null,
            ];
        }

        [$position, $url] = $this->matchOrganic($organic, $keyword);

        return [
            'google_position' => $position,
            'google_url' => $url,
            'ai_results' => $this->emptyAiResults('Google CSE only covers roughly the first page and does not include AI engines.'),
            'meta' => [
                'organic_count' => count($organic),
                'note' => 'google_cse_top10_only',
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $organic
     * @return array{0:?int, 1:?string}
     */
    private function matchOrganic(array $organic, SeoKeyword $keyword): array
    {
        $targets = array_values(array_filter([
            $keyword->target_domain,
            $this->domainFromUrl($keyword->target_url),
        ]));

        if ($targets === []) {
            return [null, null];
        }

        foreach ($organic as $row) {
            if (! is_array($row)) {
                continue;
            }
            $link = (string) ($row['link'] ?? $row['url'] ?? '');
            $host = $this->domainFromUrl($link);
            if ($host === null) {
                continue;
            }

            foreach ($targets as $target) {
                if ($host === $target || Str::endsWith($host, '.'.$target)) {
                    $position = isset($row['position']) ? (int) $row['position'] : null;

                    return [$position, $link !== '' ? $link : null];
                }
            }
        }

        return [null, null];
    }

    /**
     * @return array<int, array{engine:string, cited:?bool, evidence:?string, status:string}>
     */
    private function emptyAiResults(?string $note = null): array
    {
        $engines = (array) config('seo-keywords.ai_engines', []);
        $rows = [];
        foreach ($engines as $engine) {
            $rows[] = [
                'engine' => (string) $engine,
                'cited' => null,
                'evidence' => $note,
                'status' => 'not_checked',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array{engine:string, cited:?bool, evidence:?string, status:string}>  $rows
     * @return array<int, array{engine:string, cited:?bool, evidence:?string, status:string}>
     */
    private function markEngine(array $rows, string $engine, ?bool $cited, ?string $evidence): array
    {
        foreach ($rows as &$row) {
            if ($row['engine'] === $engine) {
                $row['cited'] = $cited;
                $row['evidence'] = $evidence !== null ? Str::limit($evidence, 500) : null;
                $row['status'] = $cited === null ? 'unknown' : ($cited ? 'cited' : 'not_cited');
            }
        }

        return $rows;
    }

    private function textCitesBrand(mixed $payload, SeoKeyword $keyword): ?bool
    {
        if ($payload === null) {
            return null;
        }

        $haystack = strtolower(is_string($payload) ? $payload : json_encode($payload));
        $needles = array_values(array_filter([
            $keyword->target_domain,
            $this->domainFromUrl($keyword->target_url),
        ]));

        if ($needles === [] || $haystack === '' || $haystack === 'false' || $haystack === 'null') {
            return null;
        }

        foreach ($needles as $needle) {
            if (str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
