<?php

namespace App\Http\Resources\Api\V1;

use App\EmailMonitoring\Support\EmailLogInvoiceOutcome;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmailLog */
class EmailLogDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = (new EmailLogResource($this->resource))->toArray($request);
        $status = $this->processing_status instanceof \BackedEnum
            ? $this->processing_status->value
            : (string) $this->processing_status;

        return array_merge($base, [
            'to_recipients' => $this->to_recipients ?? [],
            'cc_recipients' => $this->cc_recipients ?? [],
            'body_text_preview' => $this->bodyPreview(),
            'processing_meta' => $this->processing_meta ?? [],
            'invoice_outcome' => EmailLogInvoiceOutcome::fromProcessingMeta($this->processing_meta),
            'extractions' => $this->whenLoaded('invoiceExtractions', function () {
                return $this->invoiceExtractions->map(static fn ($row) => [
                    'id' => $row->id,
                    'status' => $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status,
                    'aggregate_confidence' => $row->aggregate_confidence !== null ? (float) $row->aggregate_confidence : null,
                    'extracted_payload' => $row->extracted_payload ?? [],
                    'created_at' => $row->created_at?->toIso8601String(),
                ])->values();
            }),
            'processing_status' => $status,
        ]);
    }

    private function bodyPreview(): ?string
    {
        $text = trim((string) ($this->body_text ?? ''));
        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= 2000) {
            return $text;
        }

        return mb_substr($text, 0, 2000).'…';
    }
}
