<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Addon;

class AddonSeeder extends Seeder
{
    public function run(): void
    {
        // 1. OLT PON & ONU Management (NMS Terpadu)
        Addon::updateOrCreate(['slug' => 'olt_management'], [
            'name'          => 'MANAJEMEN OLT & ONT NMS (GPON & EPON)',
            'description'   => 'Sistem monitoring dan manajemen OLT EPON / GPON terpadu (HiOSO, C-Data, VSOL, ZTE, Huawei, HSGQ). Pantau optical power Rx/Tx dBm, auto-discovery ONU unconfigured, status link fiber, dan remote reboot.',
            'price'         => 25000,
            'billing_cycle' => 'monthly',
            'icon'          => 'Server',
            'route_name'    => '/admin/olt',
            'feature_key'   => 'olt_management',
            'is_active'     => true,
        ]);

        // 2. MIKHMON Online
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

        // 3. Paket Isolir & Webproxy
        Addon::updateOrCreate(['slug' => 'paket_isolir'], [
            'name'          => 'PAKET ISOLIR & WEBPROXY',
            'description'   => 'Konfigurasi otomatis isolir MikroTik — Web Proxy error.html, redirect port 80/443, firewall filter, dan generator script langsung.',
            'price'         => 10000,
            'billing_cycle' => 'lifetime',
            'icon'          => 'ShieldAlert',
            'route_name'    => '/admin/addons',
            'feature_key'   => 'paket_isolir',
            'is_active'     => true,
        ]);

        // 4. GenieACS TR-069 & Cloud ONT
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

        // 5. RADIUS Server & RFC 3576 CoA
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

        // 6. Mapping GIS & ODP FTTH
        Addon::updateOrCreate(['slug' => 'mapping_gis'], [
            'name'          => 'PETA JARINGAN GIS & FTTH ODP',
            'description'   => 'Peta geospasial interaktif FTTH untuk dokumentasi kabel fiber optik, tiang, ODC, ODP, dan drop core dengan estimasi jarak OTDR.',
            'price'         => 15000,
            'billing_cycle' => 'monthly',
            'icon'          => 'MapPin',
            'route_name'    => '/admin/map',
            'feature_key'   => 'mapping_gis',
            'is_active'     => true,
        ]);

        // 7. Gateway Notifikasi WhatsApp
        Addon::updateOrCreate(['slug' => 'whatsapp_notification'], [
            'name'          => 'GATEWAY NOTIFIKASI WHATSAPP',
            'description'   => 'Kirim broadcast tagihan otomatis, struk pembayaran lunas, pengingat jatuh tempo, dan notifikasi gangguan via WhatsApp Gateway.',
            'price'         => 15000,
            'billing_cycle' => 'monthly',
            'icon'          => 'MessageSquare',
            'route_name'    => '/admin/broadcast',
            'feature_key'   => 'whatsapp_notification',
            'is_active'     => true,
        ]);

        // 8. Fitur Warung / Reseller Voucher
        Addon::updateOrCreate(['slug' => 'mikhmon_agent'], [
            'name'          => 'FITUR WARUNG (RESELLER VOUCHER)',
            'description'   => 'Modul kasir warung, sistem kulakan voucher potong saldo, cetak struk thermal Bluetooth & kirim WhatsApp otomatis.',
            'price'         => 15000,
            'billing_cycle' => 'monthly',
            'icon'          => 'Store',
            'route_name'    => '/admin/warung',
            'feature_key'   => 'mikhmon_agent',
            'is_active'     => true,
        ]);
    }
}
