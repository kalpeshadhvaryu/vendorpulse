<?php

namespace App\EmailMonitoring\Jobs;

use App\EmailMonitoring\Contracts\MailboxConnectorFactoryInterface;
use App\EmailMonitoring\DTO\MailboxSyncCursor;
use App\EmailMonitoring\Services\EmailLogIngestionService;
use App\Models\EmailMailbox;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PollEmailMailboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $mailboxId
    ) {
        $this->onQueue(config('email-monitoring.queue', 'email-monitoring'));
    }

    public function handle(
        MailboxConnectorFactoryInterface $connectorFactory,
        EmailLogIngestionService $ingestion,
        CurrentOrganization $currentOrganization,
    ): void {
        $mailbox = EmailMailbox::query()->withoutGlobalScopes()->with('organization')->find($this->mailboxId);

        if (! $mailbox || ! $mailbox->is_enabled) {
            return;
        }

        $currentOrganization->set($mailbox->organization);

        try {
            $connector = $connectorFactory->forMailbox($mailbox);

            foreach ($connector->fetchSince($mailbox, MailboxSyncCursor::fromMailboxState($mailbox->sync_state)) as $envelope) {
                $log = $ingestion->ingestFromEnvelope(
                    (string) $mailbox->organization_id,
                    $mailbox->id,
                    $envelope,
                );

                ProcessInboundEmailJob::dispatch((string) $log->id, (string) $mailbox->organization_id);
            }

            $mailbox->update([
                'last_polled_at' => now(),
                'last_successful_sync_at' => now(),
                'last_error' => null,
            ]);
        } catch (\Throwable $e) {
            $mailbox->update([
                'last_polled_at' => now(),
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            throw $e;
        } finally {
            $currentOrganization->clear();
        }
    }
}
