<?php

namespace App\Services\Vapt;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use Illuminate\Support\Str;

class UrlCheckerService
{
    private const CHECK_CONCURRENCY = 5;

    private const BROWSER_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        .'AppleWebKit/537.36 (KHTML, like Gecko) '
        .'Chrome/125.0.0.0 Safari/537.36';

    /**
     * @return array{
     *   target_url: string,
     *   scanned_at: string,
     *   summary: array{total_links:int,active_links:int,broken_links:int,average_response_time_ms:float},
     *   links: array<int, array{source_url:string,discovered_link:string,status_code:int|null,status:string,response_time_ms:float|null,link_type:string,error:string|null}>
     * }
     */
    public function scan(string $targetUrl, int $maxLinks = 200): array
    {
        $normalizedTargetUrl = $this->normalizeTargetUrl($targetUrl);
        $anchors = $this->extractAnchors($normalizedTargetUrl);

        if ($anchors === []) {
            return [
                'target_url' => $normalizedTargetUrl,
                'scanned_at' => now()->toIso8601String(),
                'summary' => [
                    'total_links' => 0,
                    'active_links' => 0,
                    'broken_links' => 0,
                    'average_response_time_ms' => 0.0,
                ],
                'links' => [],
            ];
        }

        $links = array_slice(array_values($anchors), 0, $maxLinks);

        return $this->checkLinksAsync($normalizedTargetUrl, $links);
    }

    private function normalizeTargetUrl(string $targetUrl): string
    {
        $trimmed = trim($targetUrl);

        if (! Str::startsWith($trimmed, ['http://', 'https://'])) {
            $trimmed = 'https://'.$trimmed;
        }

        return $trimmed;
    }

