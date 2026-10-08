<?php

namespace Database\Factories;

use App\Models\VpnUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<VpnUser>
 */
class VpnUserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '08' . fake()->numerify('##########'),
            'password' => static::$password ??= Hash::make('password'),
            'saldo' => 50000.00,
            'bonus_saldo' => 0.00,
            'is_active' => true,
        ];
    }
}
