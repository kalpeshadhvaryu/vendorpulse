<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\RunPublicDnsCheckRequest;
use App\Services\Public\PublicWhoisService;
use App\Services\Vapt\DnsCheckService;
use App\Support\ApiResponse;
use App\Support\PublicScanTargetValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PublicToolsController extends Controller
{
    public function __construct(
        private readonly DnsCheckService $dnsCheck,
        private readonly PublicWhoisService $whois,
        private readonly PublicScanTargetValidator $targetValidator,
    ) {}

    public function home(): View
    {
        $dashboardBaseUrl = rtrim((string) config('vendorpulse.web_dashboard_url'), '/');

        return view('public.tools', [
            'dashboardLoginUrl' => $dashboardBaseUrl.'/web_dashboard/login',
            'dashboardUrl' => $dashboardBaseUrl.'/web_dashboard',
            'signUpUrl' => 'mailto:sales@veravalonline.com?subject=VendorPulse%20Sign%20Up',
        ]);
    }

    public function privacy(): View
    {
        $dashboardBaseUrl = rtrim((string) config('vendorpulse.web_dashboard_url'), '/');

        return view('public.privacy', [
            'dashboardLoginUrl' => $dashboardBaseUrl.'/web_dashboard/login',
            'dashboardUrl' => $dashboardBaseUrl.'/web_dashboard',
            'signUpUrl' => 'mailto:sales@veravalonline.com?subject=VendorPulse%20Sign%20Up',
        ]);
    }

    public function dnsCheck(RunPublicDnsCheckRequest $request): JsonResponse
    {
        $target = (string) $request->validated('target');

        if (! $this->targetValidator->isAllowed($target)) {
            return ApiResponse::error(
                'Provide a public domain name. Private networks, localhost, and reserved targets are not allowed.',
                422,
            );
        }

        $report = $this->dnsCheck->scan($target);

        return ApiResponse::success($report, 'DNS check completed.');
    }

    public function whoisCheck(RunPublicDnsCheckRequest $request): JsonResponse
    {
        $target = (string) $request->validated('target');

        if (! $this->targetValidator->isAllowed($target)) {
            return ApiResponse::error(
                'Provide a public domain name. Private networks, localhost, and reserved targets are not allowed.',
                422,
            );
        }

        $report = $this->whois->lookup($target);

        if (! $report['found']) {
            return ApiResponse::error(
                'WHOIS/RDAP data was not found for that domain. Check the spelling or try again shortly.',
                404,
            );
        }

        return ApiResponse::success($report, 'WHOIS check completed.');
    }
}
