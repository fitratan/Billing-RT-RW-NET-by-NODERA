<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'customer_name' => fake()->name(),
            'customer_id' => \App\Models\Customer::factory(),
            'invoice_number' => 'INV-' . strtoupper(fake()->bothify('??####')),
            'amount' => fake()->numberBetween(50000, 500000),
            'due_date' => now()->addDays(7),
            'period' => now()->format('Y-m'),
            'paid' => false,
            'status' => 'pending',
            'tenant_id' => \App\Models\Tenant::factory(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn(array $a) => [
            'paid' => true,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
