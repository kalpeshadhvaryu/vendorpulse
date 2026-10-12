<?php

namespace Tests\Unit\Services\Vapt;

use App\Services\Vapt\Email\EmailHeaderAnalyzer;
use App\Services\Vapt\Email\EmailSecurityService;
use App\Services\Vapt\Email\TestMailboxReader;
use Tests\TestCase;

class EmailHeaderAnalyzerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'email-security.blacklist.enabled' => true,
            'email-security.blacklist.spamhaus_dqs_key' => null,
            'email-security.blacklist.ip_zones' => ['bl.spamcop.net' => 'SpamCop'],
            'email-security.blacklist.domain_zones' => [],
            'email-security.test_mailbox.trusted_authserv_id' => null,
        ]);
    }

    public function test_well_authenticated_gmail_delivery(): void
    {
        $headers = implode("\r\n", [
            'Delivered-To: mailtest+vp-abcd234567@example.net',
            'Received: by 2002:a05:6a10:1234 with SMTP id abc;',
            '        Mon, 12 Oct 2026 05:00:00 -0700 (PDT)',
            'Authentication-Results: mx.google.com;',
            '       dkim=pass header.i=@sender.test header.s=google header.b=AbC123;',
            '       spf=pass (google.com: domain of alice@sender.test designates 93.184.216.34 as permitted sender) smtp.mailfrom=alice@sender.test;',
            '       dmarc=pass (p=REJECT sp=REJECT dis=NONE) header.from=sender.test',
            'Return-Path: <alice@sender.test>',
            'Received: from mail.sender.test (mail.sender.test. [93.184.216.34])',
            '        by mx.google.com with ESMTPS id x1si123 for <mailtest+vp-abcd234567@example.net>',
            '        (version=TLS1_3 cipher=TLS_AES_256_GCM_SHA384 bits=256/256);',
            '        Mon, 12 Oct 2026 05:00:00 -0700 (PDT)',
            'DKIM-Signature: v=1; a=rsa-sha256; c=relaxed/relaxed; d=sender.test; s=google;',
            '        h=from:to:subject; bh=abc=; b=def=',
            'From: Alice <alice@sender.test>',
            'To: mailtest+vp-abcd234567@example.net',
            'Subject: =?UTF-8?B?SGVsbG8gdGVzdA==?=',
            'Message-ID: <123@sender.test>',
            'Date: Mon, 12 Oct 2026 12:00:00 +0000',
            'List-Unsubscribe: <mailto:unsub@sender.test>',
        ]);

        $dns = new FakeDnsLookup(
            txt: ['google._domainkey.sender.test' => ['v=DKIM1; k=rsa; p='.$this->rsaPublicKey(2048)]],
            a: [
                'mail.sender.test' => ['93.184.216.34'],
                '2.0.0.127.bl.spamcop.net' => ['127.0.0.2'],
            ],
            ptr: ['93.184.216.34' => ['mail.sender.test']],
        );

        $report = $this->analyzer($dns)->analyze($headers, 2048);
        $checks = collect($report['checks'])->keyBy('key');

        $this->assertSame('mx.google.com', $report['authentication']['authserv_id']);
        $this->assertSame('pass', $report['authentication']['spf']['result']);
        $this->assertSame('sender.test', $report['authentication']['dkim'][0]['domain']);
        $this->assertSame('reject', $report['authentication']['dmarc']['policy']);
        $this->assertSame('93.184.216.34', $report['sending_ip']['ip']);
        $this->assertTrue($report['sending_ip']['fcrdns']);
        $this->assertTrue($report['tls']['used']);
        $this->assertTrue($report['alignment']['dkim_aligned']);
        $this->assertTrue($report['alignment']['spf_aligned']);
        $this->assertSame('Hello test', $report['message']['subject']);
        $this->assertSame(2048, $report['dkim_signatures'][0]['key']['key_bits']);
        $this->assertSame(100, $report['score']);
        $this->assertSame('A', $report['grade']);

        foreach (['spf', 'dkim', 'dmarc', 'alignment', 'dkim-key', 'sending-ip-rdns', 'sending-ip-blacklist', 'transport-tls', 'list-unsubscribe'] as $key) {
            $this->assertSame('pass', $checks[$key]['status'], $key);
        }
    }

    public function test_unsigned_mail_from_unaligned_provider_fails(): void
    {
        $headers = implode("\n", [
            'Authentication-Results: mx.google.com;',
            '       spf=softfail (google.com: domain of transitioning bounce@mailer.provider.test does not designate 45.33.32.9 as permitted sender) smtp.mailfrom=bounce@mailer.provider.test;',
            '       dmarc=fail (p=NONE sp=NONE dis=NONE) header.from=shop.test',
            'Received: from smtp.provider.test (smtp.provider.test [45.33.32.9])',
            '        by mx.google.com with ESMTP id abc;',
            'From: Shop <hello@shop.test>',
            'Subject: Order',
            'Date: Mon, 12 Oct 2026 12:00:00 +0000',
        ]);

        $dns = new FakeDnsLookup(a: [
            '2.0.0.127.bl.spamcop.net' => ['127.0.0.2'],
            '9.32.33.45.bl.spamcop.net' => ['127.0.0.2'],
        ]);

        $report = $this->analyzer($dns)->analyze($headers);
        $checks = collect($report['checks'])->keyBy('key');

        $this->assertSame('warning', $checks['spf']['status']);
        $this->assertSame('fail', $checks['dkim']['status']);
        $this->assertSame('fail', $checks['dmarc']['status']);
        $this->assertSame('fail', $checks['alignment']['status']);
        $this->assertSame('fail', $checks['sending-ip-rdns']['status']);
        $this->assertSame('fail', $checks['sending-ip-blacklist']['status']);
        $this->assertSame('warning', $checks['transport-tls']['status']);
        $this->assertSame('warning', $checks['message-headers']['status']);
        $this->assertSame('F', $report['grade']);
    }

    public function test_trusted_authserv_id_ignores_forged_results(): void
    {
        config(['email-security.test_mailbox.trusted_authserv_id' => 'mx.google.com']);

        $headers = implode("\n", [
            'Authentication-Results: evil.test; dkim=pass header.d=shop.test; spf=pass smtp.mailfrom=shop.test; dmarc=pass header.from=shop.test',
            'Authentication-Results: mx.google.com; spf=fail smtp.mailfrom=shop.test; dmarc=fail header.from=shop.test',
            'From: hello@shop.test',
        ]);

        $report = $this->analyzer(new FakeDnsLookup)->analyze($headers);

        $this->assertSame('mx.google.com', $report['authentication']['authserv_id']);
        $this->assertSame('fail', $report['authentication']['spf']['result']);
        $this->assertSame([], $report['authentication']['dkim']);
    }

    public function test_microsoft_results_without_authserv_id(): void
    {
        $headers = implode("\n", [
            'Authentication-Results: spf=pass (sender IP is 93.184.216.34)',
            ' smtp.mailfrom=shop.test; dkim=pass (signature was verified)',
            ' header.d=shop.test;dmarc=pass action=none header.from=shop.test;compauth=pass reason=100',
            'From: hello@shop.test',
        ]);

        $auth = $this->analyzer(new FakeDnsLookup)->analyze($headers)['authentication'];

        $this->assertSame('', $auth['authserv_id']);
        $this->assertSame('pass', $auth['spf']['result']);
        $this->assertSame('shop.test', $auth['spf']['mailfrom']);
        $this->assertSame('shop.test', $auth['dkim'][0]['domain']);
        $this->assertSame('pass', $auth['dmarc']['result']);
    }

    public function test_org_domain_and_token_matching(): void
    {
        $analyzer = $this->analyzer(new FakeDnsLookup);

        $this->assertSame('example.co.in', $analyzer->orgDomain('mail.example.co.in'));
        $this->assertSame('example.com', $analyzer->orgDomain('bounce.mail.example.com'));

        $reader = new TestMailboxReader;
        $this->assertSame('abcd234567', $reader->matchToken("To: test+VP-ABCD234567@x.test\r\n", ['zzzz', 'abcd234567']));
        $this->assertNull($reader->matchToken("To: test@x.test\r\nSubject: hi\r\n", ['abcd234567']));
    }

    private function analyzer(FakeDnsLookup $dns): EmailHeaderAnalyzer
    {
        return new EmailHeaderAnalyzer($dns, new EmailSecurityService($dns));
    }

    private function rsaPublicKey(int $bits): string
    {
        $key = openssl_pkey_new(['private_key_bits' => $bits, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        return preg_replace('/-----[^-]+-----|\s+/', '', openssl_pkey_get_details($key)['key']);
    }
}
