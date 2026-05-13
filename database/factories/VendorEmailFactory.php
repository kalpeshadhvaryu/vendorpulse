<?php

namespace Database\Factories;

use App\Models\Vendor;
use App\Models\VendorEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorEmail>
 */
class VendorEmailFactory extends Factory
{
    protected $model = VendorEmail::class;

    public function definition(): array
    {
        $vendor = Vendor::query()->withoutGlobalScopes()->inRandomOrder()->first()
            ?? Vendor::factory()->create();

        return [
            'organization_id' => $vendor->company_id,
            'vendor_id' => $vendor->id,
            'email' => fake()->unique()->companyEmail(),
            'label' => fake()->optional()->word(),
            'purpose' => 'general',
            'is_monitored' => true,
            'last_checked_at' => null,
            'last_check_status' => null,
            'metadata' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
