<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\MonitoringChecks\ListMonitoringLogsRequest;
use App\Http\Requests\Api\V1\MonitoringChecks\ListMonitoringChecksRequest;
use App\Http\Requests\Api\V1\MonitoringChecks\MonitoringLogsWindowRequest;
use App\Http\Requests\Api\V1\MonitoringChecks\ReassignMonitoringCheckRequest;
use App\Http\Requests\Api\V1\MonitoringChecks\StoreMonitoringCheckRequest;
use App\Http\Requests\Api\V1\MonitoringChecks\UpdateMonitoringCheckRequest;
use App\Http\Resources\Api\V1\MonitoringCheckResource;
use App\Http\Resources\Api\V1\MonitoringLogResource;
use App\Models\MonitoringCheck;
use App\Models\Organization;
use App\Services\MonitoringCheckService;
use App\SiteMonitoring\Jobs\RunMonitoringCheckJob;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MonitoringCheckController extends BaseApiController
{
    public function __construct(
        protected MonitoringCheckService $monitoringChecks
    ) {}

    public function index(ListMonitoringChecksRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = min((int) ($validated['per_page'] ?? 15), 100);

        $filters = [];
        if (! empty($validated['search'])) {
            $filters['search'] = trim((string) $validated['search']);
        }
        if (! empty($validated['type'])) {
            $filters['type'] = (string) $validated['type'];
        }

        return ApiResponse::fromResource(
            MonitoringCheckResource::collection($this->monitoringChecks->paginate($perPage, $filters))
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
        if (! empty($validated['from_at'])) {
            $filters['from'] = Carbon::parse($validated['from_at']);
        } elseif (! empty($validated['from'])) {
            $filters['from'] = Carbon::parse($validated['from'])->startOfDay();
        }
        if (! empty($validated['to_at'])) {
            $filters['to'] = Carbon::parse($validated['to_at']);
        } elseif (! empty($validated['to'])) {
            $filters['to'] = Carbon::parse($validated['to'])->endOfDay();
        }
        if (! empty($validated['search'])) {
            $filters['search'] = trim((string) $validated['search']);
        }
        if (($validated['downtime_only'] ?? false) === true) {
            $filters['downtime_only'] = true;
        }
        if (($validated['changes_only'] ?? false) === true) {
            $filters['changes_only'] = true;
        }

        return ApiResponse::fromResource(
            MonitoringLogResource::collection($this->monitoringChecks->paginateLogs($monitoringCheck, $perPage, $filters))
        );
    }

    public function logSummary(MonitoringLogsWindowRequest $request, MonitoringCheck $monitoringCheck): JsonResponse
    {
        $validated = $request->validated();
        $from = isset($validated['from_at'])
            ? Carbon::parse($validated['from_at'])
            : (isset($validated['from'])
                ? Carbon::parse($validated['from'])->startOfDay()
                : now()->subDays(7)->startOfDay());
        $to = isset($validated['to_at'])
            ? Carbon::parse($validated['to_at'])
            : (isset($validated['to'])
                ? Carbon::parse($validated['to'])->endOfDay()
                : now()->endOfSecond());

        $summary = $this->monitoringChecks->summarizeLogsWindow($monitoringCheck, $from, $to);

        return ApiResponse::success($summary);
    }

    public function serverAnalytics(MonitoringLogsWindowRequest $request, MonitoringCheck $monitoringCheck): JsonResponse
    {
        $validated = $request->validated();
        $from = isset($validated['from_at'])
            ? Carbon::parse($validated['from_at'])
            : (isset($validated['from'])
                ? Carbon::parse($validated['from'])->startOfDay()
                : now()->subDays(7)->startOfDay());
        $to = isset($validated['to_at'])
            ? Carbon::parse($validated['to_at'])
            : (isset($validated['to'])
                ? Carbon::parse($validated['to'])->endOfDay()
                : now()->endOfSecond());

        if (strtolower((string) $monitoringCheck->type) !== 'server') {
            return ApiResponse::success([
                'window_from' => $from->toIso8601String(),
                'window_to' => $to->toIso8601String(),
                'sample_count' => 0,
                'metrics' => [],
            ]);
        }

        $summary = $this->monitoringChecks->summarizeServerMetrics($monitoringCheck, $from, $to);

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

    public function reassign(ReassignMonitoringCheckRequest $request, string $monitoringCheckId): JsonResponse
    {
        $actor = $request->user();
        if (! $actor || ! $actor->isAdmin()) {
            return ApiResponse::error('Only global admins can reassign monitoring checks.', Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();
        $toOrgId = (string) $validated['to_organization_id'];
        $execute = (bool) ($validated['execute'] ?? false);

        $check = MonitoringCheck::query()->withoutGlobalScopes()->withTrashed()->find($monitoringCheckId);
        if (! $check) {
            return ApiResponse::error('Monitoring check not found.', Response::HTTP_NOT_FOUND);
        }

        $fromOrgId = (string) ($validated['from_organization_id'] ?? $check->organization_id);

        if ((string) $check->organization_id !== $fromOrgId) {
            return ApiResponse::error(
                'Check does not belong to the provided source organization.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                data: [
                    'actual_organization_id' => (string) $check->organization_id,
                ]
            );
        }

        if ($fromOrgId === $toOrgId) {
            return ApiResponse::error(
                'Source and target organizations are the same.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $targetExists = Organization::query()->withTrashed()->whereKey($toOrgId)->exists();
        if (! $targetExists) {
            return ApiResponse::error('Target organization not found.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $vendorOrgId = null;
        if ($check->vendor_id) {
            $vendorOrgId = DB::table('vendors')->where('id', $check->vendor_id)->value('company_id');
        }

        if ($vendorOrgId && (string) $vendorOrgId !== $toOrgId) {
            return ApiResponse::error(
                'Safety check failed: check vendor belongs to a different organization.',
                Response::HTTP_CONFLICT,
                data: ['vendor_company_id' => (string) $vendorOrgId]
            );
        }

        $monitoringLogsTotal = DB::table('monitoring_logs')
            ->where('monitoring_check_id', $monitoringCheckId)
            ->count();
        $monitoringLogsDrift = DB::table('monitoring_logs')
            ->where('monitoring_check_id', $monitoringCheckId)
            ->where('organization_id', '!=', $fromOrgId)
            ->count();

        $socialAccountsTotal = DB::table('domain_social_accounts')
            ->where('monitoring_check_id', $monitoringCheckId)
            ->count();
        $socialAccountsDrift = DB::table('domain_social_accounts')
            ->where('monitoring_check_id', $monitoringCheckId)
            ->where('organization_id', '!=', $fromOrgId)
            ->count();

        if ($monitoringLogsDrift > 0 || $socialAccountsDrift > 0) {
            return ApiResponse::error(
                'Safety check failed: related records already contain organization mismatches.',
                Response::HTTP_CONFLICT,
                data: [
                    'monitoring_logs_mismatched' => $monitoringLogsDrift,
                    'domain_social_accounts_mismatched' => $socialAccountsDrift,
                ]
            );
        }

        $summary = [
            'mode' => $execute ? 'execute' : 'dry-run',
            'check_id' => (string) $check->id,
            'check_name' => (string) $check->name,
            'from_organization_id' => $fromOrgId,
            'to_organization_id' => $toOrgId,
            'monitoring_logs_to_move' => $monitoringLogsTotal,
            'domain_social_accounts_to_move' => $socialAccountsTotal,
        ];

        if (! $execute) {
            return ApiResponse::success($summary, 'Dry-run complete. Send execute=true to apply changes.');
        }

        $updated = DB::transaction(function () use ($monitoringCheckId, $fromOrgId, $toOrgId): array {
            $updatedChecks = DB::table('monitoring_checks')
                ->where('id', $monitoringCheckId)
                ->where('organization_id', $fromOrgId)
                ->update([
                    'organization_id' => $toOrgId,
                    'updated_at' => now(),
                ]);

            if ($updatedChecks !== 1) {
                throw new \RuntimeException('Monitoring check update failed unexpectedly.');
            }

            $updatedLogs = DB::table('monitoring_logs')
                ->where('monitoring_check_id', $monitoringCheckId)
                ->where('organization_id', $fromOrgId)
                ->update([
                    'organization_id' => $toOrgId,
                ]);

            $updatedSocialAccounts = DB::table('domain_social_accounts')
                ->where('monitoring_check_id', $monitoringCheckId)
                ->where('organization_id', $fromOrgId)
                ->update([
                    'organization_id' => $toOrgId,
                ]);

            return [
                'updated_checks' => $updatedChecks,
                'updated_monitoring_logs' => $updatedLogs,
                'updated_domain_social_accounts' => $updatedSocialAccounts,
            ];
        });

        return ApiResponse::success(
            array_merge($summary, $updated),
            'Monitoring check reassigned successfully.'
        );
    }
}
