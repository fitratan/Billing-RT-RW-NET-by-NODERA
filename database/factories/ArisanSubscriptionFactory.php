<?php

namespace Database\Factories;

use App\Models\ArisanSubscription;
use App\Models\VpnUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ArisanSubscription>
 */
class ArisanSubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vpn_user_id' => VpnUser::factory(),
            'subdomain' => 'arisan-' . Str::lower(Str::random(6)),
            'business_name' => fake()->company(),
            'price' => 10000.00,
            'order_date' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'ACTIVE',
            'auto_renew' => false,
            'saldo_deducted' => 10000.00,
            'admin_password_hash' => bcrypt('secret123'),
            'notes' => null,
        ];
    }
}
