<?php

namespace App\Repositories\Contracts;

use App\Models\MonitoringCheck;
use App\Models\MonitoringLog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MonitoringCheckRepositoryInterface
{
    /**
     * @param  array{search?: string}  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
        * @param  array{search?: string, downtime_only?: bool, status?: string, from?: Carbon, to?: Carbon}  $filters
     * @return LengthAwarePaginator<int, MonitoringLog>
     */
    public function paginateLogsForCheck(MonitoringCheck $check, int $perPage = 30, array $filters = []): LengthAwarePaginator;

    /**
     * @return Collection<int, MonitoringLog>
     */
    public function logsBetween(MonitoringCheck $check, Carbon $from, Carbon $to, int $limit = 10000): Collection;

    public function latestLogBefore(MonitoringCheck $check, Carbon $moment): ?MonitoringLog;

    public function find(string $id): ?MonitoringCheck;

    public function create(array $attributes): MonitoringCheck;

    public function update(MonitoringCheck $monitoringCheck, array $attributes): MonitoringCheck;

    public function delete(MonitoringCheck $monitoringCheck): bool;
}
