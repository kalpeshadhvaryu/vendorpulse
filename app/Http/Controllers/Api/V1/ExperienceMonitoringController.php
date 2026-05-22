<?php

namespace App\Http\Controllers\Api\V1;

use App\ExperienceMonitoring\Jobs\RunExperienceMonitoringTestJob;
use App\ExperienceMonitoring\Services\ExperienceMonitoringService;
use App\Http\Requests\Api\V1\ExperienceMonitoring\ListExperienceMonitoringRunsRequest;
use App\Http\Requests\Api\V1\ExperienceMonitoring\ListExperienceMonitoringTestsRequest;
use App\Http\Requests\Api\V1\ExperienceMonitoring\StoreExperienceMonitoringTestRequest;
use App\Http\Requests\Api\V1\ExperienceMonitoring\UpdateExperienceMonitoringTestRequest;
use App\Http\Resources\Api\V1\ExperienceMonitoringRunResource;
use App\Http\Resources\Api\V1\ExperienceMonitoringScreenshotResource;
use App\Http\Resources\Api\V1\ExperienceMonitoringTestResource;
use App\Models\ExperienceMonitoringScreenshot;
use App\Models\ExperienceMonitoringTest;
use App\Repositories\Contracts\ExperienceMonitoringRepositoryInterface;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ExperienceMonitoringController extends BaseApiController
{
    public function __construct(
        protected ExperienceMonitoringService $experienceMonitoring,
        protected ExperienceMonitoringRepositoryInterface $repository,
    ) {}

    public function index(ListExperienceMonitoringTestsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = min((int) ($validated['per_page'] ?? 15), 100);

        $filters = [];
        if (! empty($validated['search'])) {
            $filters['search'] = trim((string) $validated['search']);
        }
        if (! empty($validated['status'])) {
            $filters['status'] = trim((string) $validated['status']);
        }

        return ApiResponse::fromResource(
            ExperienceMonitoringTestResource::collection($this->experienceMonitoring->paginateTests($perPage, $filters))
        );
    }

    public function store(StoreExperienceMonitoringTestRequest $request): JsonResponse
    {
        $test = $this->experienceMonitoring->createTest($request->validated(), $request->user());

        return ApiResponse::success(
            new ExperienceMonitoringTestResource($test),
            'Experience monitoring test created.',
            Response::HTTP_CREATED
        );
    }

    public function show(ExperienceMonitoringTest $experienceMonitoringTest): JsonResponse
    {
        return ApiResponse::success(new ExperienceMonitoringTestResource($experienceMonitoringTest));
    }

    public function update(UpdateExperienceMonitoringTestRequest $request, ExperienceMonitoringTest $experienceMonitoringTest): JsonResponse
    {
        $test = $this->experienceMonitoring->updateTest($experienceMonitoringTest, $request->validated(), $request->user());

        return ApiResponse::success(new ExperienceMonitoringTestResource($test), 'Experience monitoring test updated.');
    }

    public function destroy(ExperienceMonitoringTest $experienceMonitoringTest): JsonResponse
    {
        $this->experienceMonitoring->deleteTest($experienceMonitoringTest);

        return ApiResponse::success(null, 'Experience monitoring test deleted.');
    }

    public function trigger(ExperienceMonitoringTest $experienceMonitoringTest): JsonResponse
    {
        RunExperienceMonitoringTestJob::dispatch($experienceMonitoringTest->id)
            ->onQueue((string) config('experience-monitoring.queue', 'experience-monitoring'));

        return ApiResponse::success(null, 'Experience monitoring test queued.');
    }

    public function runs(ListExperienceMonitoringRunsRequest $request, ExperienceMonitoringTest $experienceMonitoringTest): JsonResponse
    {
        $validated = $request->validated();
        $perPage = min((int) ($validated['per_page'] ?? 20), 100);

        $filters = [];
        if (! empty($validated['status'])) {
            $filters['status'] = (string) $validated['status'];
        }
        if (! empty($validated['from'])) {
            $filters['from'] = Carbon::parse((string) $validated['from']);
        }
        if (! empty($validated['to'])) {
            $filters['to'] = Carbon::parse((string) $validated['to']);
        }

        return ApiResponse::fromResource(
            ExperienceMonitoringRunResource::collection(
                $this->experienceMonitoring->paginateRuns($experienceMonitoringTest, $perPage, $filters)
            )
        );
    }

    public function metrics(ListExperienceMonitoringRunsRequest $request, ExperienceMonitoringTest $experienceMonitoringTest): JsonResponse
    {
        $validated = $request->validated();
        $from = isset($validated['from']) ? Carbon::parse((string) $validated['from']) : now()->subDays(7);
        $to = isset($validated['to']) ? Carbon::parse((string) $validated['to']) : now();

        return ApiResponse::success($this->experienceMonitoring->summarizeMetrics($experienceMonitoringTest, $from, $to));
    }

    public function report(ListExperienceMonitoringRunsRequest $request, ExperienceMonitoringTest $experienceMonitoringTest): JsonResponse
    {
        $validated = $request->validated();
        $from = isset($validated['from']) ? Carbon::parse((string) $validated['from']) : now()->subDays(7);
        $to = isset($validated['to']) ? Carbon::parse((string) $validated['to']) : now();

        return ApiResponse::success($this->experienceMonitoring->buildTechnicalReport($experienceMonitoringTest, $from, $to));
    }

    public function screenshots(ListExperienceMonitoringRunsRequest $request, ExperienceMonitoringTest $experienceMonitoringTest): JsonResponse
    {
        $validated = $request->validated();
        $perPage = min((int) ($validated['per_page'] ?? 20), 100);

        return ApiResponse::fromResource(
            ExperienceMonitoringScreenshotResource::collection(
                $this->experienceMonitoring->listScreenshots($experienceMonitoringTest, $perPage)
            )
        );
    }

    public function screenshotFile(ExperienceMonitoringScreenshot $experienceMonitoringScreenshot)
    {
        $disk = Storage::disk((string) config('experience-monitoring.screenshots_disk', 'local'));
        $path = is_string($experienceMonitoringScreenshot->path) ? trim($experienceMonitoringScreenshot->path) : '';

        if ($path === '') {
            return ApiResponse::error('Screenshot file not found.', Response::HTTP_NOT_FOUND);
        }

        if ($disk->exists($path)) {
            return response($disk->get($path), Response::HTTP_OK, [
                'Content-Type' => $experienceMonitoringScreenshot->mime_type,
                'Cache-Control' => 'private, max-age=60',
            ]);
        }

        // Fallback for runtime/config drift where screenshot files were written under
        // a different local root (storage/app/private vs storage/app).
        $normalizedPath = ltrim($path, '/');
        $fallbackCandidates = [
            storage_path('app/private/'.$normalizedPath),
            storage_path('app/'.$normalizedPath),
            base_path('../storage/app/private/'.$normalizedPath),
            base_path('../storage/app/'.$normalizedPath),
        ];

        foreach ($fallbackCandidates as $candidate) {
            if (! is_file($candidate)) {
                continue;
            }

            $contents = @file_get_contents($candidate);
            if ($contents === false) {
                continue;
            }

            return response($contents, Response::HTTP_OK, [
                'Content-Type' => $experienceMonitoringScreenshot->mime_type,
                'Cache-Control' => 'private, max-age=60',
            ]);
        }

        return ApiResponse::error('Screenshot file not found.', Response::HTTP_NOT_FOUND);
    }
}
