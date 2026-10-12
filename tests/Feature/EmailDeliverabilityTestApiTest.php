<?php

namespace Tests\Feature;

use App\Models\EmailDeliverabilityTest;
use App\Services\Vapt\Email\TestMailboxReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EmailDeliverabilityTestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'email-security.test_mailbox.address' => 'mailtest@example.net',
            'email-security.test_mailbox.host' => 'imap.example.net',
            'email-security.test_mailbox.username' => 'mailtest@example.net',
            'email-security.test_mailbox.password' => 'secret',
            'email-security.blacklist.enabled' => false,
        ]);

        $this->app->instance(TestMailboxReader::class, new class extends TestMailboxReader
        {
            /** @var array<string, array{raw_headers:string, size:int|null}> */
            public array $messages = [];

            public function findMessages(array $tokens): array
            {
                return array_intersect_key($this->messages, array_flip($tokens));
            }
        });
    }

    public function test_public_flow_creates_address_and_returns_analysis_once_mail_arrives(): void
    {
        $created = $this->postJson('/api/v1/public/email-test')->assertCreated();
        $token = $created->json('data.token');

        $this->assertMatchesRegularExpression('/^[a-z0-9]{10}$/', $token);
        $this->assertSame('mailtest+vp-'.$token.'@example.net', $created->json('data.address'));
        $this->assertSame('waiting', $created->json('data.status'));

        $this->fakeMailbox([]);
        $this->getJson('/api/v1/public/email-test/'.$token)
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting');

        Cache::flush();
        $this->fakeMailbox([$token => [
            'raw_headers' => implode("\r\n", [
                'Authentication-Results: mx.example.net; dkim=pass header.d=sender.test header.s=s1; spf=pass smtp.mailfrom=bounce@sender.test; dmarc=pass header.from=sender.test',
                'From: Alice <alice@sender.test>',
                'Subject: VendorPulse test vp-'.$token,
                'Message-ID: <1@sender.test>',
                'Date: Mon, 12 Oct 2026 12:00:00 +0000',
            ]),
            'size' => 1200,
        ]]);

        $this->getJson('/api/v1/public/email-test/'.$token)
            ->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.from_address', 'alice@sender.test')
            ->assertJsonPath('data.result.alignment.dkim_aligned', true);
    }

    public function test_public_flow_reports_unavailable_without_mailbox(): void
    {
        config(['email-security.test_mailbox.host' => null]);

        $this->postJson('/api/v1/public/email-test')->assertStatus(503);
    }

    public function test_expired_test_is_reported_as_expired(): void
    {
        $test = EmailDeliverabilityTest::query()->create([
            'token' => 'expiredtok1',
            'source' => 'public',
            'status' => EmailDeliverabilityTest::STATUS_WAITING,
            'expires_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/v1/public/email-test/'.$test->token)
            ->assertOk()
            ->assertJsonPath('data.status', 'expired');
    }

    /**
     * @param  array<string, array{raw_headers:string, size:int|null}>  $messages
     */
    private function fakeMailbox(array $messages): void
    {
        $this->app->make(TestMailboxReader::class)->messages = $messages;
    }
}
