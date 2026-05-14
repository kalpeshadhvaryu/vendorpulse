<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_org_scoped_routes_without_header_and_see_all_data(): void
    {
        $admin = User::factory()->create([
            'default_organization_id' => null,
        ]);

        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $admin->organizations()->attach($orgA->id, ['role' => 'admin']);

        Vendor::factory()->create([
            'company_id' => $orgA->id,
            'name' => 'Vendor Alpha',
        ]);
        Vendor::factory()->create([
            'company_id' => $orgB->id,
            'name' => 'Vendor Beta',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/vendors');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_without_header_is_not_limited_by_default_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $admin = User::factory()->create([
            'default_organization_id' => $orgA->id,
        ]);

        $admin->organizations()->attach($orgA->id, ['role' => 'admin']);

        Vendor::factory()->create([
            'company_id' => $orgA->id,
            'name' => 'Vendor Alpha',
        ]);
        Vendor::factory()->create([
            'company_id' => $orgB->id,
            'name' => 'Vendor Beta',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/vendors');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_non_admin_cannot_use_another_organization_context(): void
    {
        $user = User::factory()->create();
        $memberOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();

        $user->organizations()->attach($memberOrg->id, ['role' => 'member']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/vendors', [
            'X-Organization-Id' => $otherOrg->id,
        ]);

        $response->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_create_organization_and_assign_user_to_it(): void
    {
        $admin = User::factory()->create();
        $adminOrg = Organization::factory()->create();
        $targetUser = User::factory()->create([
            'email' => 'member@example.test',
        ]);

        $admin->organizations()->attach($adminOrg->id, ['role' => 'admin']);

        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/v1/organizations', [
            'name' => 'Managed Org',
            'slug' => 'managed-org',
            'owner_user_email' => $targetUser->email,
            'set_owner_default' => true,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Managed Org');

        $organizationId = (string) $createResponse->json('data.id');

        $assignResponse = $this->postJson("/api/v1/organizations/{$organizationId}/members", [
            'user_email' => $targetUser->email,
            'role' => 'member',
            'set_default' => true,
        ]);

        $assignResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organizationId,
            'user_id' => $targetUser->id,
            'role' => 'member',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'default_organization_id' => $organizationId,
        ]);
    }

    public function test_non_admin_cannot_assign_user_to_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $targetUser = User::factory()->create([
            'email' => 'target@example.test',
        ]);

        $user->organizations()->attach($organization->id, ['role' => 'member']);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/organizations/{$organization->id}/members", [
            'user_email' => $targetUser->email,
            'role' => 'member',
            'set_default' => true,
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('organization_user', [
            'organization_id' => $organization->id,
            'user_id' => $targetUser->id,
            'role' => 'member',
        ]);
    }

    public function test_non_admin_cannot_create_organization(): void
    {
        $user = User::factory()->create();
        $existingOrg = Organization::factory()->create();

        $user->organizations()->attach($existingOrg->id, ['role' => 'member']);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/organizations', [
            'name' => 'Should Not Be Created',
            'slug' => 'should-not-be-created',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('organizations', [
            'slug' => 'should-not-be-created',
        ]);
    }

    public function test_admin_can_create_user_and_assign_to_organization(): void
    {
        $admin = User::factory()->create();
        $adminOrg = Organization::factory()->create();
        $targetOrg = Organization::factory()->create();

        $admin->organizations()->attach($adminOrg->id, ['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/organizations/{$targetOrg->id}/users", [
            'name' => 'New Team Member',
            'email' => 'new-member@example.test',
            'password' => 'password123',
            'role' => 'member',
            'set_default' => true,
            'email_verified' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'new-member@example.test');

        $createdUserId = (string) $response->json('data.id');

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $targetOrg->id,
            'user_id' => $createdUserId,
            'role' => 'member',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $createdUserId,
            'email' => 'new-member@example.test',
            'default_organization_id' => $targetOrg->id,
        ]);
    }

    public function test_admin_create_organization_with_missing_owner_email_returns_422_and_does_not_create_org(): void
    {
        $admin = User::factory()->create();
        $adminOrg = Organization::factory()->create();

        $admin->organizations()->attach($adminOrg->id, ['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/organizations', [
            'name' => 'Should Not Persist',
            'slug' => 'should-not-persist',
            'owner_user_email' => 'missing-user@example.test',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('organizations', [
            'slug' => 'should-not-persist',
        ]);
    }

    public function test_admin_can_list_all_users_with_organization_memberships(): void
    {
        $admin = User::factory()->create();
        $adminOrg = Organization::factory()->create(['name' => 'Admin Org']);
        $orgA = Organization::factory()->create(['name' => 'Org A']);
        $orgB = Organization::factory()->create(['name' => 'Org B']);

        $memberA = User::factory()->create(['email' => 'member-a@example.test']);
        $memberB = User::factory()->create(['email' => 'member-b@example.test']);

        $admin->organizations()->attach($adminOrg->id, ['role' => 'admin']);
        $memberA->organizations()->attach($orgA->id, ['role' => 'member']);
        $memberB->organizations()->attach($orgB->id, ['role' => 'owner']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/organizations/users');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['email' => 'member-a@example.test'])
            ->assertJsonFragment(['email' => 'member-b@example.test'])
            ->assertJsonFragment(['name' => 'Org A'])
            ->assertJsonFragment(['name' => 'Org B']);
    }

    public function test_non_admin_cannot_list_all_users_with_organization_memberships(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $user->organizations()->attach($org->id, ['role' => 'member']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/organizations/users');

        $response->assertForbidden();
    }

    public function test_admin_can_soft_delete_organization(): void
    {
        $admin = User::factory()->create();
        $adminOrg = Organization::factory()->create();
        $targetOrg = Organization::factory()->create();

        $admin->organizations()->attach($adminOrg->id, ['role' => 'admin']);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/organizations/{$targetOrg->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('organizations', [
            'id' => $targetOrg->id,
        ]);
    }

    public function test_admin_can_permanently_delete_organization(): void
    {
        $admin = User::factory()->create();
        $adminOrg = Organization::factory()->create();
        $targetOrg = Organization::factory()->create();

        $admin->organizations()->attach($adminOrg->id, ['role' => 'admin']);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/organizations/{$targetOrg->id}")->assertOk();

        $response = $this->deleteJson("/api/v1/organizations/{$targetOrg->id}/force");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('organizations', [
            'id' => $targetOrg->id,
        ]);
    }
}
