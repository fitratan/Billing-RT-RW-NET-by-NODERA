<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Activate standard Superadmin packages
        DB::table('packages')
            ->whereNull('tenant_id')
            ->whereIn('name', ['Starter (500 Pelanggan)', 'Boost (1.000 Pelanggan)', 'Ultra (Unlimited Pelanggan)'])
            ->update([
                'type' => 'subscription',
                'is_active' => true,
            ]);

        // Clean up temporary packages if created
        DB::table('packages')
            ->whereNull('tenant_id')
            ->whereIn('name', ['Starter', 'Bisnis', 'Professional', 'Enterprise'])
            ->delete();

        $packages = [
            [
                'name' => 'Starter (500 Pelanggan)',
                'type' => 'subscription',
                'price' => 125000,
                'monthly_price' => 125000,
                'semi_annual_price' => 700000,
                'annual_price' => 1300000,
                'duration_options' => '1,3,6,12',
                'max_customers' => 500,
                'max_routers' => 5,
                'is_popular' => false,
                'is_active' => true,
                'description' => 'Paket pemula hingga 500 pelanggan dan 5 router MikroTik.',
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Boost (1.000 Pelanggan)',
                'type' => 'subscription',
                'price' => 250000,
                'monthly_price' => 250000,
                'semi_annual_price' => 1400000,
                'annual_price' => 2600000,
                'duration_options' => '1,6,12',
                'max_customers' => 1000,
                'max_routers' => 10,
                'is_popular' => true,
                'is_active' => true,
                'description' => 'Paket paling populer untuk ISP berkembang hingga 1.000 pelanggan.',
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Ultra (Unlimited Pelanggan)',
                'type' => 'subscription',
                'price' => 500000,
                'monthly_price' => 500000,
                'semi_annual_price' => 2800000,
                'annual_price' => 5000000,
                'duration_options' => '1,6,12',
                'max_customers' => 0,
                'max_routers' => 0,
                'is_popular' => false,
                'is_active' => true,
                'description' => 'Kapasitas tanpa batas pelanggan dan router untuk ISP skala besar.',
                'tenant_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($packages as $pkg) {
            $existing = DB::table('packages')
                ->whereNull('tenant_id')
                ->where('name', $pkg['name'])
                ->first();

            if (!$existing) {
                DB::table('packages')->insert($pkg);
            } else {
                DB::table('packages')->where('id', $existing->id)->update([
                    'type' => 'subscription',
                    'price' => $pkg['price'],
                    'monthly_price' => $pkg['monthly_price'],
                    'semi_annual_price' => $pkg['semi_annual_price'],
                    'annual_price' => $pkg['annual_price'],
                    'duration_options' => $pkg['duration_options'],
                    'max_customers' => $pkg['max_customers'],
                    'max_routers' => $pkg['max_routers'],
                    'is_popular' => $pkg['is_popular'],
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
    }
};
