<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\RegistrationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegistrationRequestFactory extends Factory
{
    protected $model = RegistrationRequest::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'slug' => fake()->unique()->slug(2),
            'email' => fake()->email(),
            'phone' => fake()->phoneNumber(),
            'company' => fake()->company(),
            'package_id' => Package::factory(),
            'duration' => fake()->randomElement([1, 3, 6, 12]),
            'status' => 'pending',
        ];
    }
}
