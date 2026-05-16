<?php

namespace App\Services\MarketingSeo;

use GuzzleHttp\Client;
use Illuminate\Support\Str;

class OnPageSeoAuditService
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        .'AppleWebKit/537.36 (KHTML, like Gecko) '
        .'Chrome/125.0.0.0 Safari/537.36';

    /**
     * @return array<string, mixed>
     */
    public function audit(string $targetUrl): array
    {
        $normalizedUrl = $this->normalizeTargetUrl($targetUrl);
        $html = $this->fetchHtml($normalizedUrl);

        if ($html === null) {
            return [
                'target_url' => $normalizedUrl,
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
            'target_url' => $normalizedUrl,
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

    private function normalizeTargetUrl(string $targetUrl): string
    {
        $trimmed = trim($targetUrl);

        if (! Str::startsWith($trimmed, ['http://', 'https://'])) {
            $trimmed = 'https://'.$trimmed;
        }

        return $trimmed;
    }

    private function fetchHtml(string $url): ?string
    {
        $client = new Client([
            'timeout' => 25,
            'connect_timeout' => 8,
            'http_errors' => false,
            'allow_redirects' => true,
            'verify' => true,
        ]);

        try {
            $response = $client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
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
}
