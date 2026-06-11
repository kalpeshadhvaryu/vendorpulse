<?php

namespace App\Repositories\Eloquent;

use App\Models\MonitoringCheck;
use App\Models\MonitoringLog;
use App\Repositories\Contracts\MonitoringCheckRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonitoringCheckRepository implements MonitoringCheckRepositoryInterface
{
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = MonitoringCheck::query()
            ->withMax(
                ['monitoringLogs' => fn ($q) => $q->whereIn('status', ['failed', 'error', 'degraded'])],
                'created_at'
            );

        if (! empty($filters['search'])) {
            $term = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('type', 'like', $term)
                    ->orWhere('endpoint', 'like', $term)
                    ->orWhere('last_status', 'like', $term)
                    ->orWhere('last_message', 'like', $term);
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', (string) $filters['type']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function paginateLogsForCheck(MonitoringCheck $check, int $perPage = 30, array $filters = []): LengthAwarePaginator
    {
        $changesOnly = ($filters['changes_only'] ?? false) === true;

        if (! $changesOnly) {
            $query = MonitoringLog::query()
                ->where('monitoring_check_id', $check->id)
                ->where('organization_id', $check->organization_id);

            $this->applyLogFilters($query, $filters);

            return $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($perPage);
        }

        $windowed = MonitoringLog::query()
            ->selectRaw('monitoring_logs.*, LAG(status) OVER (ORDER BY created_at ASC, id ASC) as prev_status')
            ->where('monitoring_check_id', $check->id)
            ->where('organization_id', $check->organization_id);

        $this->applyLogFilters($windowed, $filters);
        $windowed->whereIn(DB::raw('LOWER(status)'), ['ok', 'failed']);

        $query = DB::query()->fromSub($windowed, 'logs_with_prev')
            ->where(function (QueryBuilder $q): void {
                $q->whereNull('prev_status')
                    ->orWhereRaw("LOWER(COALESCE(status, '')) <> LOWER(COALESCE(prev_status, ''))");
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $query->paginate($perPage);
    }

    /**
     * @param Builder<MonitoringLog> $query
     * @param array{search?: string, downtime_only?: bool, status?: string, from?: Carbon, to?: Carbon} $filters
     */
    private function applyLogFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $term = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('message', 'like', $term)
                    ->orWhere('status', 'like', $term)
                    ->orWhereRaw("CAST(http_status AS TEXT) like ?", [$term]);
            });
        }

        if (($filters['downtime_only'] ?? false) === true) {
            $query->whereIn('status', ['failed', 'error', 'degraded']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }
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
