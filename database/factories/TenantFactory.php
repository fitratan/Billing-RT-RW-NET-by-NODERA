<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(2),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
            'expired_at' => now()->addYear(),
            'settings' => [
                'max_customers' => 500,
                'max_routers' => 5,
                'subscribed_price' => 100000,
                'subscribed_duration' => 1,
            ],
        ];
    }

    public function expired(): static
    {
        return $this->state(fn(array $a) => [
            'is_active' => false,
            'expired_at' => now()->subDay(),
        ]);
    }
}
