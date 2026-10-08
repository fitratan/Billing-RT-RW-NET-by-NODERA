<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminUsername = env('ADMIN_USERNAME', 'admin');
        $adminPassword = env('ADMIN_PASSWORD', 'admin123');

        $exists = DB::table('users')->where('username', $adminUsername)->exists();
        if (!$exists) {
            DB::table('users')->insert([
                [
                    'username' => $adminUsername,
                    'password' => Hash::make($adminPassword),
                    'name' => 'NODERA Administrator',
                    'email' => 'admin@isp.local',
                    'role' => 'admin',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        }

        // Default Settings
        $settings = [
            ['key' => 'APP_NAME', 'value' => 'NODERA BILLING'],
            ['key' => 'COMPANY_NAME', 'value' => 'ISP RT-RW Net Mandiri'],
            ['key' => 'MIKROTIK_HOST', 'value' => '192.168.88.1'],
            ['key' => 'MIKROTIK_USER', 'value' => 'admin'],
            ['key' => 'MIKROTIK_PORT', 'value' => '8728'],
            ['key' => 'GENIEACS_URL', 'value' => 'http://localhost:7557'],
            ['key' => 'STANDALONE_MODE', 'value' => 'true'],
        ];

        foreach ($settings as $st) {
            DB::table('settings')->updateOrInsert(['key' => $st['key']], $st);
        }
    }
}
