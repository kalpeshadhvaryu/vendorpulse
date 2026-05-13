<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\VendorStatus;
use App\Enums\VendorType;
use App\Models\Organization;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return [
            'company_id' => Organization::factory(),
            'name' => fake()->company(),
            'vendor_type' => fake()->randomElement(VendorType::cases())->value,
            'billing_email' => fake()->optional()->companyEmail(),
            'support_email' => fake()->optional()->companyEmail(),
            'website' => fake()->optional()->url(),
            'currency' => 'USD',
            'expected_amount' => fake()->optional()->randomFloat(2, 50, 50000),
            'billing_cycle' => fake()->randomElement(BillingCycle::cases())->value,
            'renewal_date' => fake()->optional()->date(),
            'auto_detect_invoices' => fake()->boolean(30),
            'auto_fetch_email' => fake()->boolean(80),
            'match_inbound_from_website_domain' => fake()->boolean(10),
            'monitoring_enabled' => fake()->boolean(40),
            'notes' => fake()->optional()->sentence(),
            'status' => VendorStatus::Active->value,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
