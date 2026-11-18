<?php

namespace Tests\Feature;

use App\Models\CloudflareZone;
use App\Models\DnsDomain;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DnsDomainApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_can_manage_manual_dns_records(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $org = Organization::factory()->create();
        $user->organizations()->attach($org->id);

        Sanctum::actingAs($user);

        $create = $this->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/dns/domains', [
                'provider' => 'manual',
                'name' => 'manual.example.com',
            ])
            ->assertCreated()
            ->json('data');

        $domainId = $create['id'];

        $this->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/dns/domains/'.$domainId.'/records', [
                'type' => 'A',
                'name' => '@',
                'content' => '1.2.3.4',
                'ttl' => 3600,
            ])
            ->assertCreated()
            ->assertJsonPath('data.content', '1.2.3.4');

        $this->withHeader('X-Organization-Id', $org->id)
            ->getJson('/api/v1/dns/domains/'.$domainId.'/records')
            ->assertOk()
            ->assertJsonPath('data.items.0.type', 'A');

        $this->assertDatabaseHas('dns_records', [
            'dns_domain_id' => $domainId,
            'content' => '1.2.3.4',
        ]);
    }

    public function test_cloudflare_dns_domain_requires_assigned_zone(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $org = Organization::factory()->create();
        $user->organizations()->attach($org->id);

        $zone = CloudflareZone::query()->create([
            'cf_zone_id' => 'cf-zone-dns-1',
            'name' => 'cf.example.com',
            'status' => 'active',
            'paused' => false,
            'organization_id' => $org->id,
            'assigned_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/dns/domains', [
                'provider' => 'cloudflare',
                'cloudflare_zone_id' => $zone->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'cloudflare')
            ->assertJsonPath('data.name', 'cf.example.com');

        $this->assertDatabaseHas('dns_domains', [
            'organization_id' => $org->id,
            'provider' => DnsDomain::PROVIDER_CLOUDFLARE,
            'cloudflare_zone_id' => $zone->id,
        ]);
    }
}
