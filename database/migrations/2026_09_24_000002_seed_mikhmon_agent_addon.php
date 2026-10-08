<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('addons')) {
            $existing = DB::table('addons')->where('slug', 'mikhmon_agent')->first();
            if (!$existing) {
                DB::table('addons')->insert([
                    'name'          => 'MITRA WARUNG & RESELLER VOUCHER (MIKHMON)',
                    'slug'          => 'mikhmon_agent',
                    'description'   => 'Modul kasir warung, sistem kulakan voucher potong saldo, cetak struk thermal Bluetooth & kirim WhatsApp otomatis.',
                    'price'         => 15000.00,
                    'billing_cycle' => 'monthly',
                    'icon'          => 'Store',
                    'route_name'    => '/admin/warung',
                    'feature_key'   => 'mikhmon_agent',
                    'is_active'     => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('addons')) {
            DB::table('addons')->where('slug', 'mikhmon_agent')->delete();
        }
    }
};
