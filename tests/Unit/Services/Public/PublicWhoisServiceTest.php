<?php

namespace Tests\Unit\Services\Public;

use App\Services\Public\PublicWhoisService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicWhoisServiceTest extends TestCase
{
    public function test_parses_rdap_whois_fields(): void
    {
        Http::fake([
            'https://rdap.org/domain/example.test' => Http::response([
                'events' => [
                    ['eventAction' => 'registration', 'eventDate' => '2020-01-01T00:00:00Z'],
                    ['eventAction' => 'expiration', 'eventDate' => '2030-06-15T00:00:00Z'],
                    ['eventAction' => 'last changed', 'eventDate' => '2024-03-01T12:00:00Z'],
                ],
                'status' => ['client transfer prohibited', 'active'],
                'nameservers' => [
                    ['ldhName' => 'ns1.example.test.'],
                    ['ldhName' => 'ns2.example.test.'],
                ],
                'entities' => [
                    [
                        'roles' => ['registrar'],
                        'handle' => 'REG-1',
                        'vcardArray' => [
                            'vcard',
                            [
                                ['version', [], 'text', '4.0'],
                                ['fn', [], 'text', 'Example Registrar'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new PublicWhoisService;
        $result = $service->lookup('example.test');

        $this->assertTrue($result['found']);
        $this->assertSame('example.test', $result['domain']);
        $this->assertSame('Example Registrar', $result['registrar']);
        $this->assertSame('2030-06-15T00:00:00+00:00', $result['expires_at']);
        $this->assertSame(['client transfer prohibited', 'active'], $result['statuses']);
        $this->assertSame(['ns1.example.test', 'ns2.example.test'], $result['nameservers']);
    }

    public function test_returns_not_found_when_rdap_fails(): void
    {
        Http::fake([
            'https://rdap.org/domain/missing.example' => Http::response([], 404),
        ]);

        $service = new PublicWhoisService;
        $result = $service->lookup('missing.example');

        $this->assertFalse($result['found']);
        $this->assertSame('missing.example', $result['domain']);
    }
}
