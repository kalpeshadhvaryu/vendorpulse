<?php

namespace App\Console\Commands;

use App\Models\MonitoringLog;
use Illuminate\Console\Command;

class PruneMonitoringLogsCommand extends Command
{
    protected $signature = 'monitoring:prune-logs
                            {--days= : Override retention days (default from config)}
                            {--chunk=1000 : Rows deleted per batch}
                            {--dry-run : Count rows that would be deleted without deleting}';

    protected $description = 'Delete monitoring_logs older than the configured retention window (default 90 days)';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('site-monitoring.log_retention_days', 90));
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        if ($days < 1) {
            $this->error('Retention days must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $query = MonitoringLog::query()->where('created_at', '<', $cutoff);
        $eligible = (clone $query)->count();

        $this->info("Retention: {$days} days. Cutoff: {$cutoff->toIso8601String()}. Eligible: {$eligible}.");

        if ($dryRun || $eligible === 0) {
            return self::SUCCESS;
        }

        $deleted = 0;

        while (true) {
            $ids = (clone $query)
                ->orderBy('created_at')
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += MonitoringLog::query()->whereIn('id', $ids)->delete();
            $this->output->write('.');
        }

        $this->newLine();
        $this->info("Deleted {$deleted} monitoring log row(s).");

        return self::SUCCESS;
    }
}
