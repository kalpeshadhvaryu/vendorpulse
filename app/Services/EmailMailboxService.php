<?php

namespace App\Services;

use App\EmailMonitoring\Enums\MailboxDriver;
use App\Models\EmailMailbox;
use App\Models\User;
use App\Support\Organization\CurrentOrganization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmailMailboxService
{
    public function __construct(
        protected CurrentOrganization $currentOrganization
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return EmailMailbox::query()
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, User $actor): EmailMailbox
    {
        $driver = MailboxDriver::from($validated['driver']);
        $config = $this->normalizeConnectionConfig($driver, $validated['connection_config'] ?? []);

        return EmailMailbox::query()->create([
            'organization_id' => $this->currentOrganization->id(),
            'name' => $validated['name'],
            'driver' => $driver,
            'is_enabled' => (bool) ($validated['is_enabled'] ?? true),
            'connection_config' => $config,
            'sync_state' => null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(EmailMailbox $mailbox, array $validated, User $actor): EmailMailbox
    {
        $data = ['updated_by' => $actor->id];

        if (array_key_exists('name', $validated)) {
            $data['name'] = $validated['name'];
        }
        if (array_key_exists('is_enabled', $validated)) {
            $data['is_enabled'] = (bool) $validated['is_enabled'];
        }

        $nextDriver = $mailbox->driver;
        if (isset($validated['driver'])) {
            $nextDriver = MailboxDriver::from($validated['driver']);
            $data['driver'] = $nextDriver;
        }

        if (isset($validated['connection_config'])) {
            $incoming = $validated['connection_config'];
            $existing = $mailbox->connection_config ?? [];
            $merged = array_merge($existing, array_filter(
                $incoming,
                static fn ($v) => $v !== null && $v !== ''
            ));

            if (($incoming['password'] ?? null) === '' || ($incoming['password'] ?? null) === null) {
                if (isset($existing['password'])) {
                    $merged['password'] = $existing['password'];
                } else {
                    unset($merged['password']);
                }
            }
            if (($incoming['client_secret'] ?? null) === '' || ($incoming['client_secret'] ?? null) === null) {
                if (isset($existing['client_secret'])) {
                    $merged['client_secret'] = $existing['client_secret'];
                } else {
                    unset($merged['client_secret']);
                }
            }
            if (($incoming['refresh_token'] ?? null) === '' || ($incoming['refresh_token'] ?? null) === null) {
                if (isset($existing['refresh_token'])) {
                    $merged['refresh_token'] = $existing['refresh_token'];
                } else {
                    unset($merged['refresh_token']);
                }
            }

            $data['connection_config'] = $this->normalizeConnectionConfig($nextDriver, $merged);
        }

        $mailbox->update($data);

        return $mailbox->fresh();
    }

    public function delete(EmailMailbox $mailbox): bool
    {
        return (bool) $mailbox->delete();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeConnectionConfig(MailboxDriver $driver, array $input): array
    {
        if ($driver === MailboxDriver::Imap) {
            $out = [
                'host' => isset($input['host']) ? (string) $input['host'] : '',
                'port' => isset($input['port']) ? (int) $input['port'] : 993,
                'encryption' => isset($input['encryption']) && in_array($input['encryption'], ['ssl', 'tls', 'none'], true)
                    ? (string) $input['encryption']
                    : 'ssl',
                'username' => isset($input['username']) ? (string) $input['username'] : '',
                'folder' => isset($input['folder']) && (string) $input['folder'] !== ''
                    ? (string) $input['folder']
                    : 'INBOX',
            ];
            if (isset($input['password']) && is_string($input['password']) && $input['password'] !== '') {
                $out['password'] = $input['password'];
            }

            return $out;
        }

        if ($driver === MailboxDriver::GmailApi) {
            $out = [];
            foreach (['client_id', 'client_secret', 'refresh_token'] as $key) {
                if (isset($input[$key]) && is_string($input[$key]) && $input[$key] !== '') {
                    $out[$key] = $input[$key];
                }
            }

            return $out;
        }

        return [];
    }
}
