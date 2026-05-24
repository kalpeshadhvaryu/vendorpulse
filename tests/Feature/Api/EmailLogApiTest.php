<?php

namespace Tests\Feature\Api;

use App\EmailMonitoring\Enums\EmailLogProcessingStatus;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_list_email_logs_for_active_organization(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $user->organizations()->attach($org->id, ['role' => 'member']);

        EmailLog::query()->create([
            'organization_id' => $org->id,
            'external_message_id' => 'msg-org-a',
            'subject' => 'Invoice from Acme',
            'from_email' => 'billing@acme.test',
            'received_at' => now(),
            'processing_status' => EmailLogProcessingStatus::Completed,
            'processing_meta' => [
                'invoice_automation' => [
                    'action' => 'created',
                    'invoice_number' => 'INV-100',
                ],
            ],
        ]);

        EmailLog::query()->create([
            'organization_id' => $otherOrg->id,
            'external_message_id' => 'msg-org-b',
            'subject' => 'Other org mail',
            'from_email' => 'other@example.test',
            'received_at' => now(),
            'processing_status' => EmailLogProcessingStatus::Completed,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/email-logs', [
            'X-Organization-Id' => $org->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Invoice from Acme')
            ->assertJsonPath('data.0.invoice_outcome.kind', 'invoice_created');
    }

    public function test_invoice_index_includes_email_source_when_linked(): void
    {
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $vendor = Vendor::factory()->create(['company_id' => $org->id]);
        $user->organizations()->attach($org->id, ['role' => 'member']);

        $log = EmailLog::query()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'external_message_id' => 'msg-invoice-link',
            'subject' => 'Your May invoice',
            'from_email' => 'billing@vendor.test',
            'received_at' => now(),
            'processing_status' => EmailLogProcessingStatus::Completed,
        ]);

        Invoice::query()->create([
            'organization_id' => $org->id,
            'vendor_id' => $vendor->id,
            'number' => 'INV-42',
            'status' => 'open',
            'currency' => 'USD',
            'amount_cents' => 10000,
            'tax_cents' => 0,
            'metadata' => [
                'auto_generated' => true,
                'source' => 'email_monitoring',
                'email_log_id' => $log->id,
            ],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/invoices', [
            'X-Organization-Id' => $org->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.email_source.email_log_id', $log->id)
            ->assertJsonPath('data.0.email_source.subject', 'Your May invoice')
            ->assertJsonPath('data.0.email_source.auto_generated', true);
    }
}
