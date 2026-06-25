<?php

namespace App\SiteMonitoring\Services;

use App\Models\MonitoringCheck;
use App\Models\MonitoringLog;
use App\SiteMonitoring\DTO\ProbeResult;
use App\SiteMonitoring\Enums\MonitoringLogStatus;
use App\SiteMonitoring\Events\SiteMonitoringDomainExpiringSoon;
use App\SiteMonitoring\Events\SiteMonitoringSslExpiringSoon;
use App\SiteMonitoring\Events\SiteMonitoringUptimeCheckFailed;
use App\SiteMonitoring\Events\SiteMonitoringUptimeCheckRecovered;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Throwable;

class MonitoringExecutionService
{
    public function __construct(
        protected MonitoringStrategyRegistry $strategies,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function execute(string $monitoringCheckId, array $context = []): void
    {
        Cache::lock('site-monitoring:check:'.$monitoringCheckId, 90)->block(10, function () use ($monitoringCheckId, $context): void {
            $check = MonitoringCheck::query()
                ->withoutGlobalScopes()
                ->with(['organization.users'])
                ->find($monitoringCheckId);

            if (! $check || ! $check->enabled) {
                return;
            }

            $previous = [
                'last_status' => $check->last_status,
                'consecutive_failures' => (int) $check->consecutive_failures,
                'last_message' => $check->last_message,
                'last_http_status' => $check->last_http_status,
            ];

            $strategy = $this->strategies->resolve($check);

            try {
                $result = $strategy->execute($check);
            } catch (Throwable $e) {
                report($e);
                $result = new ProbeResult(
                    MonitoringLogStatus::Error,
                    mb_substr($e->getMessage(), 0, 500),
                    null,
                    null,
                    ['exception' => $e::class]
                );
            }

            $meta = $result->meta;
            if ($context !== []) {
                $meta['execution_context'] = $context;
            }

            $failureStates = [MonitoringLogStatus::Failed->value, MonitoringLogStatus::Error->value];
            $isFailure = in_array($result->status->value, $failureStates, true);
            $consecutiveFailures = $isFailure ? $previous['consecutive_failures'] + 1 : 0;

            $log = DB::transaction(function () use ($check, $result, $meta, $consecutiveFailures) {
                $log = \App\Models\MonitoringLog::query()->create([
                    'monitoring_check_id' => $check->id,
                    'organization_id' => $check->organization_id,
                    'status' => $result->status,
                    'http_status' => $result->httpStatus,
                    'response_time_ms' => $result->responseTimeMs,
                    'message' => $result->message,
                    'meta' => $meta === [] ? null : $meta,
                ]);

                $check->update([
                    'last_status' => $result->status->value,
                    'last_message' => $result->message,
                    'last_run_at' => now(),
                    'next_run_at' => now()->addSeconds(max(60, (int) $check->interval_seconds)),
                    'last_http_status' => $result->httpStatus,
                    'last_response_time_ms' => $result->responseTimeMs,
                    'last_meta' => $meta === [] ? null : $meta,
                    'consecutive_failures' => $consecutiveFailures,
                ]);

                return $log;
            });

            $check->refresh();

            $this->dispatchAlerts($check, $log, $previous, $consecutiveFailures, $result);
        });
    }

    /**
     * @param  array{last_status: ?string, consecutive_failures: int}  $previous
     */
    protected function dispatchAlerts(
        MonitoringCheck $check,
        MonitoringLog $log,
        array $previous,
        int $consecutiveFailures,
        ProbeResult $result,
    ): void {
        $threshold = max(1, (int) config('site-monitoring.uptime_failure_notify_after', 1));

        if ($this->isUptimeFamily($check)) {
            if (in_array($result->status, [MonitoringLogStatus::Failed, MonitoringLogStatus::Error], true)
                && $consecutiveFailures === $threshold) {
                Event::dispatch(new SiteMonitoringUptimeCheckFailed($check, $log));
            }

            if ($result->status === MonitoringLogStatus::Ok
                && in_array($previous['last_status'], ['failed', 'error'], true)) {
                Event::dispatch(new SiteMonitoringUptimeCheckRecovered($check, $log));
            }
        }

        if ($this->isSslFamily($check)
            && $result->status === MonitoringLogStatus::Degraded
            && Cache::add(
                'site-monitoring:ssl-alert:'.$check->id,
                true,
                now()->addSeconds(max(60, (int) config('site-monitoring.ssl_alert_throttle_seconds', 86400)))
            )) {
            Event::dispatch(new SiteMonitoringSslExpiringSoon($check, $log));
        }

        if ($this->isDomainFamily($check)
            && $result->status === MonitoringLogStatus::Degraded
            && Cache::add(
                'site-monitoring:domain-alert:'.$check->id,
                true,
                now()->addSeconds(max(60, (int) config('site-monitoring.domain_alert_throttle_seconds', 86400)))
            )) {
            Event::dispatch(new SiteMonitoringDomainExpiringSoon($check, $log));
        }
    }

    protected function isUptimeFamily(MonitoringCheck $check): bool
    {
        return in_array(strtolower((string) $check->type), ['uptime', 'http', 'https'], true);
    }

    protected function isSslFamily(MonitoringCheck $check): bool
    {
        return in_array(strtolower((string) $check->type), ['ssl', 'tls'], true);
    }

    protected function isDomainFamily(MonitoringCheck $check): bool
    {
        return in_array(strtolower((string) $check->type), ['domain', 'whois'], true);
    }
}
