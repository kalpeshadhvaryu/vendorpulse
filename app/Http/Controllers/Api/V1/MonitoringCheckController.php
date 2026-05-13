<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\MonitoringChecks\ListMonitoringLogsRequest;
use App\Http\Requests\Api\V1\MonitoringChecks\MonitoringLogsWindowRequest;
use App\Http\Requests\Api\V1\MonitoringChecks\StoreMonitoringCheckRequest;
use App\Http\Requests\Api\V1\MonitoringChecks\UpdateMonitoringCheckRequest;
use App\Http\Resources\Api\V1\MonitoringCheckResource;
use App\Http\Resources\Api\V1\MonitoringLogResource;
use App\Models\MonitoringCheck;
use App\Services\MonitoringCheckService;
use App\SiteMonitoring\Jobs\RunMonitoringCheckJob;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MonitoringCheckController extends BaseApiController
{
    public function __construct(
        protected MonitoringCheckService $monitoringChecks
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        return ApiResponse::fromResource(
            MonitoringCheckResource::collection($this->monitoringChecks->paginate($perPage))
        );
    }

    public function logs(ListMonitoringLogsRequest $request, MonitoringCheck $monitoringCheck): JsonResponse
    {
        $validated = $request->validated();
        $perPage = min((int) ($validated['per_page'] ?? 30), 100);

        $filters = [];
        if (! empty($validated['status'])) {
            $filters['status'] = $validated['status'];
        }
        if (! empty($validated['from'])) {
            $filters['from'] = Carbon::parse($validated['from'])->startOfDay();
        }
        if (! empty($validated['to'])) {
            $filters['to'] = Carbon::parse($validated['to'])->endOfDay();
        }

        return ApiResponse::fromResource(
            MonitoringLogResource::collection($this->monitoringChecks->paginateLogs($monitoringCheck, $perPage, $filters))
        );
    }

    public function logSummary(MonitoringLogsWindowRequest $request, MonitoringCheck $monitoringCheck): JsonResponse
    {
        $validated = $request->validated();
        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->subDays(7)->startOfDay();
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfSecond();

        $summary = $this->monitoringChecks->summarizeLogsWindow($monitoringCheck, $from, $to);

        return ApiResponse::success($summary);
    }

    public function run(MonitoringCheck $monitoringCheck): JsonResponse
    {
        RunMonitoringCheckJob::dispatch($monitoringCheck->id)
            ->onQueue((string) config('site-monitoring.queue', 'site-monitoring'));

        return ApiResponse::success(null, 'Monitoring check queued for execution.');
    }

    public function store(StoreMonitoringCheckRequest $request): JsonResponse
    {
        $check = $this->monitoringChecks->create($request->validated(), $request->user());

        return ApiResponse::success(
            new MonitoringCheckResource($check),
            'Monitoring check created.',
            Response::HTTP_CREATED
        );
    }

    public function show(MonitoringCheck $monitoringCheck): JsonResponse
    {
        return ApiResponse::success(new MonitoringCheckResource($monitoringCheck));
    }

    public function update(UpdateMonitoringCheckRequest $request, MonitoringCheck $monitoringCheck): JsonResponse
    {
        $monitoringCheck = $this->monitoringChecks->update($monitoringCheck, $request->validated(), $request->user());

        return ApiResponse::success(new MonitoringCheckResource($monitoringCheck), 'Monitoring check updated.');
    }

    public function destroy(MonitoringCheck $monitoringCheck): JsonResponse
    {
        $this->monitoringChecks->delete($monitoringCheck);

        return ApiResponse::success(null, 'Monitoring check deleted.');
    }
}
