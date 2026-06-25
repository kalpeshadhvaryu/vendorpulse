<?php

namespace App\Services\Common;

use GuzzleHttp\Client;
use Illuminate\Support\Str;

class InternalSiteCrawler
{
    private const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        .'AppleWebKit/537.36 (KHTML, like Gecko) '
        .'Chrome/125.0.0.0 Safari/537.36';

    /**
     * @return array{
     *   pages: array<int, array{url:string,depth:int,html:string|null,final_url:string|null,ok:bool}>,
     *   crawl: array{pages_crawled:int,max_pages:int,max_depth:int,stopped_reason:string,unreachable_pages:int}
     * }
     */
    public function crawl(
        string $targetUrl,
        int $maxPages = 30,
        int $maxDepth = 3,
        int $maxRuntimeSeconds = 900,
        ?string $userAgent = null,
    ): array {
        $normalizedTargetUrl = $this->normalizeTargetUrl($targetUrl);
        $startHost = parse_url($normalizedTargetUrl, PHP_URL_HOST);

        if (! is_string($startHost) || $startHost === '') {
            return [
                'pages' => [],
                'crawl' => $this->crawlMeta(0, $maxPages, $maxDepth, 'completed', 0),
            ];
        }

        $startedAt = microtime(true);
        $visited = [];
        $queue = [
            [
                'url' => $this->normalizeCrawlUrl($normalizedTargetUrl),
                'depth' => 0,
            ],
        ];
        $pages = [];
        $pagesCrawled = 0;
        $unreachablePages = 0;
        $stoppedReason = 'completed';
        $userAgent ??= self::DEFAULT_USER_AGENT;

        while ($queue !== [] && $pagesCrawled < $maxPages) {
            if (microtime(true) - $startedAt > $maxRuntimeSeconds) {
                $stoppedReason = 'timeout';
                break;
            }

            $item = array_shift($queue);
            $pageUrl = $item['url'];
            $depth = (int) $item['depth'];

            if (isset($visited[$pageUrl]) || $depth > $maxDepth) {
                continue;
            }

            $visited[$pageUrl] = true;
            $fetch = $this->fetchHtml($pageUrl, $userAgent);
            $ok = $fetch['html'] !== null && $fetch['html'] !== '';

            if (! $ok) {
                $unreachablePages++;
            }

            $pages[] = [
                'url' => $pageUrl,
                'depth' => $depth,
                'html' => $fetch['html'],
                'final_url' => $fetch['final_url'],
                'ok' => $ok,
            ];

            $pagesCrawled++;

            if ($ok) {
                $internalLinks = $this->extractInternalLinks($pageUrl, $fetch['html'], $startHost);

                foreach ($internalLinks as $internalUrl) {
                    $normalizedInternal = $this->normalizeCrawlUrl($internalUrl);
                    if (! isset($visited[$normalizedInternal]) && $this->isCrawlablePageUrl($internalUrl)) {
                        $queue[] = [
                            'url' => $normalizedInternal,
                            'depth' => $depth + 1,
                        ];
                    }
                }
            }
        }

        if ($pagesCrawled >= $maxPages && $queue !== [] && $stoppedReason === 'completed') {
            $stoppedReason = 'max_pages';
        }

        return [
            'pages' => $pages,
            'crawl' => $this->crawlMeta($pagesCrawled, $maxPages, $maxDepth, $stoppedReason, $unreachablePages),
        ];
    }

    /**
     * @return array{html:string|null,final_url:string|null}
     */
    public function fetchHtml(string $url, ?string $userAgent = null): array
    {
        $userAgent ??= self::DEFAULT_USER_AGENT;

        $client = new Client([
            'timeout' => 25,
            'connect_timeout' => 8,
            'http_errors' => false,
            'allow_redirects' => [
                'max' => 5,
                'track_redirects' => true,
            ],
            'verify' => true,
        ]);

        try {
            $response = $client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => $userAgent,
                    'Accept' => 'text/html,application/xhtml+xml',
                ],
            ]);

            $body = (string) $response->getBody();
            $redirectHistory = $response->getHeader('X-Guzzle-Redirect-History');
            $finalUrl = $redirectHistory !== [] ? (string) end($redirectHistory) : $url;

            return [
                'html' => $body !== '' ? $body : null,
                'final_url' => $finalUrl,
            ];
        } catch (\Throwable) {
            return [
                'html' => null,
                'final_url' => null,
            ];
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractInternalLinks(string $pageUrl, string $html, string $startHost): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//a[@href]');
        if ($nodes === false) {
            return [];
        }

        $links = [];
        foreach ($nodes as $node) {
            $href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
            if ($href === '' || Str::startsWith($href, ['javascript:', 'mailto:', 'tel:', '#', 'data:'])) {
                continue;
            }

            $resolved = $this->resolveUrl($pageUrl, $href);
            if ($resolved === null || ! $this->isHttpUrl($resolved)) {
                continue;
            }

            $host = parse_url($resolved, PHP_URL_HOST);
            if ($host !== null && strcasecmp($host, $startHost) === 0) {
                $links[] = $resolved;
            }
        }

        return array_values(array_unique($links));
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
     * @return array{pages_crawled:int,max_pages:int,max_depth:int,stopped_reason:string,unreachable_pages:int}
     */
    private function crawlMeta(
        int $pagesCrawled,
        int $maxPages,
        int $maxDepth,
        string $stoppedReason,
        int $unreachablePages,
    ): array {
        return [
            'pages_crawled' => $pagesCrawled,
            'max_pages' => $maxPages,
            'max_depth' => $maxDepth,
            'stopped_reason' => $stoppedReason,
            'unreachable_pages' => $unreachablePages,
        ];
    }

    private function normalizeCrawlUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$port.$path.$query;
    }

    private function isCrawlablePageUrl(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if ($extension === '') {
            return true;
        }

        $nonHtmlExtensions = [
            'pdf', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico',
            'css', 'js', 'mjs', 'map', 'zip', 'gz', 'tar', 'rar',
            'mp4', 'webm', 'mp3', 'wav', 'woff', 'woff2', 'ttf', 'eot',
            'xml', 'json', 'csv', 'txt',
        ];

        return ! in_array($extension, $nonHtmlExtensions, true);
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
