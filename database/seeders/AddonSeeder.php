<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Addon;

class AddonSeeder extends Seeder
{
    public function run(): void
    {
        // Hapus fitur standar & deprecated dari tabel addons
        Addon::whereIn('slug', [
            'olt_management',
            'mapping_gis',
            'telegram_bot',
            'mikhmon_agent',
            'mitra_warung',
            'toko_online',
            'shop_products',
        ])->delete();

        // 1. MIKHMON Online
        Addon::updateOrCreate(['slug' => 'mikhmon_online'], [
            'name'          => 'MIKHMON ONLINE (RouterOS v6 & v7)',
            'description'   => 'Kelola Voucher MikroTik & Hotspot online dari mana saja tanpa IP publik statis. Terisolasi per tenant dengan auto-heal template & multi-session.',
            'price'         => 10000,
            'billing_cycle' => 'monthly',
            'icon'          => 'Wifi',
            'route_name'    => '/admin/mikhmon',
            'feature_key'   => 'mikhmon_online',
            'is_active'     => true,
        ]);

        // 2. Paket Isolir & Webproxy
        Addon::updateOrCreate(['slug' => 'paket_isolir'], [
            'name'          => 'PAKET ISOLIR & WEBPROXY',
            'description'   => 'Konfigurasi otomatis isolir MikroTik — Web Proxy error.html, redirect port 80, firewall filter, dan generator script langsung.',
            'price'         => 10000,
            'billing_cycle' => 'lifetime',
            'icon'          => 'ShieldAlert',
            'route_name'    => '/admin/addons',
            'feature_key'   => 'paket_isolir',
            'is_active'     => true,
        ]);

        // 3. GenieACS TR-069 & Cloud ONT
        Addon::updateOrCreate(['slug' => 'genieacs_management'], [
            'name'          => 'GENIEACS TR-069 & CLOUD ONT',
            'description'   => 'Manajemen massal dan konfigurasi otomatis ONT / Modem pelanggan (ZTE, Huawei, Fiberhome, VSOL) via protokol TR-069. Pantau redaman optik dBm, ubah WiFi & remote reboot.',
            'price'         => 1000,
            'billing_cycle' => 'monthly',
            'icon'          => 'Wifi',
            'route_name'    => '/admin/ont-devices',
            'feature_key'   => 'genieacs_management',
            'is_active'     => true,
        ]);

        // 4. RADIUS Server & RFC 3576 CoA
        Addon::updateOrCreate(['slug' => 'radius_server'], [
            'name'          => 'RADIUS SERVER & RFC 3576 CoA',
            'description'   => 'Autentikasi AAA terpusat untuk ribuan akun PPPoE & Hotspot dengan dukungan Change of Authorization (CoA) dan isolir instan.',
            'price'         => 20000,
            'billing_cycle' => 'monthly',
            'icon'          => 'Radio',
            'route_name'    => '/admin/radius',
            'feature_key'   => 'radius_server',
            'is_active'     => true,
        ]);
    }
}
