<?php

namespace Tests\Feature;

use App\Models\CloudflareZone;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CloudflareZoneApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_zone_and_org_can_list_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create(['is_admin' => false]);
        $org = Organization::factory()->create(['name' => 'Client Co']);
        $member->organizations()->attach($org->id);

        $zone = CloudflareZone::query()->create([
            'cf_zone_id' => 'cf-zone-1',
            'name' => 'client.example',
            'status' => 'active',
            'paused' => false,
            'plan_name' => 'Free',
            'name_servers' => ['a.ns.cloudflare.com', 'b.ns.cloudflare.com'],
        ]);

        Sanctum::actingAs($admin);
        $this->patchJson('/api/v1/admin/cloudflare/zones/'.$zone->id.'/assign', [
            'organization_id' => $org->id,
        ])->assertOk()
            ->assertJsonPath('data.organization_id', $org->id);

        Sanctum::actingAs($member);
        $this->withHeader('X-Organization-Id', $org->id)
            ->getJson('/api/v1/cloudflare/domains')
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'client.example');

        $otherOrg = Organization::factory()->create();
        $member->organizations()->attach($otherOrg->id);
        $this->withHeader('X-Organization-Id', $otherOrg->id)
            ->getJson('/api/v1/cloudflare/domains')
            ->assertOk()
            ->assertJsonPath('data.items', []);
    }

    public function test_non_admin_cannot_sync_catalog(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/admin/cloudflare/zones/sync')
            ->assertForbidden();
    }
}
