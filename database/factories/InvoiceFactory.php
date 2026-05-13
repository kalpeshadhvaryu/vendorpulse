<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'vendor_id' => null,
            'number' => strtoupper(fake()->bothify('INV-####-????')),
            'status' => 'draft',
            'currency' => 'USD',
            'amount_cents' => fake()->numberBetween(1000, 500000),
            'tax_cents' => 0,
            'issued_on' => now()->toDateString(),
            'due_on' => now()->addDays(30)->toDateString(),
            'paid_at' => null,
            'description' => fake()->sentence(),
            'metadata' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function forVendor(Vendor $vendor): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $vendor->company_id,
            'vendor_id' => $vendor->id,
        ]);
    }
}
