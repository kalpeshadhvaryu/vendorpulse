<?php

namespace App\Services\Vapt\Email;

use App\Models\EmailDeliverabilityTest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EmailDeliverabilityTestService
{
    private const TOKEN_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    private const TOKEN_LENGTH = 10;

    public function __construct(
        private readonly TestMailboxReader $mailbox,
        private readonly EmailHeaderAnalyzer $analyzer,
    ) {}

    public function isAvailable(): bool
    {
        return $this->mailbox->isConfigured();
    }

    public function create(string $source, ?string $organizationId = null, ?string $userId = null, ?string $requesterIp = null): EmailDeliverabilityTest
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('The email test mailbox is not configured.');
        }

        do {
            $token = $this->generateToken();
        } while (EmailDeliverabilityTest::query()->where('token', $token)->exists());

        return EmailDeliverabilityTest::query()->create([
            'token' => $token,
            'source' => $source,
            'organization_id' => $organizationId,
            'created_by' => $userId,
            'requester_ip' => $requesterIp,
            'status' => EmailDeliverabilityTest::STATUS_WAITING,
            'expires_at' => now()->addMinutes(max(5, (int) config('email-security.test_mailbox.expires_minutes', 30))),
        ]);
    }

    /**
     * Checks the inbox (rate limited globally) and returns the refreshed test.
     */
    public function refresh(EmailDeliverabilityTest $test): EmailDeliverabilityTest
    {
        if ($test->status === EmailDeliverabilityTest::STATUS_WAITING && $test->expires_at->isPast()) {
            $test->update(['status' => EmailDeliverabilityTest::STATUS_EXPIRED]);

            return $test;
        }

        if (! $test->isWaiting()) {
            return $test;
        }

        $interval = max(2, (int) config('email-security.test_mailbox.inbox_check_interval_seconds', 5));
        if (Cache::add('email-test:inbox-checked', true, $interval)) {
            $this->processInbox();
        }

        return $test->refresh();
    }

    public function processInbox(): int
    {
        return (int) Cache::lock('email-test:inbox', 60)->get(fn (): int => $this->processInboxUnlocked());
    }

    private function processInboxUnlocked(): int
    {
        $waiting = EmailDeliverabilityTest::query()
            ->where('status', EmailDeliverabilityTest::STATUS_WAITING)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->keyBy('token');

        if ($waiting->isEmpty()) {
            return 0;
        }

        try {
            $messages = $this->mailbox->findMessages($waiting->keys()->all());
        } catch (\Throwable $e) {
            Log::warning('Email test mailbox check failed', ['error' => $e->getMessage()]);

            return 0;
        }

        foreach ($messages as $token => $message) {
            $test = $waiting->get($token);
            if ($test === null) {
                continue;
            }

            $result = $this->analyzer->analyze($message['raw_headers'], $message['size']);

            $test->update([
                'status' => EmailDeliverabilityTest::STATUS_RECEIVED,
                'received_at' => now(),
                'from_address' => $result['message']['from_address'],
                'subject' => isset($result['message']['subject']) ? mb_substr((string) $result['message']['subject'], 0, 500) : null,
                'score' => $result['score'],
                'grade' => $result['grade'],
                'result' => $result,
            ]);
        }

        return count($messages);
    }

    public function addressFor(EmailDeliverabilityTest $test): string
    {
        $address = strtolower(trim((string) config('email-security.test_mailbox.address')));
        [$local, $domain] = array_pad(explode('@', $address, 2), 2, '');

        return $local.'+vp-'.$test->token.'@'.$domain;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(EmailDeliverabilityTest $test): array
    {
        $status = $test->status === EmailDeliverabilityTest::STATUS_WAITING && $test->expires_at->isPast()
            ? EmailDeliverabilityTest::STATUS_EXPIRED
            : $test->status;

        return [
            'id' => $test->id,
            'token' => $test->token,
            'status' => $status,
            'address' => $this->addressFor($test),
            'subject_hint' => 'VendorPulse test vp-'.$test->token,
            'expires_at' => $test->expires_at->toIso8601String(),
            'received_at' => $test->received_at?->toIso8601String(),
            'from_address' => $test->from_address,
            'subject' => $test->subject,
            'score' => $test->score,
            'grade' => $test->grade,
            'result' => $test->result,
            'created_at' => $test->created_at?->toIso8601String(),
        ];
    }

    public function prune(): int
    {
        $days = max(1, (int) config('email-security.test_mailbox.retention_days', 7));

        return EmailDeliverabilityTest::query()->where('created_at', '<', now()->subDays($days))->delete();
    }

    private function generateToken(): string
    {
        $token = '';
        $max = strlen(self::TOKEN_ALPHABET) - 1;

        for ($i = 0; $i < self::TOKEN_LENGTH; $i++) {
            $token .= self::TOKEN_ALPHABET[random_int(0, $max)];
        }

        return $token;
    }
}
