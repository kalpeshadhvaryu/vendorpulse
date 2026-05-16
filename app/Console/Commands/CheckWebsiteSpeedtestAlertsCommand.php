<?php

namespace App\Console\Commands;

use App\Models\WebsiteSpeedtestRun;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckWebsiteSpeedtestAlertsCommand extends Command
{
    protected $signature = 'vapt:speedtest-alerts';

    protected $description = 'Notify organization users when recent website speedtest runs are slower than threshold';

    public function __construct(
        private readonly NotificationDispatchService $notifications,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $thresholdMs = (float) config('vapt-web-health.speedtest_alert_threshold_ms', 3000);
        $windowMinutes = (int) config('vapt-web-health.speedtest_alert_window_minutes', 15);
        $cooldownMinutes = (int) config('vapt-web-health.speedtest_alert_cooldown_minutes', 60);

        $since = now()->subMinutes($windowMinutes);

        $candidates = WebsiteSpeedtestRun::query()
            ->where('success', true)
            ->whereNotNull('total_time_ms')
            ->where('total_time_ms', '>=', $thresholdMs)
            ->where('tested_at', '>=', $since)
            ->with('organization.users')
            ->orderByDesc('tested_at')
            ->get()
            ->unique(function (WebsiteSpeedtestRun $run): string {
                return $run->organization_id.'|'.$run->target_url;
            });

        $sent = 0;

        foreach ($candidates as $run) {
            $users = $run->organization?->users;
            if (! $users || $users->isEmpty()) {
                continue;
            }

            $cacheKey = 'vapt:speedtest-alert:'.$run->organization_id.':'.sha1((string) $run->target_url);
            $isNew = Cache::add($cacheKey, $run->id, now()->addMinutes($cooldownMinutes));
            if (! $isNew) {
                continue;
            }

            $this->notifications->notify(
                $users,
                'vapt.website_speed_slow',
                [
                    'website_speedtest_run_id' => $run->id,
                    'target_url' => $run->target_url,
                    'final_url' => $run->final_url,
                    'total_time_ms' => $run->total_time_ms,
                    'threshold_ms' => $thresholdMs,
                    'tested_at' => $run->tested_at?->toIso8601String(),
                ]
            );
            $sent++;
        }

        $this->info("Website speed alerts sent: {$sent}");

        return self::SUCCESS;
    }
}
