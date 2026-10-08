<?php

namespace Database\Factories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'description' => fake()->sentence(3),
            'amount' => fake()->numberBetween(10000, 5000000),
            'category' => fake()->randomElement(['Operasional', 'Listrik', 'Internet', 'Gaji', 'Sewa', 'Lainnya']),
            'date' => fake()->dateTimeBetween('-3 months', 'now'),
            'tenant_id' => null,
        ];
    }
}
