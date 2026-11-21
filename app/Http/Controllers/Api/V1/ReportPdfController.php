<?php

namespace App\Http\Controllers\Api\V1;

use App\ExperienceMonitoring\Services\ExperienceMonitoringService;
use App\Models\ExperienceMonitoringTest;
use App\Models\Organization;
use App\Services\Reports\BrandedPdfReportService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class ReportPdfController extends BaseApiController
{
    public function __construct(
        private readonly BrandedPdfReportService $pdf,
        private readonly ExperienceMonitoringService $experienceMonitoring,
    ) {}

    public function store(Request $request, string $type): Response|JsonResponse
    {
        if (! in_array($type, BrandedPdfReportService::TYPES, true)) {
            return ApiResponse::error('Unsupported report type.', SymfonyResponse::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'report' => ['required', 'array'],
            'meta' => ['nullable', 'array'],
            'meta.subtitle' => ['nullable', 'string', 'max:255'],
            'meta.target' => ['nullable', 'string', 'max:500'],
            'meta.organization' => ['nullable', 'string', 'max:255'],
            'file_name' => ['nullable', 'string', 'max:180'],
        ]);

        $meta = $validated['meta'] ?? [];
        if (empty($meta['organization'])) {
            $orgId = $this->organizationIdOrNull();
            if ($orgId) {
                $meta['organization'] = Organization::query()->whereKey($orgId)->value('name');
            }
        }

        try {
            return $this->pdf->download(
                $type,
                $validated['report'],
                $meta,
                $validated['file_name'] ?? null,
            );
        } catch (Throwable $e) {
            return ApiResponse::error('PDF generation failed: '.$e->getMessage(), SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function vapt(Request $request, ExperienceMonitoringTest $experienceMonitoringTest): Response|JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = isset($validated['from']) ? Carbon::parse((string) $validated['from']) : now()->subDays(7);
        $to = isset($validated['to']) ? Carbon::parse((string) $validated['to']) : now();

        try {
            $report = $this->experienceMonitoring->buildTechnicalReport($experienceMonitoringTest, $from, $to);
            $orgName = $experienceMonitoringTest->organization?->name
                ?? Organization::query()->whereKey($experienceMonitoringTest->organization_id)->value('name');

            return $this->pdf->download(
                'vapt',
                $report,
                [
                    'organization' => $orgName,
                    'target' => $experienceMonitoringTest->name,
                    'subtitle' => 'Experience monitoring technical report',
                ],
                'vapt-'.$experienceMonitoringTest->id.'-'.now()->format('Ymd').'.pdf',
            );
        } catch (Throwable $e) {
            return ApiResponse::error('PDF generation failed: '.$e->getMessage(), SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
