<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use InvalidArgumentException;

class BrandedPdfReportService
{
    /** @var list<string> */
    public const TYPES = [
        'port-checker',
        'url-checker',
        'dns-check',
        'website-speedtest',
        'vapt',
        'on-page-seo',
        'security-audit',
    ];

    /**
     * @param  array<string, mixed>  $report
     * @param  array{subtitle?: string|null, organization?: string|null, target?: string|null}  $meta
     */
    public function download(string $type, array $report, array $meta = [], ?string $fileName = null): Response
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported report type: '.$type);
        }

        $titles = [
            'port-checker' => 'Port Checker Report',
            'url-checker' => 'Web Health / Link Auditor Report',
            'dns-check' => 'DNS & Email Security Report',
            'website-speedtest' => 'Website Speedtest Report',
            'vapt' => 'VAPT / Experience Monitoring Report',
            'on-page-seo' => 'On-Page SEO Audit Report',
            'security-audit' => 'Website Security Audit Report',
        ];

        $logo = $this->logoAsset();

        $pdf = Pdf::loadView('reports.pdf.'.$type, [
            'report' => $report,
            'meta' => [
                'title' => $titles[$type],
                'subtitle' => $meta['subtitle'] ?? null,
                'organization' => $meta['organization'] ?? null,
                'target' => $meta['target'] ?? $this->guessTarget($report),
                'generated_at' => now()->timezone(config('app.timezone', 'UTC'))->format('d M Y H:i T'),
                'app_name' => (string) config('app.name', 'VendorPulse'),
                'logo_data_uri' => $logo['data_uri'] ?? null,
                'logo_path' => $logo['path'] ?? null,
                'logo_is_wordmark' => (bool) ($logo['is_wordmark'] ?? false),
            ],
        ])->setPaper('a4', 'portrait');

        $pdf->setOption([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
            'chroot' => base_path(),
        ]);

        $safeName = $fileName ?: $type.'-'.now()->format('Ymd-His').'.pdf';

        return $pdf->download($safeName);
    }

    /**
     * DomPDF requires PHP GD to embed PNG/JPEG. Prefer the brand wordmark assets.
     *
     * @return array{data_uri:?string,path:?string,is_wordmark:bool}
     */
    private function logoAsset(): array
    {
        $candidates = [
            'vendorpulse-logo-pdf.jpg',
            'vendorpulse-logo-pdf.png',
            'vendorpulse-logo.png',
        ];

        if (! extension_loaded('gd')) {
            return ['data_uri' => null, 'path' => null, 'is_wordmark' => false];
        }

        foreach ($candidates as $file) {
            $path = public_path($file);
            if (! is_file($path)) {
                continue;
            }

            $bytes = file_get_contents($path);
            if ($bytes === false || $bytes === '') {
                continue;
            }

            $mime = match (true) {
                str_ends_with($file, '.jpg'), str_ends_with($file, '.jpeg') => 'image/jpeg',
                default => 'image/png',
            };

            return [
                // Data URIs are the most reliable DomPDF path (no chroot/path issues).
                'data_uri' => 'data:'.$mime.';base64,'.base64_encode($bytes),
                'path' => $path,
                'is_wordmark' => true,
            ];
        }

        return ['data_uri' => null, 'path' => null, 'is_wordmark' => false];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function guessTarget(array $report): ?string
    {
        foreach (['target', 'target_url', 'host', 'domain', 'name', 'final_url'] as $key) {
            if (! empty($report[$key]) && is_scalar($report[$key])) {
                return (string) $report[$key];
            }
        }

        if (! empty($report['test']['name']) && is_scalar($report['test']['name'])) {
            return (string) $report['test']['name'];
        }

        return null;
    }
}
