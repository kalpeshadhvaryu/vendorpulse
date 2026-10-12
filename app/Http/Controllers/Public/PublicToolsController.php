<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\RunPublicDnsCheckRequest;
use App\Models\EmailDeliverabilityTest;
use App\Services\Public\PublicWhoisService;
use App\Services\Vapt\DnsCheckService;
use App\Services\Vapt\Email\EmailDeliverabilityTestService;
use App\Support\ApiResponse;
use App\Support\PublicScanTargetValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicToolsController extends Controller
{
    public function __construct(
        private readonly DnsCheckService $dnsCheck,
        private readonly PublicWhoisService $whois,
        private readonly PublicScanTargetValidator $targetValidator,
        private readonly EmailDeliverabilityTestService $emailTests,
    ) {}

    public function home(): View
    {
        $dashboardBaseUrl = rtrim((string) config('vendorpulse.web_dashboard_url'), '/');

        return view('public.tools', [
            'dashboardLoginUrl' => $dashboardBaseUrl.'/web_dashboard/login',
            'dashboardUrl' => $dashboardBaseUrl.'/web_dashboard',
            'signUpUrl' => 'mailto:sales@veravalonline.com?subject=VendorPulse%20Sign%20Up',
            'emailTestAvailable' => $this->emailTests->isAvailable(),
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

        $report = $this->dnsCheck->scan($target, $request->validated('dkim_selector'));

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

    public function createEmailTest(Request $request): JsonResponse
    {
        if (! $this->emailTests->isAvailable()) {
            return ApiResponse::error('The email deliverability test is not available right now.', 503);
        }

        $test = $this->emailTests->create('public', null, null, $request->ip());

        return ApiResponse::success($this->emailTests->present($test), 'Email test created.', 201);
    }

    public function showEmailTest(string $token): JsonResponse
    {
        $test = EmailDeliverabilityTest::query()
            ->where('source', 'public')
            ->where('token', strtolower($token))
            ->first();

        if ($test === null) {
            return ApiResponse::error('Email test not found.', 404);
        }

        return ApiResponse::success($this->emailTests->present($this->emailTests->refresh($test)));
    }
}
