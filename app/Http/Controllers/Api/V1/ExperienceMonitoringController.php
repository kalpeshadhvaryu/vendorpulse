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
use App\Models\ExperienceMonitoringTest;
use App\Repositories\Contracts\ExperienceMonitoringRepositoryInterface;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        if (! $experienceMonitoringTest->enabled) {
            return ApiResponse::error('Experience monitoring test is disabled. Enable it before running.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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

    public function screenshotFile(Request $request, string $experience_monitoring_screenshot): JsonResponse|Response
    {
        $screenshot = $this->repository->findScreenshot($experience_monitoring_screenshot);

        if (! $screenshot) {
            return ApiResponse::error('Screenshot not found.', Response::HTTP_NOT_FOUND);
        }

        $user = $request->user();
        if ($user && $user->isAdmin() !== true && ! $user->belongsToOrganization((string) $screenshot->organization_id)) {
            return ApiResponse::error('You do not have access to this screenshot.', Response::HTTP_FORBIDDEN);
        }

        $path = is_string($screenshot->path) ? trim($screenshot->path) : '';
        if ($path === '') {
            return ApiResponse::error('Screenshot file not found.', Response::HTTP_NOT_FOUND);
        }

        $contents = $this->readScreenshotContents($path);
        if ($contents === null) {
            return ApiResponse::error('Screenshot file not found.', Response::HTTP_NOT_FOUND);
        }

        return response($contents, Response::HTTP_OK, [
            'Content-Type' => $screenshot->mime_type ?: 'image/png',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }

    private function readScreenshotContents(string $path): ?string
    {
        $disk = Storage::disk((string) config('experience-monitoring.screenshots_disk', 'local'));

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

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

            return $contents === false ? null : $contents;
        }

        return null;
    }
}
