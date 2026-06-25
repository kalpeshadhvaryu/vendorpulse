<?php

namespace App\Services\MarketingSeo;

use App\Services\Common\InternalSiteCrawler;
use Illuminate\Support\Str;

class OnPageSeoAuditService
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        .'AppleWebKit/537.36 (KHTML, like Gecko) '
        .'Chrome/125.0.0.0 Safari/537.36';

    public function __construct(
        private readonly InternalSiteCrawler $siteCrawler,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function audit(string $targetUrl): array
    {
        $normalizedUrl = $this->normalizeTargetUrl($targetUrl);
        $fetch = $this->siteCrawler->fetchHtml($normalizedUrl, self::USER_AGENT);

        if ($fetch['html'] === null) {
            return $this->fetchFailedReport($normalizedUrl, 'quick');
        }

        $report = $this->auditPage(
            $normalizedUrl,
            $fetch['html'],
            $fetch['final_url'] ?? $normalizedUrl,
        );
        $report['mode'] = 'quick';

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    public function crawlSite(string $targetUrl, int $maxPages = 30, int $maxDepth = 3): array
    {
        $normalizedUrl = $this->normalizeTargetUrl($targetUrl);
        $crawlResult = $this->siteCrawler->crawl(
            $normalizedUrl,
            $maxPages,
            $maxDepth,
            900,
            self::USER_AGENT,
        );

        $pageReports = [];
        $issueRollups = [];
        $scoreSum = 0;
        $scoredPages = 0;
        $lowestScore = null;
        $lowestUrl = null;
        $totalCritical = 0;
        $totalWarnings = 0;

        foreach ($crawlResult['pages'] as $page) {
            if (! $page['ok'] || $page['html'] === null) {
                $pageReports[] = [
                    'url' => $page['url'],
                    'overall_score' => 0,
                    'totals' => ['passed' => 0, 'warnings' => 0, 'critical' => 1],
                    'top_critical' => 'Page fetch failed',
                    'unreachable' => true,
                ];
                $totalCritical++;
                $this->rollupIssue($issueRollups, 'fetch-failed', 'Page fetch failed', $page['url']);

                continue;
            }

            $pageReport = $this->auditPage(
                $page['url'],
                $page['html'],
                $page['final_url'] ?? $page['url'],
            );

            $score = (int) $pageReport['overall_score'];
            $scoreSum += $score;
            $scoredPages++;
            $totalCritical += (int) $pageReport['totals']['critical'];
            $totalWarnings += (int) $pageReport['totals']['warnings'];

            if ($lowestScore === null || $score < $lowestScore) {
                $lowestScore = $score;
                $lowestUrl = $page['url'];
            }

            foreach (array_merge($pageReport['critical_fixes'], $pageReport['warnings']) as $item) {
                $this->rollupIssue($issueRollups, $item['key'], $item['title'], $page['url']);
            }

            $topCritical = $pageReport['critical_fixes'][0]['title'] ?? null;

            $pageReports[] = [
                'url' => $page['url'],
                'overall_score' => $score,
                'totals' => $pageReport['totals'],
                'top_critical' => $topCritical,
                'unreachable' => false,
            ];
        }

        usort($issueRollups, static fn (array $a, array $b): int => $b['page_count'] <=> $a['page_count']);

        $averageScore = $scoredPages > 0 ? (int) round($scoreSum / $scoredPages) : 0;

        return [
            'target_url' => $normalizedUrl,
            'mode' => 'site_crawl',
            'scanned_at' => now()->toIso8601String(),
            'overall_score' => $averageScore,
            'crawl' => $crawlResult['crawl'],
            'site_summary' => [
                'average_score' => $averageScore,
                'lowest_score' => $lowestScore ?? 0,
                'lowest_url' => $lowestUrl,
                'total_critical' => $totalCritical,
                'total_warnings' => $totalWarnings,
            ],
            'issue_rollups' => array_values($issueRollups),
            'pages' => $pageReports,
            'totals' => [
                'passed' => 0,
                'warnings' => $totalWarnings,
                'critical' => $totalCritical,
            ],
            'passed_audits' => [],
            'warnings' => [],
            'critical_fixes' => [],
            'metrics' => [
                'pages_audited' => count($pageReports),
            ],
            'extracted_values' => [
                'title' => null,
                'meta_description' => null,
                'headings' => ['h1' => [], 'h2' => [], 'h3' => []],
                'open_graph' => [],
            ],
        ];
    }

    /**
     * @param  array<string, array{key:string,title:string,page_count:int,urls:array<int,string>}>  $rollups
     */
    private function rollupIssue(array &$rollups, string $key, string $title, string $url): void
    {
        if (! isset($rollups[$key])) {
            $rollups[$key] = [
                'key' => $key,
                'title' => $title,
                'page_count' => 0,
                'urls' => [],
            ];
        }

        if (! in_array($url, $rollups[$key]['urls'], true)) {
            $rollups[$key]['urls'][] = $url;
            $rollups[$key]['page_count']++;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPage(string $targetUrl, string $html, string $finalUrl): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $passed = [];
        $warnings = [];
        $critical = [];

        $title = trim((string) $xpath->evaluate('string(//title[1])'));
        $titleLength = Str::length($title);

        if ($title === '') {
            $critical[] = $this->item('title-missing', 'Missing title tag', 'No <title> tag content found.', 'Add a unique page title under 60 characters.');
        } elseif ($titleLength > 60) {
            $warnings[] = $this->item('title-too-long', 'Title too long', "Title has {$titleLength} characters.", 'Keep title length to 50-60 characters for search result clarity.');
        } else {
            $passed[] = $this->item('title-length-ok', 'Title length looks good', "Title has {$titleLength} characters.", null);
        }

        $metaDescription = trim((string) $xpath->evaluate("string(//meta[translate(@name,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='description'][1]/@content)"));
        $descriptionLength = Str::length($metaDescription);

        if ($metaDescription === '') {
            $critical[] = $this->item('meta-description-missing', 'Meta description missing', 'No meta description tag found.', 'Add a compelling meta description under 160 characters.');
        } elseif ($descriptionLength > 160) {
            $warnings[] = $this->item('meta-description-too-long', 'Meta description too long', "Description has {$descriptionLength} characters.", 'Trim meta description to 120-160 characters.');
        } else {
            $passed[] = $this->item('meta-description-ok', 'Meta description length looks good', "Description has {$descriptionLength} characters.", null);
        }

        $canonical = trim((string) $xpath->evaluate("string(//link[translate(@rel,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='canonical'][1]/@href)"));
        if ($canonical === '') {
            $warnings[] = $this->item('canonical-missing', 'Canonical tag missing', 'No canonical link element found.', 'Add a canonical URL to reduce duplicate content risk.');
        } else {
            $passed[] = $this->item('canonical-present', 'Canonical tag found', "Canonical points to {$canonical}.", null);
        }

        $robotsMeta = strtolower(trim((string) $xpath->evaluate("string(//meta[translate(@name,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='robots'][1]/@content)")));
        if (str_contains($robotsMeta, 'noindex')) {
            $warnings[] = $this->item('robots-noindex', 'Page marked noindex', 'Robots meta tag includes noindex.', 'Remove noindex if this page should appear in search results.');
        } elseif ($robotsMeta === '') {
            $passed[] = $this->item('robots-default', 'No restrictive robots meta tag', 'Page is not explicitly blocked by robots meta.', null);
        } else {
            $passed[] = $this->item('robots-present', 'Robots meta tag present', "Robots directive: {$robotsMeta}.", null);
        }

        $viewport = trim((string) $xpath->evaluate("string(//meta[translate(@name,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='viewport'][1]/@content)"));
        if ($viewport === '') {
            $critical[] = $this->item('viewport-missing', 'Viewport meta missing', 'No viewport meta tag found.', 'Add a mobile viewport meta tag for responsive SEO.');
        } else {
            $passed[] = $this->item('viewport-present', 'Viewport meta found', 'Mobile viewport meta tag is present.', null);
        }

        $htmlLang = trim((string) $xpath->evaluate('string(//html/@lang)'));
        if ($htmlLang === '') {
            $warnings[] = $this->item('html-lang-missing', 'HTML lang attribute missing', 'The <html> element has no lang attribute.', 'Add lang="en" (or the correct locale) on the html element.');
        } else {
            $passed[] = $this->item('html-lang-present', 'HTML lang attribute found', "Document language is {$htmlLang}.", null);
        }

        $jsonLdNodes = $xpath->query("//script[@type='application/ld+json']");
        $hasJsonLd = $jsonLdNodes !== false && $jsonLdNodes->length > 0;
        if (! $hasJsonLd) {
            $warnings[] = $this->item('json-ld-missing', 'Structured data not found', 'No JSON-LD script blocks detected.', 'Add structured data where relevant for rich results.');
        } else {
            $passed[] = $this->item('json-ld-present', 'Structured data found', "Found {$jsonLdNodes->length} JSON-LD block(s).", null);
        }

        $faviconHref = trim((string) $xpath->evaluate("string((//link[contains(translate(@rel,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'icon')])[1]/@href)"));
        if ($faviconHref === '') {
            $warnings[] = $this->item('favicon-missing', 'Favicon not found', 'No favicon link tag detected.', 'Add a favicon for brand trust in browser tabs and search contexts.');
        } else {
            $passed[] = $this->item('favicon-present', 'Favicon found', 'A favicon link tag is present.', null);
        }

        $scheme = strtolower((string) parse_url($finalUrl, PHP_URL_SCHEME));
        if ($scheme !== 'https') {
            $critical[] = $this->item('https-missing', 'Page not served over HTTPS', "Final URL uses {$scheme}://.", 'Serve the page over HTTPS and redirect HTTP traffic.');
        } else {
            $passed[] = $this->item('https-present', 'HTTPS enabled', 'Final URL is served over HTTPS.', null);
        }

        $h1Count = (int) $xpath->evaluate('count(//h1)');
        $h2Count = (int) $xpath->evaluate('count(//h2)');
        $h3Count = (int) $xpath->evaluate('count(//h3)');
        $h1Texts = $this->headingTexts($xpath, 'h1');
        $h2Texts = $this->headingTexts($xpath, 'h2');
        $h3Texts = $this->headingTexts($xpath, 'h3');

        if ($h1Count === 1) {
            $passed[] = $this->item('h1-count-ok', 'Exactly one H1 tag found', 'Heading structure starts with one primary heading.', null);
        } elseif ($h1Count === 0) {
            $critical[] = $this->item('h1-missing', 'H1 heading missing', 'No H1 heading found on the page.', 'Add one primary H1 heading reflecting the page topic.');
        } else {
            $warnings[] = $this->item('h1-multiple', 'Multiple H1 headings found', "Found {$h1Count} H1 tags.", 'Use exactly one H1 and demote other major headings to H2/H3.');
        }

        $h2Position = $xpath->query('//h2[1]')?->item(0)?->getLineNo() ?? null;
        $firstH3 = $xpath->query('//h3[1]')?->item(0);
        $h3Position = $firstH3?->getLineNo() ?? null;

        if ($h3Count > 0 && $h2Count === 0) {
            $warnings[] = $this->item('h3-without-h2', 'H3 used without H2', 'H3 headings exist but no H2 heading found.', 'Introduce H2 sections before using H3 sub-sections.');
        } elseif ($h3Count > 0 && $h2Position !== null && $h3Position !== null && $h3Position < $h2Position) {
            $warnings[] = $this->item('heading-order-warning', 'H3 appears before first H2', 'Detected heading hierarchy issue in page order.', 'Ensure heading flow follows H1 > H2 > H3 order.');
        } else {
            $passed[] = $this->item('heading-hierarchy-ok', 'Heading hierarchy looks valid', "Detected H1: {$h1Count}, H2: {$h2Count}, H3: {$h3Count}.", null);
        }

        $imageNodes = $xpath->query('//img');
        $imagesTotal = $imageNodes?->length ?? 0;
        $missingAlt = 0;

        if ($imageNodes !== false && $imageNodes !== null) {
            foreach ($imageNodes as $node) {
                $alt = trim((string) $node->attributes?->getNamedItem('alt')?->nodeValue);
                if ($alt === '') {
                    $missingAlt++;
                }
            }
        }

        $missingAltPercent = $imagesTotal > 0 ? round(($missingAlt / $imagesTotal) * 100, 2) : 0.0;

        if ($imagesTotal === 0) {
            $passed[] = $this->item('images-none', 'No images found', 'No image alt audit required for this page.', null);
        } elseif ($missingAlt === 0) {
            $passed[] = $this->item('images-alt-complete', 'All images have alt text', "Audited {$imagesTotal} images, all include alt attributes.", null);
        } elseif ($missingAltPercent > 50) {
            $critical[] = $this->item('images-alt-critical', 'High missing alt ratio', "{$missingAlt} of {$imagesTotal} images are missing alt ({$missingAltPercent}%).", 'Add descriptive alt text to critical images used for content and context.');
        } else {
            $warnings[] = $this->item('images-alt-warning', 'Some images missing alt text', "{$missingAlt} of {$imagesTotal} images are missing alt ({$missingAltPercent}%).", 'Add alt text for accessibility and image SEO value.');
        }

        $originHost = parse_url($targetUrl, PHP_URL_HOST);
        $internalLinkCount = 0;
        $anchorNodes = $xpath->query('//a[@href]');
        if ($anchorNodes !== false) {
            foreach ($anchorNodes as $node) {
                $href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
                if ($href === '' || Str::startsWith($href, ['javascript:', 'mailto:', 'tel:', '#', 'data:'])) {
                    continue;
                }
                $resolved = $this->resolveUrl($targetUrl, $href);
                $host = $resolved !== null ? parse_url($resolved, PHP_URL_HOST) : null;
                if ($originHost !== null && $host !== null && strcasecmp($originHost, $host) === 0) {
                    $internalLinkCount++;
                }
            }
        }

        if ($internalLinkCount === 0) {
            $warnings[] = $this->item('internal-links-none', 'No internal links found', 'Page has no crawlable internal links.', 'Add internal navigation links to help users and search engines.');
        } else {
            $passed[] = $this->item('internal-links-present', 'Internal links found', "Found {$internalLinkCount} internal links.", null);
        }

        $bodyText = trim(preg_replace('/\s+/u', ' ', (string) $xpath->evaluate('string(//body)')) ?? '');
        $wordCount = $bodyText === '' ? 0 : count(preg_split('/\s+/u', $bodyText, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($wordCount < 300) {
            $warnings[] = $this->item('thin-content', 'Thin content detected', "Body text is approximately {$wordCount} words.", 'Expand page content with useful, unique information where appropriate.');
        } else {
            $passed[] = $this->item('content-length-ok', 'Content length looks adequate', "Body text is approximately {$wordCount} words.", null);
        }

        $ogTitle = $this->metaContent($xpath, 'property', 'og:title');
        $ogDescription = $this->metaContent($xpath, 'property', 'og:description');
        $ogImage = $this->metaContent($xpath, 'property', 'og:image');
        $twitterTitle = $this->metaContent($xpath, 'name', 'twitter:title');
        $twitterDescription = $this->metaContent($xpath, 'name', 'twitter:description');
        $twitterImage = $this->metaContent($xpath, 'name', 'twitter:image');

        $missingOg = [];
        if ($ogTitle === '') {
            $missingOg[] = 'og:title';
        }
        if ($ogDescription === '') {
            $missingOg[] = 'og:description';
        }
        if ($ogImage === '') {
            $missingOg[] = 'og:image';
        }

        if ($missingOg === []) {
            $passed[] = $this->item('og-core-ok', 'Open Graph core tags found', 'og:title, og:description, and og:image are present.', null);
        } else {
            $warnings[] = $this->item('og-core-missing', 'Missing Open Graph tags', 'Missing: '.implode(', ', $missingOg).'.', 'Add missing Open Graph tags for better social sharing previews.');
        }

        $missingTwitter = [];
        if ($twitterTitle === '') {
            $missingTwitter[] = 'twitter:title';
        }
        if ($twitterDescription === '') {
            $missingTwitter[] = 'twitter:description';
        }
        if ($twitterImage === '') {
            $missingTwitter[] = 'twitter:image';
        }

        if ($missingTwitter === []) {
            $passed[] = $this->item('twitter-tags-ok', 'Twitter tags found', 'twitter:title, twitter:description, and twitter:image are present.', null);
        } else {
            $warnings[] = $this->item('twitter-tags-missing', 'Missing Twitter meta tags', 'Missing: '.implode(', ', $missingTwitter).'.', 'Add Twitter cards metadata for richer link previews on X/Twitter.');
        }

        $score = max(0, min(100, (count($passed) * 10) + (count($warnings) * 4) - (count($critical) * 12) + 40));

        return [
            'target_url' => $targetUrl,
            'scanned_at' => now()->toIso8601String(),
            'overall_score' => (int) $score,
            'totals' => [
                'passed' => count($passed),
                'warnings' => count($warnings),
                'critical' => count($critical),
            ],
            'passed_audits' => $passed,
            'warnings' => $warnings,
            'critical_fixes' => $critical,
            'metrics' => [
                'title_length' => $title === '' ? null : $titleLength,
                'meta_description_length' => $metaDescription === '' ? null : $descriptionLength,
                'h1_count' => $h1Count,
                'h2_count' => $h2Count,
                'h3_count' => $h3Count,
                'images_total' => $imagesTotal,
                'images_missing_alt' => $missingAlt,
                'images_missing_alt_percent' => $missingAltPercent,
                'internal_link_count' => $internalLinkCount,
                'word_count' => $wordCount,
                'has_json_ld' => $hasJsonLd,
                'open_graph_present' => [
                    'og:title' => $ogTitle !== '',
                    'og:description' => $ogDescription !== '',
                    'og:image' => $ogImage !== '',
                    'twitter:title' => $twitterTitle !== '',
                    'twitter:description' => $twitterDescription !== '',
                    'twitter:image' => $twitterImage !== '',
                ],
            ],
            'extracted_values' => [
                'title' => $title !== '' ? $title : null,
                'meta_description' => $metaDescription !== '' ? $metaDescription : null,
                'canonical' => $canonical !== '' ? $canonical : null,
                'robots' => $robotsMeta !== '' ? $robotsMeta : null,
                'viewport' => $viewport !== '' ? $viewport : null,
                'html_lang' => $htmlLang !== '' ? $htmlLang : null,
                'favicon' => $faviconHref !== '' ? $faviconHref : null,
                'final_url' => $finalUrl,
                'headings' => [
                    'h1' => $h1Texts,
                    'h2' => $h2Texts,
                    'h3' => $h3Texts,
                ],
                'open_graph' => [
                    'og:title' => $ogTitle !== '' ? $ogTitle : null,
                    'og:description' => $ogDescription !== '' ? $ogDescription : null,
                    'og:image' => $ogImage !== '' ? $ogImage : null,
                    'twitter:title' => $twitterTitle !== '' ? $twitterTitle : null,
                    'twitter:description' => $twitterDescription !== '' ? $twitterDescription : null,
                    'twitter:image' => $twitterImage !== '' ? $twitterImage : null,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchFailedReport(string $normalizedUrl, string $mode): array
    {
        return [
            'target_url' => $normalizedUrl,
            'mode' => $mode,
            'scanned_at' => now()->toIso8601String(),
            'overall_score' => 0,
            'totals' => [
                'passed' => 0,
                'warnings' => 0,
                'critical' => 1,
            ],
            'passed_audits' => [],
            'warnings' => [],
            'critical_fixes' => [
                [
                    'key' => 'fetch-failed',
                    'title' => 'Page fetch failed',
                    'message' => 'Unable to download HTML from the target URL for SEO analysis.',
                    'suggestion' => 'Verify the URL is public, reachable, and not blocked by firewall rules.',
                ],
            ],
            'metrics' => [
                'title_length' => null,
                'meta_description_length' => null,
                'h1_count' => 0,
                'h2_count' => 0,
                'h3_count' => 0,
                'images_total' => 0,
                'images_missing_alt' => 0,
                'images_missing_alt_percent' => 0,
                'internal_link_count' => 0,
                'word_count' => 0,
                'has_json_ld' => false,
                'open_graph_present' => [
                    'og:title' => false,
                    'og:description' => false,
                    'og:image' => false,
                    'twitter:title' => false,
                    'twitter:description' => false,
                    'twitter:image' => false,
                ],
            ],
            'extracted_values' => [
                'title' => null,
                'meta_description' => null,
                'canonical' => null,
                'robots' => null,
                'viewport' => null,
                'html_lang' => null,
                'favicon' => null,
                'final_url' => null,
                'headings' => [
                    'h1' => [],
                    'h2' => [],
                    'h3' => [],
                ],
                'open_graph' => [
                    'og:title' => null,
                    'og:description' => null,
                    'og:image' => null,
                    'twitter:title' => null,
                    'twitter:description' => null,
                    'twitter:image' => null,
                ],
            ],
        ];
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
     * @return array<string, string|null>
     */
    private function item(string $key, string $title, string $message, ?string $suggestion): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'message' => $message,
            'suggestion' => $suggestion,
        ];
    }

    private function metaContent(\DOMXPath $xpath, string $attrName, string $attrValue): string
    {
        $query = sprintf(
            "string(//meta[translate(@%s,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='%s'][1]/@content)",
            $attrName,
            strtolower($attrValue),
        );

        return trim((string) $xpath->evaluate($query));
    }

    /**
     * @return array<int, string>
     */
    private function headingTexts(\DOMXPath $xpath, string $tag): array
    {
        $nodes = $xpath->query("//{$tag}");
        if ($nodes === false || $nodes === null) {
            return [];
        }

        $values = [];
        foreach ($nodes as $node) {
            $text = trim((string) $node->textContent);
            if ($text !== '') {
                $values[] = $text;
            }
        }

        return $values;
    }

    private function resolveUrl(string $baseUrl, string $href): ?string
    {
        if (Str::startsWith($href, ['http://', 'https://'])) {
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
