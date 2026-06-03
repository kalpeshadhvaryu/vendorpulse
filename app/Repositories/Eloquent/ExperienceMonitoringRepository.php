<?php

namespace App\Repositories\Eloquent;

use App\Models\ExperienceMonitoringRun;
use App\Models\ExperienceMonitoringRunMetric;
use App\Models\ExperienceMonitoringExecutionLog;
use App\Models\ExperienceMonitoringScreenshot;
use App\Models\ExperienceMonitoringTest;
use App\Repositories\Contracts\ExperienceMonitoringRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ExperienceMonitoringRepository implements ExperienceMonitoringRepositoryInterface
{
    public function paginateTests(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = ExperienceMonitoringTest::query();

        if (! empty($filters['search'])) {
            $term = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('login_url', 'like', $term)
                    ->orWhere('dashboard_url', 'like', $term)
                    ->orWhere('login_username', 'like', $term);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('last_status', (string) $filters['status']);
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function createTest(array $attributes): ExperienceMonitoringTest
    {
        return ExperienceMonitoringTest::query()->create($attributes);
    }

    public function updateTest(ExperienceMonitoringTest $test, array $attributes): ExperienceMonitoringTest
    {
        $test->update($attributes);

        return $test->fresh();
    }

    public function deleteTest(ExperienceMonitoringTest $test): bool
    {
        return (bool) $test->delete();
    }

    public function findTest(string $id): ?ExperienceMonitoringTest
    {
        return ExperienceMonitoringTest::query()->withoutGlobalScopes()->whereKey($id)->first();
    }

    public function paginateRuns(ExperienceMonitoringTest $test, int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $query = ExperienceMonitoringRun::query()
            ->where('experience_monitoring_test_id', $test->id)
            ->where('organization_id', $test->organization_id);

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function createRun(array $attributes): ExperienceMonitoringRun
    {
        return ExperienceMonitoringRun::query()->create($attributes);
    }

    public function createRunMetric(array $attributes): void
    {
        ExperienceMonitoringRunMetric::query()->create($attributes);
    }

    public function createExecutionLog(array $attributes): void
    {
        ExperienceMonitoringExecutionLog::query()->create($attributes);
    }

    public function createScreenshot(array $attributes): ExperienceMonitoringScreenshot
    {
        return ExperienceMonitoringScreenshot::query()->create($attributes);
    }

    public function listScreenshots(ExperienceMonitoringTest $test, int $perPage = 20): LengthAwarePaginator
    {
        return ExperienceMonitoringScreenshot::query()
            ->where('organization_id', $test->organization_id)
            ->whereIn('experience_monitoring_run_id', function ($q) use ($test): void {
                $q->select('id')
                    ->from('experience_monitoring_runs')
                    ->where('experience_monitoring_test_id', $test->id);
            })
            ->orderByDesc('captured_at')
            ->paginate($perPage);
    }

    public function findScreenshot(string $id): ?ExperienceMonitoringScreenshot
    {
        return ExperienceMonitoringScreenshot::query()->whereKey($id)->first();
    }

    public function dueEnabledTestIds(Carbon $now, int $limit = 1000): Collection
    {
        return ExperienceMonitoringTest::query()
            ->withoutGlobalScopes()
            ->where('enabled', true)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', $now);
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');
    }
}