    /**
     * @return array<string, array{source_url:string,discovered_link:string,link_type:string}>
     */
    private function extractAnchors(string $targetUrl): array
    {
        $html = $this->fetchHtml($targetUrl);

        if ($html === null || $html === '') {
            return [];
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//a[@href]');

        if ($nodes === false) {
            return [];
        }

        $originHost = parse_url($targetUrl, PHP_URL_HOST);
        $results = [];

        foreach ($nodes as $node) {
            $href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
            if ($href === '') {
                continue;
            }

            if (
                Str::startsWith($href, ['javascript:', 'mailto:', 'tel:', '#'])
                || Str::startsWith($href, ['data:'])
            ) {
                continue;
            }

            $resolved = $this->resolveUrl($targetUrl, $href);
            if ($resolved === null || ! $this->isHttpUrl($resolved)) {
                continue;
            }

            $host = parse_url($resolved, PHP_URL_HOST);
            $linkType = ($originHost !== null && $host !== null && strcasecmp($originHost, $host) === 0)
                ? 'internal'
                : 'external';

            $results[$resolved] = [
                'source_url' => $targetUrl,
                'discovered_link' => $resolved,
                'link_type' => $linkType,
            ];
        }

        return $results;
    }

    private function fetchHtml(string $targetUrl): ?string
    {
        $client = new Client([
            'timeout' => 20,
            'http_errors' => false,
            'allow_redirects' => true,
            'verify' => true,
        ]);

        try {
            $response = $client->request('GET', $targetUrl, [
                'headers' => [
                    'User-Agent' => self::BROWSER_USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml',
                ],
            ]);

            $body = (string) $response->getBody();
            return $body !== '' ? $body : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, array{source_url:string,discovered_link:string,link_type:string}>  $links
     * @return array{
     *   target_url: string,
     *   scanned_at: string,
     *   summary: array{total_links:int,active_links:int,broken_links:int,average_response_time_ms:float},
     *   links: array<int, array{source_url:string,discovered_link:string,status_code:int|null,status:string,response_time_ms:float|null,link_type:string,error:string|null}>
     * }
     */
    private function checkLinksAsync(string $targetUrl, array $links): array
    {
        $client = new Client([
            'timeout' => 25,
            'connect_timeout' => 8,
            'http_errors' => false,
            'allow_redirects' => true,
            'verify' => true,
        ]);

        $latencies = [];
        $settled = [];

        $requests = function () use ($links, $client, &$latencies) {
            foreach ($links as $index => $link) {
                $url = $link['discovered_link'];

                yield $index => function () use ($client, $url, &$latencies, $index) {
                    return $client
                        ->requestAsync('HEAD', $url, $this->requestOptions($latencies, $index, false))
                        ->then(
                            function ($response) use ($client, $url, &$latencies, $index) {
                                $statusCode = $response->getStatusCode();

                                // Some servers/WAFs reject HEAD even when GET succeeds.
                                if (in_array($statusCode, [403, 405, 429], true)) {
                                    return $client->requestAsync('GET', $url, $this->requestOptions($latencies, $index, true));
                                }

                                return $response;
                            },
                            function () use ($client, $url, &$latencies, $index) {
                                return $client->requestAsync('GET', $url, $this->requestOptions($latencies, $index, true));
                            }
                        );
                };
            }
        };

        $pool = new Pool($client, $requests(), [
            'concurrency' => self::CHECK_CONCURRENCY,
            'fulfilled' => function ($response, $index) use (&$settled): void {
                $settled[(int) $index] = [
                    'state' => 'fulfilled',
                    'value' => $response,
                ];
            },
            'rejected' => function ($reason, $index) use (&$settled): void {
                $settled[(int) $index] = [
                    'state' => 'rejected',
                    'reason' => $reason,
                ];
            },
        ]);

        $pool->promise()->wait();

        $rows = [];
        $active = 0;
        $broken = 0;
        $latencySum = 0.0;
        $latencyCount = 0;

        foreach ($links as $index => $link) {
            $result = $settled[$index] ?? null;
            $statusCode = null;
            $error = null;

            if (($result['state'] ?? null) === 'fulfilled') {
                $statusCode = $result['value']->getStatusCode();
            } else {
                $reason = $result['reason'] ?? null;
                if ($reason instanceof RequestException && $reason->hasResponse()) {
                    $statusCode = $reason->getResponse()?->getStatusCode();
                }
                $error = $reason instanceof \Throwable ? $reason->getMessage() : 'Request failed';
            }

            $latency = $latencies[$index] ?? null;
            if ($latency !== null) {
                $latencySum += $latency;
                $latencyCount++;
            }

            $isActive = $statusCode !== null && $statusCode >= 200 && $statusCode < 300;
            if ($isActive) {
                $active++;
            } elseif ($statusCode === null || $statusCode >= 400) {
                $broken++;
            }

            $rows[] = [
                'source_url' => $link['source_url'],
                'discovered_link' => $link['discovered_link'],
                'status_code' => $statusCode,
                'status' => $isActive ? 'Active' : 'Broken',
                'response_time_ms' => $latency,
                'link_type' => $link['link_type'],
                'error' => $error,
            ];
        }

        return [
            'target_url' => $targetUrl,
            'scanned_at' => now()->toIso8601String(),
            'summary' => [
                'total_links' => count($rows),
                'active_links' => $active,
                'broken_links' => $broken,
                'average_response_time_ms' => $latencyCount > 0 ? round($latencySum / $latencyCount, 2) : 0.0,
            ],
            'links' => $rows,
        ];
    }

    /**
     * @param  array<int, float>  $latencies
     * @return array<string, mixed>
     */
    private function requestOptions(array &$latencies, int $index, bool $isGetFallback): array
    {
        $headers = [
            'User-Agent' => self::BROWSER_USER_AGENT,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Connection' => 'close',
        ];

        if ($isGetFallback) {
            // Keep fallback GET lightweight for big pages.
            $headers['Range'] = 'bytes=0-2048';
        }

        return [
            'headers' => $headers,
            'on_stats' => static function ($stats) use (&$latencies, $index): void {
                $seconds = $stats->getTransferTime();
                $latencies[$index] = round($seconds * 1000, 2);
            },
        ];
    }

    private function isHttpUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private function resolveUrl(string $baseUrl, string $href): ?string
    {
        if ($this->isHttpUrl($href)) {
            return $href;
        }

        $parts = parse_url($baseUrl);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = $parts['scheme'];
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $basePath = $parts['path'] ?? '/';

        if (Str::startsWith($href, '//')) {
            return $scheme.':'.$href;
        }

        if (Str::startsWith($href, '/')) {
            return $scheme.'://'.$host.$port.$href;
        }

        $directory = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
        if ($directory === '' || $directory === '.') {
            $directory = '';
        }

        return $scheme.'://'.$host.$port.$directory.'/'.$href;
    }
}
