<?php

namespace Tests\Unit\Services\Vapt;

use App\Services\Vapt\Email\EmailSecurityService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmailSecurityServiceTest extends TestCase
{
    private const REACHABLE_ZONES = [
        '2.0.0.127.zen.spamhaus.org' => ['127.0.0.2'],
        '2.0.0.127.bl.spamcop.net' => ['127.0.0.2'],
        'dbltest.com.dbl.spamhaus.org' => ['127.0.1.2'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'email-security.dkim_selectors' => ['default', 'google', 'selector1'],
            'email-security.blacklist.enabled' => true,
            'email-security.blacklist.spamhaus_dqs_key' => null,
            'email-security.blacklist.ip_zones' => ['zen.spamhaus.org' => 'Spamhaus ZEN', 'bl.spamcop.net' => 'SpamCop'],
            'email-security.blacklist.domain_zones' => ['dbl.spamhaus.org' => 'Spamhaus DBL'],
            'email-security.blacklist.domain_zone_test_entries' => ['dbl.spamhaus.org' => 'dbltest.com'],
        ]);
    }

    public function test_well_configured_domain_scores_grade_a(): void
    {
        $dns = new FakeDnsLookup(
            txt: [
                'example.com' => ['v=spf1 include:_spf.google.com -all'],
                '_spf.google.com' => ['v=spf1 include:_netblocks.google.com ~all'],
                '_netblocks.google.com' => ['v=spf1 ip4:35.190.247.0/24 ~all'],
                '_dmarc.example.com' => ['v=DMARC1; p=reject; rua=mailto:dmarc@example.com'],
                'google._domainkey.example.com' => ['v=DKIM1; k=rsa; p='.$this->rsaPublicKey(2048)],
                '_mta-sts.example.com' => ['v=STSv1; id=2024'],
                '_smtp._tls.example.com' => ['v=TLSRPTv1; rua=mailto:tls@example.com'],
                'default._bimi.example.com' => ['v=BIMI1; l=https://example.com/logo.svg'],
            ],
            a: self::REACHABLE_ZONES + [
                'mx1.example.com' => ['93.184.216.34'],
                'mail.example.com' => ['93.184.216.34'],
                'mta-sts.example.com' => ['93.184.216.35'],
            ],
            mx: ['example.com' => [['host' => 'mx1.example.com', 'priority' => 10]]],
            ptr: ['93.184.216.34' => ['mail.example.com']],
        );

        Http::fake([
            'https://mta-sts.example.com/.well-known/mta-sts.txt' => Http::response("version: STSv1\nmode: enforce\nmx: *.example.com\nmax_age: 604800\n"),
        ]);

        $report = (new EmailSecurityService($dns))->analyze('www.example.com');

        $this->assertSame('example.com', $report['domain']);
        $this->assertSame('A', $report['grade']);
        $this->assertSame(100, $report['score']);
        $this->assertSame(2, $report['spf']['lookups']);
        $this->assertSame('-all', $report['spf']['all']);
        $this->assertSame('reject', $report['dmarc']['tags']['p']);
        $this->assertSame(2048, $report['dkim']['keys'][0]['key_bits']);
        $this->assertSame('enforce', $report['mta_sts']['policy']['mode']);
        $this->assertSame([], $report['mta_sts']['unmatched_mx']);

        foreach ($report['checks'] as $check) {
            $this->assertSame('pass', $check['status'], $check['key'].': '.$check['explanation']);
        }
    }

    public function test_missing_records_and_listed_ip_fail(): void
    {
        $dns = new FakeDnsLookup(
            txt: ['bad.test' => ['v=spf1 +all']],
            a: self::REACHABLE_ZONES + [
                'mx.bad.test' => ['45.33.32.7'],
                '7.32.33.45.bl.spamcop.net' => ['127.0.0.2'],
            ],
            mx: ['bad.test' => [['host' => 'mx.bad.test', 'priority' => 0]]],
        );

        $report = (new EmailSecurityService($dns))->analyze('bad.test');
        $checks = collect($report['checks'])->keyBy('key');

        $this->assertSame('fail', $checks['spf']['status']);
        $this->assertSame('warning', $checks['dmarc']['status']);
        $this->assertSame('warning', $checks['dkim']['status']);
        $this->assertSame('warning', $checks['mx-reverse-dns']['status']);
        $this->assertSame('fail', $checks['email-blacklist']['status']);
        $this->assertSame(1, $report['blacklist']['listed']);
        $this->assertSame('F', $report['grade']);
    }

    public function test_unreachable_blacklist_is_reported_as_unverified_not_clean(): void
    {
        $dns = new FakeDnsLookup(
            txt: [],
            a: [
                'mx.refused.test' => ['45.33.32.8'],
                '2.0.0.127.zen.spamhaus.org' => ['127.255.255.254'],
                '2.0.0.127.bl.spamcop.net' => ['127.0.0.2'],
                'dbltest.com.dbl.spamhaus.org' => ['127.0.1.2'],
            ],
            mx: ['refused.test' => [['host' => 'mx.refused.test', 'priority' => 0]]],
        );

        $report = (new EmailSecurityService($dns))->analyze('refused.test');

        $this->assertSame(0, $report['blacklist']['listed']);
        $this->assertSame(1, $report['blacklist']['errors']);
    }

    public function test_spf_lookup_limit_exceeded(): void
    {
        $includes = [];
        $txt = [];
        for ($i = 1; $i <= 11; $i++) {
            $includes[] = 'include:spf'.$i.'.test';
            $txt['spf'.$i.'.test'] = ['v=spf1 ip4:192.0.2.'.$i.' -all'];
        }
        $txt['many.test'] = ['v=spf1 '.implode(' ', $includes).' -all'];

        $report = (new EmailSecurityService(new FakeDnsLookup(txt: $txt)))->analyze('many.test');
        $checks = collect($report['checks'])->keyBy('key');

        $this->assertSame(11, $report['spf']['lookups']);
        $this->assertSame('fail', $checks['spf-lookups']['status']);
    }

    public function test_spf_self_include_is_reported_as_loop(): void
    {
        $dns = new FakeDnsLookup(txt: [
            'loop.test' => ['v=spf1 ip4:192.0.2.1 include:loop.test ~all'],
        ]);

        $report = (new EmailSecurityService($dns))->analyze('loop.test');
        $checks = collect($report['checks'])->keyBy('key');

        $this->assertTrue($report['spf']['loop']);
        $this->assertSame('fail', $checks['spf-lookups']['status']);
        $this->assertStringContainsString('includes itself', $checks['spf-lookups']['explanation']);
    }

    public function test_custom_dkim_selector_and_weak_key(): void
    {
        $dns = new FakeDnsLookup(txt: [
            'acme._domainkey.weak.test' => ['v=DKIM1; k=rsa; t=y; p='.$this->rsaPublicKey(1024)],
        ]);

        $report = (new EmailSecurityService($dns))->analyze('weak.test', 'acme');
        $checks = collect($report['checks'])->keyBy('key');

        $this->assertTrue($report['dkim']['custom_selector_found']);
        $this->assertSame(1024, $report['dkim']['keys'][0]['key_bits']);
        $this->assertTrue($report['dkim']['keys'][0]['testing']);
        $this->assertSame('warning', $checks['dkim']['status']);
    }

    public function test_wildcard_dkim_record_is_reported_once(): void
    {
        $dns = new FakeDnsLookup(txt: [
            '*._domainkey.nomail.test' => ['v=DKIM1; p='],
            'real._domainkey.nomail.test' => ['v=DKIM1; k=rsa; p='.$this->rsaPublicKey(2048)],
        ]);

        $report = (new EmailSecurityService($dns))->analyze('nomail.test');
        $this->assertTrue($report['dkim']['wildcard']);
        $this->assertSame(['*'], array_column($report['dkim']['keys'], 'selector'));
        $this->assertStringContainsString('wildcard', collect($report['checks'])->firstWhere('key', 'dkim')['explanation']);

        $report = (new EmailSecurityService($dns))->analyze('nomail.test', 'real');
        $this->assertTrue($report['dkim']['custom_selector_found']);
        $this->assertSame(['real', '*'], array_column($report['dkim']['keys'], 'selector'));
    }

    public function test_missing_custom_dkim_selector_fails_with_its_dns_name(): void
    {
        $report = (new EmailSecurityService(new FakeDnsLookup))->analyze('nokey.test', 'mail2024');
        $dkim = collect($report['checks'])->firstWhere('key', 'dkim');

        $this->assertFalse($report['dkim']['custom_selector_found']);
        $this->assertSame('fail', $dkim['status']);
        $this->assertStringContainsString('mail2024._domainkey.nokey.test', $dkim['explanation']);
    }

    public function test_parsers(): void
    {
        $service = new EmailSecurityService(new FakeDnsLookup);

        $tags = $service->parseDmarcTags('v=DMARC1; p=Quarantine; sp=none; pct=50; rua=mailto:a@x.test, mailto:b@x.test; adkim=s');
        $this->assertSame('quarantine', $tags['p']);
        $this->assertSame('none', $tags['sp']);
        $this->assertSame(50, $tags['pct']);
        $this->assertSame(['mailto:a@x.test', 'mailto:b@x.test'], $tags['rua']);
        $this->assertSame('s', $tags['adkim']);
        $this->assertSame('r', $tags['aspf']);

        $this->assertTrue($service->mxMatchesPolicy('mx1.example.com', ['*.example.com']));
        $this->assertFalse($service->mxMatchesPolicy('a.b.example.com', ['*.example.com']));
        $this->assertFalse($service->mxMatchesPolicy('mx.other.com', ['mx.example.com']));

        $revoked = $service->parseDkimKey('old', 'v=DKIM1; p=');
        $this->assertTrue($revoked['revoked']);

        $this->assertSame('B', $service->grade(85));
        $this->assertSame('F', $service->grade(10));
    }

    private function rsaPublicKey(int $bits): string
    {
        $key = openssl_pkey_new(['private_key_bits' => $bits, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $pem = openssl_pkey_get_details($key)['key'];

        return preg_replace('/-----[^-]+-----|\s+/', '', $pem);
    }
}
