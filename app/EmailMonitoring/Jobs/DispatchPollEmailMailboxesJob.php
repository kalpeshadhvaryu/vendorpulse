<?php

namespace App\EmailMonitoring\Jobs;

use App\Models\EmailMailbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatches {@see PollEmailMailboxJob} for every enabled mailbox (all organizations).
 */
class DispatchPollEmailMailboxesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue(config('email-monitoring.queue', 'email-monitoring'));
    }

    public function handle(): void
    {
        EmailMailbox::query()
            ->withoutGlobalScopes()
            ->where('is_enabled', true)
            ->each(function (EmailMailbox $mailbox): void {
                PollEmailMailboxJob::dispatch($mailbox->id);
            });
    }
}
