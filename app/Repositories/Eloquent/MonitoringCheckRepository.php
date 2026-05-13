<?php

namespace App\Repositories\Eloquent;

use App\Models\MonitoringCheck;
use App\Models\MonitoringLog;
use App\Repositories\Contracts\MonitoringCheckRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MonitoringCheckRepository implements MonitoringCheckRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return MonitoringCheck::query()
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function paginateLogsForCheck(MonitoringCheck $check, int $perPage = 30, array $filters = []): LengthAwarePaginator
    {
        $query = MonitoringLog::query()
            ->where('monitoring_check_id', $check->id)
            ->where('organization_id', $check->organization_id);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function logsBetween(MonitoringCheck $check, Carbon $from, Carbon $to, int $limit = 10000): Collection
    {
        return MonitoringLog::query()
            ->where('monitoring_check_id', $check->id)
            ->where('organization_id', $check->organization_id)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    public function latestLogBefore(MonitoringCheck $check, Carbon $moment): ?MonitoringLog
    {
        return MonitoringLog::query()
            ->where('monitoring_check_id', $check->id)
            ->where('organization_id', $check->organization_id)
            ->where('created_at', '<', $moment)
            ->orderByDesc('created_at')
            ->first();
    }

    public function find(string $id): ?MonitoringCheck
    {
        return MonitoringCheck::query()->whereKey($id)->first();
    }

    public function create(array $attributes): MonitoringCheck
    {
        return MonitoringCheck::query()->create($attributes);
    }

    public function update(MonitoringCheck $monitoringCheck, array $attributes): MonitoringCheck
    {
        $monitoringCheck->update($attributes);

        return $monitoringCheck->fresh();
    }

    public function delete(MonitoringCheck $monitoringCheck): bool
    {
        return (bool) $monitoringCheck->delete();
    }
}
