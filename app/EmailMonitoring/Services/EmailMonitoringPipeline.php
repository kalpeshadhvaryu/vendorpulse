<?php

namespace App\EmailMonitoring\Services;

use App\EmailMonitoring\Pipelines\Pipes\DispatchEmailMonitoringEventsPipe;
use App\EmailMonitoring\Pipelines\Pipes\ApplyInvoiceAutomationPipe;
use App\EmailMonitoring\Pipelines\Pipes\ExtractInvoiceCandidatesPipe;
use App\EmailMonitoring\Pipelines\Pipes\FinalizeEmailLogPipe;
use App\EmailMonitoring\Pipelines\Pipes\MatchVendorEmailPipe;
use App\EmailMonitoring\Pipelines\Pipes\NormalizeInboundEmailPipe;
use App\EmailMonitoring\Pipelines\Pipes\RegisterAttachmentsPipe;
use App\EmailMonitoring\Pipelines\Pipes\RunOcrPlaceholderPipe;
use App\EmailMonitoring\Pipelines\Pipes\VendorEmailPreferencesPipe;
use App\EmailMonitoring\Enums\EmailLogProcessingStatus;
use App\Models\EmailLog;
use Illuminate\Pipeline\Pipeline;
use Throwable;

class EmailMonitoringPipeline
{
    public function __construct(
        protected Pipeline $pipeline,
    ) {}

    public function run(EmailLog $log): void
    {
        $this->pipeline
            ->send($log)
            ->through([
                NormalizeInboundEmailPipe::class,
                MatchVendorEmailPipe::class,
                VendorEmailPreferencesPipe::class,
                RegisterAttachmentsPipe::class,
                RunOcrPlaceholderPipe::class,
                ExtractInvoiceCandidatesPipe::class,
                ApplyInvoiceAutomationPipe::class,
                FinalizeEmailLogPipe::class,
                DispatchEmailMonitoringEventsPipe::class,
            ])
            ->thenReturn();
    }

    public function runSafe(EmailLog $log): void
    {
        try {
            $this->run($log->fresh());
        } catch (Throwable $e) {
            $log->refresh();
            $log->update([
                'processing_status' => EmailLogProcessingStatus::Failed,
                'failure_reason' => mb_substr($e->getMessage(), 0, 500),
                'processing_meta' => array_merge($log->processing_meta ?? [], [
                    'exception' => $e::class,
                ]),
            ]);

            report($e);
        }
    }
}
