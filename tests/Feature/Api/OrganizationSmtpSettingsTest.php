<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationSmtpSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_and_read_organization_smtp_settings(): void
    {
        $admin = User::factory()->create();
        $organization = Organization::factory()->create();

        $admin->organizations()->attach($organization->id, ['role' => 'admin']);
        $admin->forceFill(['default_organization_id' => $organization->id])->save();

        Sanctum::actingAs($admin);

        $update = $this->putJson('/api/v1/organization/smtp-settings', [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'mailer@example.com',
            'password' => 'super-secret',
            'encryption' => 'tls',
            'from_address' => 'no-reply@example.com',
            'from_name' => 'VendorPulse',
            'reply_to_address' => 'support@example.com',
            'reply_to_name' => 'Support',
        ]);

        $update->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.host', 'smtp.example.com')
            ->assertJsonPath('data.has_password', true);

        $show = $this->getJson('/api/v1/organization/smtp-settings');

        $show->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.host', 'smtp.example.com')
            ->assertJsonPath('data.from_address', 'no-reply@example.com')
            ->assertJsonMissingPath('data.password');
    }

    public function test_non_admin_cannot_manage_organization_smtp_settings(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $user->organizations()->attach($organization->id, ['role' => 'member']);
        $user->forceFill(['default_organization_id' => $organization->id])->save();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/organization/smtp-settings')->assertForbidden();

        $this->putJson('/api/v1/organization/smtp-settings', [
            'host' => 'smtp.example.com',
            'port' => 587,
            'from_address' => 'no-reply@example.com',
            'from_name' => 'VendorPulse',
        ])->assertForbidden();
    }

    public function test_test_email_requires_smtp_config_first(): void
    {
        $admin = User::factory()->create();
        $organization = Organization::factory()->create();

        $admin->organizations()->attach($organization->id, ['role' => 'admin']);
        $admin->forceFill(['default_organization_id' => $organization->id])->save();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/organization/smtp-settings/test', [
            'to' => 'receiver@example.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false);
    }
}
