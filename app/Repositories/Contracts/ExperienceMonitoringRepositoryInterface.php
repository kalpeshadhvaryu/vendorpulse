<?php

namespace App\Repositories\Contracts;

use App\Models\ExperienceMonitoringRun;
use App\Models\ExperienceMonitoringScreenshot;
use App\Models\ExperienceMonitoringTest;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ExperienceMonitoringRepositoryInterface
{
    public function paginateTests(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    public function createTest(array $attributes): ExperienceMonitoringTest;

    public function updateTest(ExperienceMonitoringTest $test, array $attributes): ExperienceMonitoringTest;

    public function deleteTest(ExperienceMonitoringTest $test): bool;

    public function findTest(string $id): ?ExperienceMonitoringTest;

    public function paginateRuns(ExperienceMonitoringTest $test, int $perPage = 20, array $filters = []): LengthAwarePaginator;

    public function createRun(array $attributes): ExperienceMonitoringRun;

    public function createRunMetric(array $attributes): void;

    public function createExecutionLog(array $attributes): void;

    public function createScreenshot(array $attributes): ExperienceMonitoringScreenshot;

    public function listScreenshots(ExperienceMonitoringTest $test, int $perPage = 20): LengthAwarePaginator;

    public function findScreenshot(string $id): ?ExperienceMonitoringScreenshot;

    public function dueEnabledTestIds(Carbon $now, int $limit = 1000): Collection;
}
