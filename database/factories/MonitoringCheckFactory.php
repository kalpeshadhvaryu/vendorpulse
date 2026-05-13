<?php

namespace Database\Factories;

use App\Models\MonitoringCheck;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoringCheck>
 */
class MonitoringCheckFactory extends Factory
{
    protected $model = MonitoringCheck::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'vendor_id' => null,
            'name' => fake()->words(3, true).' check',
            'type' => 'uptime',
            'endpoint' => fake()->url(),
            'configuration' => ['method' => 'GET', 'expected_status' => 200],
            'interval_seconds' => 300,
            'enabled' => true,
            'last_status' => null,
            'last_message' => null,
            'last_run_at' => null,
            'next_run_at' => now()->addMinutes(5),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
