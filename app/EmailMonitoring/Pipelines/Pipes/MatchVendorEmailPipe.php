<?php

namespace App\EmailMonitoring\Pipelines\Pipes;

use App\EmailMonitoring\Contracts\VendorEmailMatcherInterface;
use App\EmailMonitoring\DTO\NormalizedInboundEmail;
use App\EmailMonitoring\Enums\EmailLogProcessingStatus;
use App\Models\EmailLog;
use Closure;

class MatchVendorEmailPipe
{
    public function __construct(
        protected VendorEmailMatcherInterface $matcher,
    ) {}

    public function handle(EmailLog $log, Closure $next): mixed
    {
        $payload = $log->normalized_payload ?? [];

        if ($payload === []) {
            return $next($log);
        }

        $normalized = NormalizedInboundEmail::fromStoredArray($payload);
        $result = $this->matcher->match($normalized, (string) $log->organization_id);

        $updates = [
            'processing_meta' => array_merge($log->processing_meta ?? [], [
                'vendor_match' => [
                    'strategy' => $result->strategy,
                    'evidence' => $result->evidence,
                ],
            ]),
        ];

        if ($result->matched()) {
            $updates['vendor_id'] = $result->vendorId;
            $updates['vendor_email_id'] = $result->vendorEmailId;
            $updates['vendor_match_confidence'] = $result->confidence;
            $updates['processing_status'] = EmailLogProcessingStatus::Matched;
        }

        $log->update($updates);

        return $next($log->fresh());
    }
}
