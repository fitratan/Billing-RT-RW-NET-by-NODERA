<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Paket 10 Mbps', 'Paket 20 Mbps', 'Paket 50 Mbps']),
            'type' => 'pppoe',
            'price' => fake()->numberBetween(100000, 1000000),
            'monthly_price' => fake()->numberBetween(100000, 1000000),
            'profile_normal' => 'default',
            'profile_isolir' => 'isolir',
            'max_customers' => fake()->randomElement([100, 500, 1000, 5000]),
            'max_routers' => fake()->randomElement([1, 3, 5, 10]),
            'is_active' => true,
        ];
    }

    public function subscription(): static
    {
        return $this->state(fn () => [
            'type' => 'subscription',
            'tenant_id' => null,
        ]);
    }
}
