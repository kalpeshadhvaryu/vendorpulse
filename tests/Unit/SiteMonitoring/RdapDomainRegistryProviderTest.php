<?php

namespace Tests\Unit\SiteMonitoring;

use App\SiteMonitoring\Infrastructure\RdapDomainRegistryProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RdapDomainRegistryProviderTest extends TestCase
{
    public function test_parses_expiration_from_rdap_json(): void
    {
        Http::fake([
            'https://rdap.org/domain/example.test' => Http::response([
                'events' => [
                    ['eventAction' => 'registration', 'eventDate' => '2020-01-01T00:00:00Z'],
                    ['eventAction' => 'expiration', 'eventDate' => '2030-06-15T00:00:00Z'],
                ],
                'entities' => [],
            ], 200),
        ]);

        $provider = new RdapDomainRegistryProvider;
        $result = $provider->lookupExpiry('example.test');

        $this->assertNotNull($result);
        $this->assertSame('2030-06-15', $result['expires_at']->format('Y-m-d'));
    }

    public function test_returns_null_when_http_fails(): void
    {
        Http::fake([
            'https://rdap.org/domain/missing.example' => Http::response([], 404),
        ]);

        $provider = new RdapDomainRegistryProvider;

        $this->assertNull($provider->lookupExpiry('missing.example'));
    }
}
