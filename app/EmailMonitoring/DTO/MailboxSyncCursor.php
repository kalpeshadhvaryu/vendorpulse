<?php

namespace App\EmailMonitoring\DTO;

/**
 * @param  array<string, mixed>  $state
 */
final readonly class MailboxSyncCursor
{
    public function __construct(
        public array $state = [],
    ) {}

    /**
     * @param  array<string, mixed>|null  $syncState
     */
    public static function fromMailboxState(?array $syncState): self
    {
        return new self($syncState ?? []);
    }
}
