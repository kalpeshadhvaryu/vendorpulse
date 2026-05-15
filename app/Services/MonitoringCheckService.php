<?php

namespace App\Services;

use App\Models\MonitoringCheck;
use App\Models\MonitoringLog;
use App\Models\User;
use App\Repositories\Contracts\MonitoringCheckRepositoryInterface;
use App\SiteMonitoring\Enums\MonitoringLogStatus;
use App\Support\Organization\CurrentOrganization;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MonitoringCheckService
{
    public function __construct(
        protected MonitoringCheckRepositoryInterface $monitoringChecks,
        protected CurrentOrganization $currentOrganization
    ) {}

    /**
     * @param  array{search?: string}  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->monitoringChecks->paginate($perPage, $filters);
    }

    /**
        * @param  array{search?: string, downtime_only?: bool, status?: string, from?: Carbon, to?: Carbon}  $filters
     * @return LengthAwarePaginator<int, MonitoringLog>
     */
    public function paginateLogs(MonitoringCheck $monitoringCheck, int $perPage = 30, array $filters = []): LengthAwarePaginator
    {
        return $this->monitoringChecks->paginateLogsForCheck($monitoringCheck, $perPage, $filters);
    }

    /**
     * Time-weighted durations from probe timestamps in [effective_from, to].
     *
     * The requested window is clamped so it does not start before the check existed (avoids huge "unknown" spans).
     * With no log before the effective window, time until the first in-window probe is treated as **ok** (assume
     * service was up until the first measurement). If there are no probes in the window at all, the whole span is
     * **unknown** and uptime_ratio is null.
     *
     * "Up" = ok; "down" = failed + error; degraded and skipped tracked separately.
     *
     * @return array{
     *     window_from: string,
     *     window_to: string,
     *     log_count_in_window: int,
     *     probe_counts: array<string, int>,
     *     duration_seconds: array{up: int, down: int, degraded: int, skipped: int, unknown: int},
     *     window_span_seconds: int,
     *     uptime_ratio: float|null,
     *     downtime_incidents: int
     * }
     */
    public function summarizeLogsWindow(MonitoringCheck $check, Carbon $from, Carbon $to): array
    {
        $from = $from->copy();
        $to = $to->copy();

        $checkCreated = $check->created_at?->copy() ?? $from->copy();
        $effectiveFrom = $from->copy();
        if ($effectiveFrom->lt($checkCreated)) {
            $effectiveFrom = $checkCreated->copy();
        }

        if ($effectiveFrom->greaterThan($to)) {
            return $this->emptyLogSummary($from, $to);
        }

        $logs = $this->monitoringChecks->logsBetween($check, $effectiveFrom, $to, 10000);
        $prev = $this->monitoringChecks->latestLogBefore($check, $effectiveFrom);

        $probeCounts = [
            'ok' => 0,
            'failed' => 0,
            'error' => 0,
            'degraded' => 0,
            'skipped' => 0,
        ];

        foreach ($logs as $log) {
            $k = $this->logStatusString($log);
            if (isset($probeCounts[$k])) {
                $probeCounts[$k]++;
            }
        }

        $durations = [
            'up' => 0,
            'down' => 0,
            'degraded' => 0,
            'skipped' => 0,
            'unknown' => 0,
        ];

        $windowSpan = max(0, $effectiveFrom->diffInSeconds($to));

        if ($logs->isEmpty()) {
            $this->addDurationForState('unknown', $windowSpan, $durations);

            return [
                'window_from' => $effectiveFrom->toIso8601String(),
                'window_to' => $to->toIso8601String(),
                'window_span_seconds' => $windowSpan,
                'log_count_in_window' => 0,
                'probe_counts' => $probeCounts,
                'duration_seconds' => $durations,
                'uptime_ratio' => null,
                'downtime_incidents' => 0,
            ];
        }

        $state = $prev !== null ? $this->logStatusString($prev) : 'ok';
        $cursor = $effectiveFrom->copy();

        foreach ($logs as $log) {
            /** @var Carbon $t */
            $t = $log->created_at;
            if ($t < $cursor) {
                continue;
            }
            if ($t > $to) {
                break;
            }
            $segmentSeconds = max(0, $cursor->diffInSeconds($t));
            $this->addDurationForState($state, $segmentSeconds, $durations);
            $state = $this->logStatusString($log);
            $cursor = $t->copy();
        }

        $tailSeconds = max(0, $cursor->diffInSeconds($to));
        $this->addDurationForState($state, $tailSeconds, $durations);

        $denom = $durations['up'] + $durations['down'] + $durations['degraded'];
        $uptimeRatio = $denom > 0 ? round($durations['up'] / $denom, 4) : null;

        $downtimeIncidents = $logs->filter(function (MonitoringLog $log): bool {
            $s = $this->logStatusString($log);

            return in_array($s, ['failed', 'error'], true);
        })->count();

        return [
            'window_from' => $effectiveFrom->toIso8601String(),
            'window_to' => $to->toIso8601String(),
            'window_span_seconds' => $windowSpan,
            'log_count_in_window' => $logs->count(),
            'probe_counts' => $probeCounts,
            'duration_seconds' => $durations,
            'uptime_ratio' => $uptimeRatio,
            'downtime_incidents' => $downtimeIncidents,
        ];
    }

    /**
     * @return array{
     *   window_from: string,
     *   window_to: string,
     *   sample_count: int,
     *   metrics: array<string, array{avg: float|null, min: float|null, max: float|null, latest: float|null}>
     * }
     */
    public function summarizeServerMetrics(MonitoringCheck $check, Carbon $from, Carbon $to): array
    {
        $logs = $this->monitoringChecks->logsBetween($check, $from, $to, 10000);

        $keys = [
            'load_1m',
            'load_5m',
            'load_15m',
            'cpu_percent',
            'memory_used_percent',
            'disk_used_percent',
            'bandwidth_used_bytes',
            'process_count',
        ];

        $bucket = [];
        foreach ($keys as $key) {
            $bucket[$key] = [];
        }

        foreach ($logs as $log) {
            foreach ($keys as $key) {
                $value = data_get($log->meta, 'metrics.'.$key);
                if (is_numeric($value)) {
                    $bucket[$key][] = (float) $value;
                }
            }
        }

        $metrics = [];
        foreach ($keys as $key) {
            $values = $bucket[$key];
            if ($values === []) {
                $metrics[$key] = [
                    'avg' => null,
                    'min' => null,
                    'max' => null,
                    'latest' => null,
                ];
                continue;
            }

            $metrics[$key] = [
                'avg' => round(array_sum($values) / count($values), 2),
                'min' => round(min($values), 2),
                'max' => round(max($values), 2),
                'latest' => round((float) end($values), 2),
            ];
        }

        return [
            'window_from' => $from->toIso8601String(),
            'window_to' => $to->toIso8601String(),
            'sample_count' => $logs->count(),
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array{
     *     window_from: string,
     *     window_to: string,
     *     log_count_in_window: int,
     *     probe_counts: array<string, int>,
     *     duration_seconds: array{up: int, down: int, degraded: int, skipped: int, unknown: int},
     *     window_span_seconds: int,
     *     uptime_ratio: float|null,
     *     downtime_incidents: int
     * }
     */
    private function emptyLogSummary(Carbon $from, Carbon $to): array
    {
        $span = max(0, $from->diffInSeconds($to));

        return [
            'window_from' => $from->toIso8601String(),
            'window_to' => $to->toIso8601String(),
            'window_span_seconds' => $span,
            'log_count_in_window' => 0,
            'probe_counts' => [
                'ok' => 0,
                'failed' => 0,
                'error' => 0,
                'degraded' => 0,
                'skipped' => 0,
            ],
            'duration_seconds' => [
                'up' => 0,
                'down' => 0,
                'degraded' => 0,
                'skipped' => 0,
                'unknown' => $span,
            ],
            'uptime_ratio' => null,
            'downtime_incidents' => 0,
        ];
    }

    private function logStatusString(MonitoringLog $log): string
    {
        $s = $log->status;

        return $s instanceof MonitoringLogStatus ? $s->value : (string) $s;
    }

    /**
     * @param  array{up: int, down: int, degraded: int, skipped: int, unknown: int}  $durations
     */
    private function addDurationForState(string $state, int $seconds, array &$durations): void
    {
        if ($seconds <= 0) {
            return;
        }

        match ($state) {
            'ok' => $durations['up'] += $seconds,
            'failed', 'error' => $durations['down'] += $seconds,
            'degraded' => $durations['degraded'] += $seconds,
            'skipped' => $durations['skipped'] += $seconds,
            default => $durations['unknown'] += $seconds,
        };
    }

    public function find(string $id): ?MonitoringCheck
    {
        return $this->monitoringChecks->find($id);
    }

    public function create(array $data, User $actor): MonitoringCheck
    {
        $data['organization_id'] = $this->currentOrganization->id();
        $data['created_by'] = $actor->id;
        $data['updated_by'] = $actor->id;

        $interval = max(60, (int) ($data['interval_seconds'] ?? 300));
        $data['interval_seconds'] = $interval;
        if (empty($data['next_run_at'])) {
            $data['next_run_at'] = now()->addSeconds($interval);
        }

        return $this->monitoringChecks->create($data);
    }

    public function update(MonitoringCheck $monitoringCheck, array $data, User $actor): MonitoringCheck
    {
        $data['updated_by'] = $actor->id;

        return $this->monitoringChecks->update($monitoringCheck, $data);
    }

    public function delete(MonitoringCheck $monitoringCheck): bool
    {
        return $this->monitoringChecks->delete($monitoringCheck);
    }
}
