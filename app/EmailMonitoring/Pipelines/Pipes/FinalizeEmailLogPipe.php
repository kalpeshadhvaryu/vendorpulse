<?php

namespace App\EmailMonitoring\Pipelines\Pipes;

use App\EmailMonitoring\Enums\EmailLogProcessingStatus;
use App\Models\EmailLog;
use Closure;

class FinalizeEmailLogPipe
{
    public function handle(EmailLog $log, Closure $next): mixed
    {
        $log->update([
            'processing_status' => EmailLogProcessingStatus::Completed,
            'processing_meta' => array_merge($log->processing_meta ?? [], [
                'completed_at' => now()->toIso8601String(),
            ]),
        ]);

        return $next($log->fresh());
    }
}
