<?php

namespace App\Http\Resources\Api\V1;

use App\EmailMonitoring\Support\EmailLogInvoiceOutcome;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmailLog */
class EmailLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->processing_status instanceof \BackedEnum
            ? $this->processing_status->value
            : (string) $this->processing_status;

        $outcome = EmailLogInvoiceOutcome::fromProcessingMeta($this->processing_meta);
        $latestExtraction = $this->relationLoaded('invoiceExtractions')
            ? $this->invoiceExtractions->first()
            : null;

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'email_mailbox_id' => $this->email_mailbox_id,
            'mailbox_name' => $this->whenLoaded('mailbox', fn () => $this->mailbox?->name),
            'external_message_id' => $this->external_message_id,
            'subject' => $this->subject,
            'from_email' => $this->from_email,
            'received_at' => $this->received_at?->toIso8601String(),
            'processing_status' => $status,
            'vendor_id' => $this->vendor_id,
            'vendor_name' => $this->whenLoaded('vendor', fn () => $this->vendor?->name),
            'vendor_match_confidence' => $this->vendor_match_confidence !== null
                ? (float) $this->vendor_match_confidence
                : null,
            'failure_reason' => $this->failure_reason,
            'invoice_outcome' => $outcome,
            'latest_extraction' => $latestExtraction ? [
                'id' => $latestExtraction->id,
                'status' => $latestExtraction->status instanceof \BackedEnum
                    ? $latestExtraction->status->value
                    : (string) $latestExtraction->status,
                'aggregate_confidence' => $latestExtraction->aggregate_confidence !== null
                    ? (float) $latestExtraction->aggregate_confidence
                    : null,
                'candidate_invoice_number' => data_get($latestExtraction->extracted_payload, 'candidate_invoice_number'),
                'candidate_event' => data_get($latestExtraction->extracted_payload, 'candidate_event'),
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
