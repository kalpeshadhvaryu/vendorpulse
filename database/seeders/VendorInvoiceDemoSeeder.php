<?php

namespace Database\Seeders;

use App\Enums\BillingCycle;
use App\Enums\VendorStatus;
use App\Enums\VendorType;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorInvoiceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping VendorInvoiceDemoSeeder outside local/testing environment.');

            return;
        }

        $organization = Organization::query()->firstOrCreate(['name' => 'Demo Organization']);
        $actor = User::query()->where('email', 'test@example.com')->first();

        $vendor = Vendor::query()->firstOrNew([
            'company_id' => $organization->id,
            'name' => 'Test Vendor',
        ]);

        $vendor->fill([
            'vendor_type' => VendorType::Saas->value,
            'billing_email' => 'billing@testvendor.example',
            'support_email' => 'support@testvendor.example',
            'website' => 'https://testvendor.example',
            'currency' => 'USD',
            'expected_amount' => 199.00,
            'billing_cycle' => BillingCycle::Monthly->value,
            'renewal_date' => now()->addMonth()->toDateString(),
            'auto_detect_invoices' => true,
            'auto_fetch_email' => true,
            'match_inbound_from_website_domain' => true,
            'monitoring_enabled' => false,
            'notes' => 'Local demo vendor for onboarding and testing invoice workflows.',
            'status' => VendorStatus::Active->value,
            'created_by' => $vendor->exists ? $vendor->created_by : $actor?->id,
            'updated_by' => $actor?->id,
        ]);
        $vendor->save();

        $invoice = Invoice::query()->firstOrNew([
            'organization_id' => $organization->id,
            'number' => 'TEST-INV-001',
        ]);

        $invoice->fill([
            'vendor_id' => $vendor->id,
            'status' => 'pending',
            'currency' => 'USD',
            'amount_cents' => 19900,
            'tax_cents' => 0,
            'issued_on' => now()->toDateString(),
            'due_on' => now()->addDays(14)->toDateString(),
            'paid_at' => null,
            'description' => 'Test Invoice for local verification of vendor and billing flows.',
            'metadata' => [
                'source' => 'local-seeder',
                'demo' => true,
            ],
            'created_by' => $invoice->exists ? $invoice->created_by : $actor?->id,
            'updated_by' => $actor?->id,
        ]);
        $invoice->save();

        $this->command?->info('Seeded demo records: Test Vendor + TEST-INV-001 on Demo Organization.');
    }
}
