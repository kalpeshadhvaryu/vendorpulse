<?php

namespace App\EmailMonitoring\Jobs;

use App\EmailMonitoring\Enums\EmailLogProcessingStatus;
use App\EmailMonitoring\Services\EmailMonitoringPipeline;
use App\Models\EmailLog;
use App\Models\Organization;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInboundEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public string $emailLogId,
        public string $organizationId,
    ) {
        $this->onQueue(config('email-monitoring.queue', 'email-monitoring'));
    }

    public function handle(
        EmailMonitoringPipeline $pipeline,
        CurrentOrganization $currentOrganization,
    ): void {
        $organization = Organization::query()->find($this->organizationId);

        if (! $organization) {
            return;
        }

        $currentOrganization->set($organization);

        try {
            $log = EmailLog::query()->where('organization_id', $this->organizationId)->find($this->emailLogId);

            if (! $log) {
                return;
            }

            if ($log->processing_status === EmailLogProcessingStatus::Completed) {
                return;
            }

            $pipeline->runSafe($log);
        } finally {
            $currentOrganization->clear();
        }
    }
}
