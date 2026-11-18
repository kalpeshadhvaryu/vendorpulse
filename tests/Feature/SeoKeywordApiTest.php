<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SeoKeyword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SeoKeywordApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_list_and_update_keyword(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $user->organizations()->attach($org->id);

        Sanctum::actingAs($user);

        $create = $this->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/marketing-seo/keywords', [
                'phrase' => 'best hotel veraval',
                'priority' => 'high',
                'target_url' => 'https://example.com/hotels',
                'country' => 'in',
                'device' => 'desktop',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.phrase', 'best hotel veraval')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.target_domain', 'example.com');

        $id = $create->json('data.id');

        $this->withHeader('X-Organization-Id', $org->id)
            ->getJson('/api/v1/marketing-seo/keywords')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $id)
            ->assertJsonPath('data.provider_configured', false);

        $this->withHeader('X-Organization-Id', $org->id)
            ->patchJson('/api/v1/marketing-seo/keywords/'.$id, ['status' => 'paused'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paused');

        $this->assertDatabaseHas('seo_keywords', [
            'id' => $id,
            'organization_id' => $org->id,
            'status' => 'paused',
        ]);
    }

    public function test_check_without_provider_stores_error_row(): void
    {
        config(['seo-keywords.provider' => 'none']);

        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $user->organizations()->attach($org->id);
        Sanctum::actingAs($user);

        $keyword = SeoKeyword::query()->create([
            'organization_id' => $org->id,
            'created_by' => $user->id,
            'phrase' => 'vendorpulse demo',
            'priority' => 'medium',
            'target_url' => 'https://vendorpulse.example',
            'target_domain' => 'vendorpulse.example',
            'country' => 'in',
            'language' => 'en',
            'device' => 'desktop',
            'status' => 'active',
        ]);

        $response = $this->withHeader('X-Organization-Id', $org->id)
            ->postJson('/api/v1/marketing-seo/keywords/'.$keyword->id.'/check');

        $response->assertOk()
            ->assertJsonPath('data.provider_configured', false);

        $this->assertNotNull($response->json('data.check.error'));
        $this->assertDatabaseCount('seo_keyword_checks', 1);
    }
}
