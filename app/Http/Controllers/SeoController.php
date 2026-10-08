<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeoController extends Controller
{
    /**
     * Data Master Solusi & Landing Pages
     */
    public static function getSolutions(): array
    {
        return [
            'software-billing-isp' => [
                'slug' => 'software-billing-isp',
                'title' => 'Software Billing ISP | NODERA',
                'h1' => 'Software Billing ISP & Manajemen Jaringan Terpadu',
                'meta_description' => 'Software billing ISP lengkap dengan manajemen pelanggan, otomatisasi MikroTik, billing PPPoE & Static IP, WhatsApp billing, invoice otomatis, dan QRIS payment gateway.',
                'primary_keyword' => 'software billing ISP',
                'secondary_keywords' => ['aplikasi billing ISP', 'software billing ISP Indonesia', 'sistem billing internet'],
                'tagline' => 'Solusi Billing & Operasional ISP Skala Komersial',
                'hero_subtitle' => 'Platform billing ISP all-in-one yang mengotomasi seluruh alur kerja operasional: mulai dari sinkronisasi MikroTik, pembuatan invoice tagihan otomatis, gateway WhatsApp, pembayaran QRIS realtime, hingga monitoring OLT dan peta GIS.',
                'breadcrumbs' => [
                    ['title' => 'Beranda', 'url' => '/'],
                    ['title' => 'Solusi', 'url' => '/#demos'],
                    ['title' => 'Software Billing ISP', 'url' => '/software-billing-isp'],
                ],
                'features' => [
                    [
                        'icon' => 'fa-server',
                        'title' => 'Multi-Router MikroTik Terpusat',
                        'desc' => 'Kelola puluhan router MikroTik di berbagai lokasi POP tanpa batasan jumlah perangkat. Sinkronisasi dua arah otomatis antara database billing dan RouterOS.',
                    ],
                    [
                        'icon' => 'fa-file-text-o',
                        'title' => 'Invoice & Jatuh Tempo Otomatis',
                        'desc' => 'Generate tagihan bulanan otomatis setiap tanggal cut-off, lengkap dengan PDF invoice resmi, rincian biaya langganan, dan QR Code pembayaran.',
                    ],
                    [
                        'icon' => 'fa-whatsapp',
                        'title' => 'WhatsApp Gateway & Payment Reminder',
                        'desc' => 'Kirim notifikasi tagihan H-3, hari H, dan pesan konfirmasi pembayaran lunas secara otomatis langsung ke nomor WhatsApp pelanggan.',
                    ],
                    [
                        'icon' => 'fa-qrcode',
                        'title' => 'Auto Approval QRIS & Payment Gateway',
                        'desc' => 'Integrasi QRIS Dinamis dan Virtual Account. Sistem otomatis mendeteksi mutasi dan membuka isolir router pelanggan dalam hitungan detik tanpa verifikasi manual.',
                    ],
                    [
                        'icon' => 'fa-mobile',
                        'title' => 'Aplikasi Mobile Kolektor & Kasir',
                        'desc' => 'Aplikasi Android untuk petugas penagih lapangan dengan fitur cetak struk thermal Bluetooth 58mm/80mm dan pelacakan rute pelanggan.',
                    ],
                    [
                        'icon' => 'fa-sitemap',
                        'title' => 'NMS OLT & Pemetaan GIS Fiber Optic',
                        'desc' => 'Pantau redaman optik port PON OLT, status ONU pelanggan, dan visualisasi jalur kabel kabel fiber optic ODP/ODC secara interaktif.',
                    ],
                ],
                'sections' => [
                    [
                        'title' => 'Mengapa Ribuan ISP Memilih NODERA?',
                        'content' => 'Mengelola ratusan hingga ribuan pelanggan internet secara manual menggunakan spreadsheet rentan menyebabkan kesalahan pencatatan, tunggakan tidak tertagih, dan proses isolir manual yang membuang waktu teknisi. NODERA hadir sebagai software billing ISP komprehensif yang dirancang khusus untuk memotong 90% waktu operasional administratif ISP Anda.',
                    ],
                    [
                        'title' => 'Arsitektur Terintegrasi MikroTik RouterOS v6 & v7',
                        'content' => 'NODERA berkomunikasi langsung dengan MikroTik melalui RouterOS API dan WebSockets berkecepatan tinggi. Mendukung provisioning otomatis akun PPPoE Secret, alokasi IP statis, pengelompokan Simple Queue, dan perpindahan otomatis ke Address-List isolir saat pelanggan menunggak.',
                    ],
                ],
                'faqs' => [
                    [
                        'q' => 'Apakah NODERA mendukung router MikroTik RouterOS v7?',
                        'a' => 'Ya, NODERA mendukung penuh RouterOS versi 6.x dan versi 7.x (termasuk REST API dan API port 8728) dengan deteksi versi otomatis.',
                    ],
                    [
                        'q' => 'Apakah ada batasan jumlah pelanggan atau router?',
                        'a' => 'Tergantung paket yang Anda pilih. Paket Standalone VPS dan Lisensi Lifetime NODERA tidak memiliki batasan (unlimited) router dan pelanggan.',
                    ],
                    [
                        'q' => 'Bagaimana jika koneksi internet antara server billing dan MikroTik terputus?',
                        'a' => 'Sistem NODERA memiliki mekanisme auto-reconnect dan antrean sinkronisasi (retry queue), sehingga data tetap aman dan tersinkronisasi saat koneksi pulih.',
                    ],
                ],
            ],

            'billing-rt-rw-net' => [
                'slug' => 'billing-rt-rw-net',
                'title' => 'Billing RT/RW Net | Aplikasi Billing ISP NODERA',
                'h1' => 'Aplikasi Billing RT/RW Net & Manajemen Pelanggan MikroTik',
                'meta_description' => 'Aplikasi billing RT/RW Net terbaik di Indonesia. Kelola pelanggan WiFi, isolir otomatis MikroTik, cetak thermal kasir, aplikasi kolektor Android, dan kirim tagihan WhatsApp.',
                'primary_keyword' => 'billing RT RW Net',
                'secondary_keywords' => ['aplikasi billing RT RW Net', 'software RT RW Net', 'billing wifi rumah tangga', 'aplikasi tagihan wifi'],
                'tagline' => 'Solusi Praktis & Hemat Biaya Pengusaha Internet RT/RW Net',
                'hero_subtitle' => 'Kelola bisnis internet RT/RW Net Anda seperti ISP profesional. Otomasi penagihan bulanan, isolir otomatis pelanggan jatuh tempo, cetak struk kasir Bluetooth, dan kelola voucher hotspot dalam satu aplikasi mudah digunakan.',
                'breadcrumbs' => [
                    ['title' => 'Beranda', 'url' => '/'],
                    ['title' => 'Solusi', 'url' => '/#demos'],
                    ['title' => 'Billing RT/RW Net', 'url' => '/billing-rt-rw-net'],
                ],
                'features' => [
                    [
                        'icon' => 'fa-lock',
                        'title' => 'Auto Isolir MikroTik Tepat Waktu',
                        'desc' => 'Pelanggan yang belum membayar setelah tanggal jatuh tempo otomatis dialihkan ke halaman isolir. Begitu bayar, internet langsung aktif kembali.',
                    ],
                    [
                        'icon' => 'fa-print',
                        'title' => 'Cetak Struk Kasir & Kolektor Lapangan',
                        'desc' => 'Dukung printer thermal Bluetooth ukuran 58mm & 80mm. Cetak bukti bayar langsung di depan pintu rumah pelanggan dengan logo usaha Anda.',
                    ],
                    [
                        'icon' => 'fa-commenting-o',
                        'title' => 'Kirim Tagihan & Kwitansi WhatsApp',
                        'desc' => 'Notifikasi pengingat jatuh tempo dan kwitansi digital terkirim otomatis ke nomor WhatsApp pelanggan tanpa perlu kirim pesan manual.',
                    ],
                    [
                        'icon' => 'fa-wifi',
                        'title' => 'Dukungan Hotspot Voucher & PPPoE',
                        'desc' => 'Gabungkan layanan internet bulanan (PPPoE/Static) dan penjualan voucher hotspot harian/jam-jaman dalam satu kontrol panel.',
                    ],
                    [
                        'icon' => 'fa-map-marker',
                        'title' => 'Peta Titik Pelanggan & Tiang ODP',
                        'desc' => 'Catat lokasi rumah pelanggan dan sambungan kabel tiang ODP pada peta interaktif agar teknisi mudah melakukan perbaikan jaringan.',
                    ],
                    [
                        'icon' => 'fa-money',
                        'title' => 'Laporan Kas & Rekap Keuangan',
                        'desc' => 'Laporan pemasukan harian, bulanan, komisi kolektor, dan pengeluaran operasional tersaji rapi dan bisa diekspor ke PDF/Excel.',
                    ],
                ],
                'sections' => [
                    [
                        'title' => 'Tingkatkan Pendapatan & Hilangkan Tunggakan',
                        'content' => 'Tantangan terbesar pengelola RT/RW Net adalah pelanggan yang telat bayar atau pura-pura lupa. Dengan sistem isolir otomatis dan WhatsApp reminder dari NODERA, tingkat ketepatan bayar pelanggan meningkat hingga di atas 98%.',
                    ],
                    [
                        'title' => 'Mudah Dioperasikan Tanpa Keahlian Koding',
                        'content' => 'Tampilan NODERA didesain intuitif dan berbahasa Indonesia. Cukup masukkan IP router MikroTik dan user API Anda, sistem akan langsung mengenali data paket dan pelanggan yang ada.',
                    ],
                ],
                'faqs' => [
                    [
                        'q' => 'Apakah cocok untuk usaha RT/RW Net pemula dengan 20-50 pelanggan?',
                        'a' => 'Sangat cocok! Paket SaaS Cloud NODERA dirancang sangat ramah anggaran untuk pemula dan dapat di-upgrade seiring bertambahnya pelanggan Anda.',
                    ],
                    [
                        'q' => 'Bisa kah cetak struk dari HP Android kolektor?',
                        'a' => 'Bisa. Aplikasi NODERA mendukung cetak via Android ke printer thermal Bluetooth portabel apa saja.',
                    ],
                ],
            ],

            'billing-mikrotik' => [
                'slug' => 'billing-mikrotik',
                'title' => 'Billing MikroTik | Software Billing ISP NODERA',
                'h1' => 'Software Billing MikroTik & Otomasi PPPoE / Static IP',
                'meta_description' => 'Sistem billing MikroTik terintegrasi RouterOS API. Sinkronisasi otomatis PPPoE secret, isolir otomatis pelanggan jatuh tempo, dan manajemen bandwidth pelanggan.',
                'primary_keyword' => 'billing MikroTik',
                'secondary_keywords' => ['aplikasi billing MikroTik', 'billing PPPoE MikroTik', 'integrasi MikroTik RouterOS', 'isolir otomatis MikroTik'],
                'tagline' => 'Integrasi RouterOS API Tercepat & Paling Andal',
                'hero_subtitle' => 'Hubungkan router MikroTik Anda dengan sistem billing NODERA. Manajemen PPPoE Secrets, IP Binding ARP, Simple Queue, pembagian bandwidth bertingkat, dan isolir otomatis dalam hitungan milidetik.',
                'breadcrumbs' => [
                    ['title' => 'Beranda', 'url' => '/'],
                    ['title' => 'Solusi', 'url' => '/#demos'],
                    ['title' => 'Billing MikroTik', 'url' => '/billing-mikrotik'],
                ],
                'features' => [
                    [
                        'icon' => 'fa-users',
                        'title' => 'Sinkronisasi PPPoE Secrets & Profiles',
                        'desc' => 'Tarik akun PPPoE existing dari MikroTik atau buat akun baru langsung dari panel billing dengan sinkronisasi instan dua arah.',
                    ],
                    [
                        'icon' => 'fa-shield',
                        'title' => 'Mekanisme Isolir Firewall & Address-List',
                        'desc' => 'Metode isolir fleksibel menggunakan Address-List firewall NAT redirect atau pergantian profil isolir otomatis dengan landing page notifikasi.',
                    ],
                    [
                        'icon' => 'fa-tachometer',
                        'title' => 'Manajemen Bandwidth & Simple Queue',
                        'desc' => 'Pengaturan batas kecepatan download/upload per paket, burst rate, prioritas antrean, dan bypass traffic speedtest Ookla.',
                    ],
                    [
                        'icon' => 'fa-plug',
                        'title' => 'Kompatibilitas RouterOS v6 & v7',
                        'desc' => 'Dukungan penuh untuk seluruh seri router MikroTik (hEX, RB3011, RB4011, CCR1009, CCR2004, CHR VPS) dengan protokol API & REST API.',
                    ],
                ],
                'sections' => [
                    [
                        'title' => 'Kontrol MikroTik Tanpa Login Winbox',
                        'content' => 'Admin dan staf billing tidak perlu membuka Winbox untuk menambah pelanggan, mengubah paket kecepatan, atau memutus sambungan. Semua aksi dapat dieksekusi secara aman dari antarmuka web dan mobile NODERA.',
                    ],
                ],
                'faqs' => [
                    [
                        'q' => 'Apakah aman menghubungkan router MikroTik ke NODERA?',
                        'a' => 'Sangat aman. Komunikasi menggunakan API RouterOS terenkripsi atau VPN Tunnel private, tanpa mengekspos port router ke publik secara terbuka.',
                    ],
                ],
            ],

            'monitoring-mikrotik' => [
                'slug' => 'monitoring-mikrotik',
                'title' => 'Monitoring MikroTik | NODERA',
                'h1' => 'Monitoring MikroTik Real-Time & Live Traffic Graph',
                'meta_description' => 'Pantau performa router MikroTik, CPU load, memory, status interface, traffic bandwidth 120 FPS, dan log jaringan secara real-time dari web dan HP.',
                'primary_keyword' => 'monitoring MikroTik',
                'secondary_keywords' => ['monitoring MikroTik dari HP', 'remote monitoring MikroTik', 'grafik traffic MikroTik', 'pantau router MikroTik'],
                'tagline' => 'Visualisasi Jaringan Berkecepatan Tinggi 120 FPS',
                'hero_subtitle' => 'Pantau kesehatan dan lalu lintas seluruh router MikroTik Anda secara real-time. Deteksi lonjakan beban CPU, link flapping, interface down, dan gangguan jaringan sebelum pelanggan mengeluh.',
                'breadcrumbs' => [
                    ['title' => 'Beranda', 'url' => '/'],
                    ['title' => 'Solusi', 'url' => '/#demos'],
                    ['title' => 'Monitoring MikroTik', 'url' => '/monitoring-mikrotik'],
                ],
                'features' => [
                    [
                        'icon' => 'fa-area-chart',
                        'title' => 'Live Traffic Graph 120 FPS',
                        'desc' => 'Grafik bandwidth interface realtime ultra halus dengan latensi rendah bertenaga WebSockets.',
                    ],
                    [
                        'icon' => 'fa-microchip',
                        'title' => 'Health Monitoring (CPU, RAM, Temp)',
                        'desc' => 'Pantau penggunaan prosesor, sisa memori, voltase catu daya, dan temperatur router secara komprehensif.',
                    ],
                    [
                        'icon' => 'fa-bell',
                        'title' => 'Notifikasi Gangguan Telegram & WhatsApp',
                        'desc' => 'Dapatkan peringatan instan ke grup Telegram atau nomor WhatsApp teknisi saat terjadi link down atau CPU load di atas 90%.',
                    ],
                    [
                        'icon' => 'fa-mobile',
                        'title' => 'Akses Monitoring dari HP',
                        'desc' => 'Aplikasi web responsif dan PWA yang ringan memudahkan pengecekan status router saat Anda berada di luar kantor.',
                    ],
                ],
                'sections' => [
                    [
                        'title' => 'Deteksi Masalah Jaringan Lebih Awal',
                        'content' => 'Dengan metrik real-time dan log RouterOS yang diagregasikan, tim NOC dapat dengan cepat mengisolir bottleneck jaringan, serangan DDoS, atau kabel LAN yang mengalami link flapping.',
                    ],
                ],
                'faqs' => [
                    [
                        'q' => 'Apakah monitoring membuat CPU router MikroTik menjadi berat?',
                        'a' => 'Tidak. NODERA menggunakan query API yang dioptimalkan dan streaming event berbasis socket yang efisien, sehingga beban CPU router tetap sangat rendah (< 2%).',
                    ],
                ],
            ],

            'nms-olt' => [
                'slug' => 'nms-olt',
                'title' => 'NMS OLT | Monitoring OLT GPON/EPON | NODERA',
                'h1' => 'NMS OLT GPON & EPON — Monitoring Redaman & Status ONU',
                'meta_description' => 'Software NMS OLT untuk monitoring redaman optik (Rx/Tx power), status ONU/ONT online-offline, suhu OLT, dan auto provisioning perangkat fiber optic.',
                'primary_keyword' => 'NMS OLT',
                'secondary_keywords' => ['monitoring OLT', 'software monitoring OLT', 'NMS GPON', 'NMS EPON', 'monitoring redaman ONU'],
                'tagline' => 'Manajemen Perangkat Fiber Optic OLT & ONU Terpusat',
                'hero_subtitle' => 'Solusi NMS terintegrasi untuk berbagai merk OLT (HIOSO, VSOL, HSGQ, ZTE, Huawei). Pantau redaman optik dBm, status online/offline ONU pelanggan, temperatur PON, dan konfigurasi tanpa repot.',
                'breadcrumbs' => [
                    ['title' => 'Beranda', 'url' => '/'],
                    ['title' => 'Solusi', 'url' => '/#demos'],
                    ['title' => 'NMS OLT', 'url' => '/nms-olt'],
                ],
                'features' => [
                    [
                        'icon' => 'fa-signal',
                        'title' => 'Monitoring Redaman Optik (Rx/Tx dBm)',
                        'desc' => 'Lihat redaman sinyal optik tiap pelanggan secara real-time. Indikator warna otomatis menandai redaman buruk (di atas -25 dBm).',
                    ],
                    [
                        'icon' => 'fa-cubes',
                        'title' => 'Dukungan Multi-Brand OLT',
                        'desc' => 'Kompatibel dengan merk OLT populer di Indonesia: HIOSO, VSOL EPON/GPON, HSGQ, ZTE C300/C320, dan Huawei MA5608T.',
                    ],
                    [
                        'icon' => 'fa-bolt',
                        'title' => 'Deteksi Putus Kabel & Dying Gasp',
                        'desc' => 'Peringatan otomatis saat ONU mati listrik (Dying Gasp) atau sinyal hilang akibat kabel fiber putus (LOS - Loss of Signal).',
                    ],
                    [
                        'icon' => 'fa-refresh',
                        'title' => 'Remote Reboot & Auto Provisioning',
                        'desc' => 'Reboot modem ONT pelanggan langsung dari dashboard web dan daftarkan unconfigured ONU baru hanya dengan satu klik.',
                    ],
                ],
                'sections' => [
                    [
                        'title' => 'Pantau Kesehatan Jaringan FTTH Anda',
                        'content' => 'Masalah sinyal optik drop atau kabel bengkok sering menjadi keluhan utama pelanggan FTTH. NMS NODERA memberikan visibilitas penuh terhadap status redaman seluruh port PON dan ONU.',
                    ],
                ],
                'faqs' => [
                    [
                        'q' => 'Apakah perlu instalasi server NMS terpisah?',
                        'a' => 'Tidak perlu. Modul NMS OLT sudah menyatu di dalam panel NODERA dan dapat berkomunikasi via protokol SNMP dan Telnet/SSH.',
                    ],
                ],
            ],

            'gis-fiber-optic' => [
                'slug' => 'gis-fiber-optic',
                'title' => 'GIS Fiber Optic | Mapping Jaringan Fiber | NODERA',
                'h1' => 'Sistem GIS Fiber Optic & Peta Jalur Kabel ODP / ODC',
                'meta_description' => 'Pemetaan jalur kabel fiber optic, titik ODP, ODC, joint closure, dan splitter ratio interaktif berbasis Google Maps & OpenStreetMap untuk ISP dan RT/RW Net.',
                'primary_keyword' => 'GIS fiber optic',
                'secondary_keywords' => ['GIS jaringan fiber', 'aplikasi mapping fiber optic', 'peta ODP ODC', 'manajemen kabel fiber'],
                'tagline' => 'Visualisasi Geografis Infrastruktur Jaringan Kabel FO',
                'hero_subtitle' => 'Petakan seluruh tiang, kabel feeder, distribution, closure, ODC, dan ODP pada peta digital interaktif. Ketahui sisa kapasitas port ODP dan permudah tim teknisi dalam pemasangan baru.',
                'breadcrumbs' => [
                    ['title' => 'Beranda', 'url' => '/'],
                    ['title' => 'Solusi', 'url' => '/#demos'],
                    ['title' => 'GIS Fiber Optic', 'url' => '/gis-fiber-optic'],
                ],
                'features' => [
                    [
                        'icon' => 'fa-map',
                        'title' => 'Peta Interaktif ODC & ODP',
                        'desc' => 'Plotting lokasi tiang, enclosure ODC, dan kotak ODP dengan visualisasi status port (terisi vs kosong).',
                    ],
                    [
                        'icon' => 'fa-random',
                        'title' => 'Tracing Jalur Kabel & Tube Core',
                        'desc' => 'Gambar jalur kabel fiber optik, catat jumlah core, warna tube, rasio splitter, dan titik sambungan joint closure.',
                    ],
                    [
                        'icon' => 'fa-street-view',
                        'title' => 'Integrasi Lokasi Rumah Pelanggan',
                        'desc' => 'Hubungkan data pelanggan billing dengan port ODP terdekat untuk kalkulasi panjang drop core otomatis saat survei pasang baru.',
                    ],
                    [
                        'icon' => 'fa-crosshairs',
                        'title' => 'Deteksi Lokasi Putus Kabel (OTDR Assist)',
                        'desc' => 'Masukkan jarak meter hasil tes OTDR untuk mengetahui perkiraan titik fisik kabel putus di lapangan pada peta GIS.',
                    ],
                ],
                'sections' => [
                    [
                        'title' => 'Dokumentasi Aset Jaringan yang Rapi dan Akurat',
                        'content' => 'Jangan biarkan denah kabel hanya ada di ingatan teknisi lama. Dengan GIS NODERA, seluruh tim lapangan dan admin memiliki panduan peta yang sama, terhindar dari salah colok port ODP.',
                    ],
                ],
                'faqs' => [
                    [
                        'q' => 'Bisa kah data GIS diekspor atau diimpor dari file KML / KMZ Google Earth?',
                        'a' => 'Ya, sistem mendukung impor dan ekspor data koordinat KML/GeoJSON untuk kemudahan migrasi data jaringan lama Anda.',
                    ],
                ],
            ],

            'mikhmon-online' => [
                'slug' => 'mikhmon-online',
                'title' => 'Mikhmon Online | Hotspot MikroTik | NODERA',
                'h1' => 'Mikhmon Online Cloud & Hotspot Voucher Portal MikroTik',
                'meta_description' => 'Platform Mikhmon online cloud tanpa repot setup server. Generate voucher hotspot massal, cetak voucher QR code, dan payment gateway QRIS otomatis.',
                'primary_keyword' => 'Mikhmon online',
                'secondary_keywords' => ['Mikhmon hotspot', 'management hotspot MikroTik', 'aplikasi voucher wifi', 'cetak voucher thermal'],
                'tagline' => 'Manajemen Hotspot Voucher Cloud Tanpa Ribet',
                'hero_subtitle' => 'Akses platform manajemen hotspot voucher MikroTik berbasis cloud dari mana saja. Buat voucher massal, cetak template voucher menarik, dan sediakan pembayaran QRIS otomatis untuk pelanggan hotspot Anda.',
                'breadcrumbs' => [
                    ['title' => 'Beranda', 'url' => '/'],
                    ['title' => 'Solusi', 'url' => '/#demos'],
                    ['title' => 'Mikhmon Online', 'url' => '/mikhmon-online'],
                ],
                'features' => [
                    [
                        'icon' => 'fa-cloud',
                        'title' => 'Server Cloud Tanpa Setup Hosting',
                        'desc' => 'Tidak perlu sewa hosting terpisah atau instalasi PHP lokal. Cukup hubungkan MikroTik dan instance Mikhmon langsung siap pakai.',
                    ],
                    [
                        'icon' => 'fa-ticket',
                        'title' => 'Batch Generator & Cetak Voucher',
                        'desc' => 'Generate ratusan kode voucher acak dalam hitungan detik. Cetak ke format PDF atau printer thermal Bluetooth dengan template kustom.',
                    ],
                    [
                        'icon' => 'fa-qrcode',
                        'title' => 'Beli Voucher Otomatis via QRIS',
                        'desc' => 'Sediakan halaman portal mandiri agar pelanggan WiFi hotspot bisa membeli voucher sendiri via QRIS tanpa harus mencari konter penjual.',
                    ],
                    [
                        'icon' => 'fa-trash-o',
                        'title' => 'Auto-Clean Voucher Expired',
                        'desc' => 'Pembersihan otomatis user voucher yang habis masa aktifnya di MikroTik, menjaga database router tetap ringan dan responsif.',
                    ],
                ],
                'sections' => [
                    [
                        'title' => 'Maksimalkan Omzet Bisnis WiFi Hotspot Anda',
                        'content' => 'Kombinasi kemudahan cetak voucher fisik dan portal pembayaran QRIS mandiri 24 jam membuat perputaran pendapatan WiFi koin/voucher Anda meningkat drastis tanpa repot standby.',
                    ],
                ],
                'faqs' => [
                    [
                        'q' => 'Apakah template cetak voucher bisa disesuaikan dengan logo usaha saya?',
                        'a' => 'Bisa. Anda bebas memilih berbagai template cetak voucher siap pakai dan menyesuaikan nama WiFi, logo, kontak CS, serta tarif paket.',
                    ],
                ],
            ],

            'harga' => [
                'slug' => 'harga',
                'title' => 'Harga Software Billing ISP | NODERA',
                'h1' => 'Paket & Harga Software Billing ISP NODERA',
                'meta_description' => 'Pilihan paket harga software billing ISP dan RT/RW Net NODERA yang fleksibel, mulai dari SaaS cloud hingga lisensi mandiri self-hosted tanpa biaya tersembunyi.',
                'primary_keyword' => 'harga software billing ISP',
                'secondary_keywords' => ['biaya billing isp', 'paket billing rt rw net', 'harga software mikrotik', 'lisensi billing nodera'],
                'tagline' => 'Investasi Terbaik untuk Efisiensi & Pertumbuhan Bisnis ISP Anda',
                'hero_subtitle' => 'Pilih paket yang paling sesuai dengan skala usaha Anda: mulai dari langganan bulanan Cloud SaaS yang praktis hingga Lisensi Dedicated Self-Hosted dengan kontrol penuh.',
                'breadcrumbs' => [
                    ['title' => 'Beranda', 'url' => '/'],
                    ['title' => 'Harga', 'url' => '/harga'],
                ],
                'features' => [
                    [
                        'icon' => 'fa-check-circle',
                        'title' => 'Tanpa Biaya Setup Tersembunyi',
                        'desc' => 'Semua fitur inti langsung aktif tanpa biaya tambahan per aktivasi modul.',
                    ],
                    [
                        'icon' => 'fa-headphones',
                        'title' => 'Bantuan Setup & Support Teknis',
                        'desc' => 'Tim teknis kami siap mendampingi proses integrasi awal ke router MikroTik dan OLT Anda.',
                    ],
                    [
                        'icon' => 'fa-lock',
                        'title' => 'Keamanan Data Tingkat Tinggi',
                        'desc' => 'Enkripsi data, backup database otomatis, dan isolasi tenant yang terjamin aman.',
                    ],
                ],
                'sections' => [
                    [
                        'title' => 'Pilihan Model Lisensi Fleksibel',
                        'content' => 'Kami memahami setiap pengusaha jaringan memiliki preferensi berbeda. Tersedia paket Cloud SaaS siap pakai untuk kemudahan operasional, Dedicated Docker VPS untuk skalabilitas tinggi, hingga Full Source Code bagi Anda yang membutuhkan kustomisasi mendalam.',
                    ],
                ],
                'faqs' => [
                    [
                        'q' => 'Apakah bisa upgrade paket jika jumlah pelanggan bertambah?',
                        'a' => 'Bisa kapan saja. Seluruh data pelanggan, riwayat tagihan, dan konfigurasi router akan bermigrasi secara mulus tanpa downtime.',
                    ],
                    [
                        'q' => 'Metode pembayaran apa saja yang didukung?',
                        'a' => 'Kami menerima pembayaran melalui Transfer Bank, Virtual Account, QRIS, dan e-Wallet dengan aktivasi lisensi otomatis instan.',
                    ],
                ],
            ],
        ];
    }

    /**
     * Data Master 20 Artikel Blog & Knowledge Hub
     */
    public static function getBlogArticles(): array
    {
        return [
            'cara-membuat-billing-rt-rw-net-mikrotik' => [
                'slug' => 'cara-membuat-billing-rt-rw-net-mikrotik',
                'title' => 'Cara Membuat Billing RT RW Net dengan MikroTik',
                'h1' => 'Panduan Lengkap Cara Membuat Sistem Billing RT/RW Net dengan MikroTik',
                'meta_description' => 'Panduan langkah demi langkah cara membuat sistem billing RT/RW Net menggunakan router MikroTik, mulai dari topologi, PPPoE server, manajemen isolir, hingga otomasi tagihan.',
                'category' => 'Tutorial RT/RW Net',
                'date' => '2026-09-15',
                'reading_time' => '8 menit',
                'author' => 'Tim Teknis NODERA',
                'related_solution' => 'billing-rt-rw-net',
                'excerpt' => 'Membangun usaha RT/RW Net yang rapi dan terhindar dari tunggakan dimulai dari pemilihan sistem billing yang tepat. Simak panduan teknis konfigurasi MikroTik dan manajemen tagihannya.',
                'content' => '
                    <p class="lead">Mengembangkan jaringan RT/RW Net dari skala puluhan menjadi ratusan pelanggan membutuhkan sistem manajemen yang andal. Tanpa sistem billing yang terstruktur, pengelola sering mengalami kesulitan menagih pembayaran bulanan, mencatat keuangan kas, dan memutus sambungan pelanggan yang menunggak secara manual.</p>
                    
                    <h2>1. Perencanaan Topologi Jaringan & Distribusi</h2>
                    <p>Langkah awal adalah menentukan topologi distribusi internet. Untuk skala RT/RW Net modern, penggunaan <strong>kabel Fiber Optic (FTTH)</strong> dengan kombinasi <em>PPPoE (Point-to-Point Protocol over Ethernet)</em> sangat disarankan dibandingkan static IP atau kabel LAN tembaga biasa. PPPoE memberikan isolasi antar user yang aman, mencegah pencurian IP (IP conflict), dan mempermudah kontrol bandwidth per pelanggan.</p>
                    
                    <h2>2. Konfigurasi Pool IP & Profil PPP pada MikroTik</h2>
                    <p>Buka Winbox atau WebFig pada router MikroTik utama Anda, kemudian buat IP Pool untuk pelanggan aktif dan IP Pool terpisah untuk pelanggan isolir:</p>
                    <pre><code>/ip pool add name=pool-pelanggan-aktif ranges=10.10.10.2-10.10.10.254
/ip pool add name=pool-pelanggan-isolir ranges=10.99.99.2-10.99.99.254</code></pre>
                    <p>Selanjutnya buat <code>/ppp profile</code> untuk paket kecepatan (misalnya 10 Mbps, 20 Mbps) dan satu profil khusus isolir dengan batasan kecepatan minimal (128k/128k) serta DNS redirect.</p>

                    <h2>3. Mengaktifkan PPPoE Server Interface</h2>
                    <p>Jalankan service PPPoE Server pada interface distribusi (misalnya vlan-distribusi atau ether-lan):</p>
                    <pre><code>/interface pppoe-server server add service-name=INTERNET-RTRW interface=ether2-LAN default-profile=profile-10M disabled=no</code></pre>

                    <h2>4. Integrasi dengan Software Billing Otomatis</h2>
                    <p>Mengelola ratusan secret PPPoE secara manual di Winbox sangat memakan waktu. Dengan menghubungkan MikroTik Anda ke <a href="/billing-rt-rw-net">Software Billing RT/RW Net NODERA</a>, semua data pelanggan, tanggal jatuh tempo, dan aktivasi paket akan tersinkronisasi otomatis. Tagihan bulanan akan otomatis dikirimkan via WhatsApp, dan sistem akan langsung memindahkan user ke pool isolir jika belum membayar saat tanggal jatuh tempo tiba.</p>

                    <div class="callout-box">
                        <h4>Tips Efisiensi:</h4>
                        <p>Gunakan aplikasi mobile kolektor penagih keliling dengan printer thermal Bluetooth untuk mencetak struk pembayaran langsung di tempat pelanggan saat penagihan door-to-door.</p>
                    </div>
                ',
            ],

            'apa-itu-software-billing-isp' => [
                'slug' => 'apa-itu-software-billing-isp',
                'title' => 'Apa Itu Software Billing ISP?',
                'h1' => 'Apa Itu Software Billing ISP? Fungsi, Manfaat, dan Cara Kerjanya',
                'meta_description' => 'Pelajari apa itu software billing ISP, fungsi utamanya dalam otomatisasi penagihan, integrasi router MikroTik, isolir otomatis, dan manfaatnya bagi bisnis penyedia internet.',
                'category' => 'Wawasan ISP',
                'date' => '2026-09-12',
                'reading_time' => '6 menit',
                'author' => 'Tim Produk NODERA',
                'related_solution' => 'software-billing-isp',
                'excerpt' => 'Software billing ISP adalah tulang punggung operasional bisnis penyedia layanan internet. Ketahui bagaimana software ini menghemat ratusan jam kerja administratif tim Anda.',
                'content' => '
                    <p class="lead">Dalam industri penyedia jasa internet (Internet Service Provider / ISP) dan jaringan RT/RW Net, operasional bisnis tidak hanya berkutat pada pemasangan kabel dan konfigurasi bandwidth, tetapi juga pengelolaan ribuan data transaksi pelanggan setiap bulannya.</p>

                    <h2>Definisi Software Billing ISP</h2>
                    <p><strong>Software Billing ISP</strong> adalah platform aplikasi perangkat lunak terintegrasi yang dirancang khusus untuk mengotomasi siklus hidup pelanggan internet, mulai dari pendaftaran, manajemen paket, penerbitan faktur (invoice) tagihan berkala, verifikasi pembayaran, kontrol akses bandwidth di router, hingga isolir otomatis saat jatuh tempo.</p>

                    <h2>Fungsi-Fungsi Kunci Software Billing ISP</h2>
                    <ul>
                        <li><strong>Otomasi Penerbitan Invoice:</strong> Menghasilkan invoice resmi setiap awal periode langganan secara otomatis tanpa input manual satu per satu.</li>
                        <li><strong>Sinkronisasi Router Network (MikroTik / OLT):</strong> Terhubung langsung dengan router untuk mengontrol akun PPPoE, alokasi IP, dan manajemen Simple Queue.</li>
                        <li><strong>Notifikasi Tagihan Otomatis:</strong> Mengirimkan pengingat tagihan berkala melalui WhatsApp Gateway, SMS, atau Email.</li>
                        <li><strong>Payment Gateway & Verifikasi Real-Time:</strong> Mendukung pembayaran QRIS Dinamis dan Virtual Account bank yang langsung mengonfirmasi pembayaran 24 jam nonstop.</li>
                        <li><strong>Auto Isolir & Auto Unblock:</strong> Memutus sementara akses internet pelanggan yang menunggak dan langsung mengaktifkannya kembali begitu pembayaran diverifikasi.</li>
                    </ul>

                    <h2>Mengapa Pencatatan Manual di Excel Tidak Lagi Cukup?</h2>
                    <p>Banyak pengelola ISP memulai pencatatan dengan Microsoft Excel atau Google Spreadsheet. Namun saat pelanggan mencapai di atas 50 user, risiko human error seperti lupa menagih, salah mencatat kwitansi, atau keterlambatan isolir dapat menyebabkan kebocoran omzet hingga puluhan persen. Mengadopsi <a href="/software-billing-isp">software billing ISP terpadu seperti NODERA</a> adalah investasi wajib untuk menjaga stabilitas arus kas bisnis Anda.</p>
                ',
            ],

            'cara-membuat-pppoe-server-mikrotik' => [
                'slug' => 'cara-membuat-pppoe-server-mikrotik',
                'title' => 'Cara Membuat PPPoE Server MikroTik',
                'h1' => 'Panduan Setting PPPoE Server di MikroTik RouterOS v6 & v7',
                'meta_description' => 'Tutorial lengkap setting PPPoE Server di MikroTik: konfigurasi IP Pool, PPP Profile, MTU/MRU, default route, dan pengamanan secret untuk ISP & RT/RW Net.',
                'category' => 'Tutorial MikroTik',
                'date' => '2026-09-10',
                'reading_time' => '7 menit',
                'author' => 'Network Engineer NODERA',
                'related_solution' => 'billing-mikrotik',
                'excerpt' => 'PPPoE adalah standar industri terbaik untuk mendistribusikan koneksi internet ke pelanggan. Pelajari cara konfigurasi PPPoE Server di MikroTik secara aman dan optimal.',
                'content' => '
                    <p class="lead">Metode autentikasi PPPoE (Point-to-Point Protocol over Ethernet) menawarkan keamanan tinggi karena setiap pelanggan memiliki username dan password unik terenkripsi, mencegah penggunaan IP secara ilegal oleh pihak lain di jaringan lokal.</p>

                    <h2>Langkah 1: Membuat IP Pool untuk PPPoE</h2>
                    <pre><code>/ip pool add name=pool-pppoe ranges=192.168.100.2-192.168.100.254</code></pre>

                    <h2>Langkah 2: Menyiapkan PPP Profile</h2>
                    <pre><code>/ppp profile add name=profile-20M local-address=192.168.100.1 remote-address=pool-pppoe dns-server=8.8.8.8,1.1.1.1 rate-limit=20M/20M only-one=yes</code></pre>
                    <p>Opsi <code>only-one=yes</code> sangat penting untuk memastikan satu akun PPPoE tidak dapat digunakan login bersamaan oleh lebih dari satu router pelanggan.</p>

                    <h2>Langkah 3: Mengaktifkan PPPoE Server</h2>
                    <pre><code>/interface pppoe-server server add service-name=ISP-PPPOE interface=ether2-LAN default-profile=profile-20M authentication=pap,chap,mschap2 one-session-per-host=yes disabled=no</code></pre>

                    <h2>Langkah 4: Manajemen Akun Terpusat dengan NODERA</h2>
                    <p>Setelah PPPoE Server aktif, hubungkan router Anda ke <a href="/billing-mikrotik">Sistem Billing MikroTik NODERA</a> untuk mengelola pembuatan user secret, perubahan paket bandwidth, dan isolir otomatis tanpa harus membuka Winbox secara manual.</p>
                ',
            ],

            'cara-auto-isolir-pelanggan-mikrotik' => [
                'slug' => 'cara-auto-isolir-pelanggan-mikrotik',
                'title' => 'Cara Auto Isolir Pelanggan MikroTik',
                'h1' => 'Cara Setting Auto Isolir Pelanggan Jatuh Tempo di MikroTik',
                'meta_description' => 'Cara kerja dan konfigurasi auto isolir pelanggan MikroTik yang telat bayar tagihan. Gunakan metode address-list, firewall NAT redirect, dan auto buka isolir realtime.',
                'category' => 'Tutorial MikroTik',
                'date' => '2026-09-08',
                'reading_time' => '6 menit',
                'author' => 'Tim Teknis NODERA',
                'related_solution' => 'billing-mikrotik',
                'excerpt' => 'Otomasi isolir pelanggan yang menunggak adalah kunci menjaga kedisiplinan pembayaran. Pahami metode firewall redirect dan integrasi billing otomatisnya.',
                'content' => '
                    <p class="lead">Salah satu beban terbesar teknisi ISP adalah memutus dan menyambungkan kembali koneksi pelanggan yang menunggak tagihan. Melalui sistem auto isolir terprogram, seluruh proses ini berjalan 100% otomatis tanpa campur tangan manusia.</p>

                    <h2>Mekanisme Kerja Auto Isolir</h2>
                    <p>Terdapat dua metode populer untuk melakukan isolir pada MikroTik:</p>
                    <ol>
                        <li><strong>Metode Address-List Firewall NAT:</strong> IP pelanggan yang menunggak dimasukkan ke dalam address-list <code>ISOLIR</code>. Firewall NAT kemudian me-redirect semua request browsing HTTP port 80 ke web server landing page pemberitahuan isolir.</li>
                        <li><strong>Metode Ganti PPP Profile:</strong> Billing server mengubah profil PPPoE secret pelanggan menjadi profil isolir yang mengarahkan IP ke subnet khusus tanpa akses internet luar.</li>
                    </ol>

                    <h2>Script Firewall NAT Redirect Isolir di MikroTik</h2>
                    <pre><code>/ip firewall nat add chain=dstnat protocol=tcp dst-port=80 src-address-list=ISOLIR action=redirect to-ports=8080 comment="Redirect Isolir Web"
/ip firewall filter add chain=forward src-address-list=ISOLIR protocol=tcp dst-port=443 action=drop comment="Drop HTTPS Isolir"</code></pre>

                    <h2>Otomasi Penuh dengan NODERA</h2>
                    <p>Dengan menggunakan <a href="/software-billing-isp">NODERA Software Billing</a>, saat jadwal jatuh tempo tiba (misal tanggal 20 pukul 00:00), server billing otomatis memasukkan pelanggan ke daftar isolir. Begitu pelanggan melakukan pembayaran melalui QRIS atau Virtual Account, sistem dalam hitungan milidetik menghapus user dari daftar isolir dan internet langsung menyala kembali.</p>
                ',
            ],

            'cara-monitoring-mikrotik-dari-hp' => [
                'slug' => 'cara-monitoring-mikrotik-dari-hp',
                'title' => 'Cara Monitoring MikroTik dari HP',
                'h1' => 'Cara Praktis Monitoring Router MikroTik Real-Time dari HP Android & iPhone',
                'meta_description' => 'Panduan cara memonitor router MikroTik dari smartphone: pantau trafik bandwidth 120 FPS, CPU load, notifikasi link down via Telegram, dan akses remote aman.',
                'category' => 'Monitoring Jaringan',
                'date' => '2026-09-05',
                'reading_time' => '5 menit',
                'author' => 'Network Engineer NODERA',
                'related_solution' => 'monitoring-mikrotik',
                'excerpt' => 'Pantau kesehatan router MikroTik Anda kapan saja dan di mana saja langsung dari smartphone dengan grafik real-time dan notifikasi bot instan.',
                'content' => '
                    <p class="lead">Teknisi jaringan dan pemilik ISP sering kali harus mobile di lapangan. Memiliki akses monitoring router MikroTik langsung dari genggaman ponsel sangat penting untuk merespons gangguan sebelum dikeluhkan pelanggan.</p>

                    <h2>Metode Monitoring MikroTik dari Smartphone</h2>
                    <ul>
                        <li><strong>1. Bot Notifikasi Telegram / WhatsApp:</strong> Router mengirimkan pesan otomatis saat terjadi event penting seperti interface down, CPU spike, atau link failover.</li>
                        <li><strong>2. Dashboard Web Responsif 120 FPS:</strong> Membuka panel web monitoring yang menyajikan grafik bandwidth interface real-time, status uptime, dan daftar active connections secara dinamis.</li>
                        <li><strong>3. VPN Tunnel Terisolasi:</strong> Mengakses API router melalui koneksi VPN WireGuard atau OpenVPN tanpa perlu membuka port Winbox ke IP publik.</li>
                    </ul>

                    <p>Pelajari lebih lanjut tentang fitur <a href="/monitoring-mikrotik">Monitoring MikroTik Real-Time NODERA</a> yang sudah terintegrasi langsung dengan notifikasi bot Telegram dan grafik performa WebSockets.</p>
                ',
            ],

            'apa-itu-nms-olt' => [
                'slug' => 'apa-itu-nms-olt',
                'title' => 'Apa Itu NMS OLT?',
                'h1' => 'Apa Itu NMS OLT? Fungsi dan Pentingnya untuk Jaringan Fiber Optic GPON/EPON',
                'meta_description' => 'Mengenal NMS OLT (Network Management System Optical Line Terminal), fungsinya memonitor redaman sinyal optik, status modem ONU/ONT, dan manajemen port PON terpusat.',
                'category' => 'Fiber Optic & FTTH',
                'date' => '2026-09-02',
                'reading_time' => '6 menit',
                'author' => 'Fiber Optic Specialist NODERA',
                'related_solution' => 'nms-olt',
                'excerpt' => 'NMS OLT adalah perangkat lunak wajib bagi pengelola jaringan fiber optic FTTH untuk memonitor kesehatan sinyal optik dan perangkat ONT pelanggan.',
                'content' => '
                    <p class="lead">Dalam infrastruktur jaringan FTTH (Fiber to the Home), OLT (Optical Line Terminal) berfungsi sebagai titik pusat distribusi sinyal optik ke ratusan bahkan ribuan modem ONT pelanggan. Mengelola OLT tanpa sistem pemantau terpusat sangat berisiko.</p>

                    <h2>Fungsi Utama NMS OLT</h2>
                    <p>NMS (Network Management System) OLT bertindak sebagai dashboard pengawas yang mengumpulkan data telemetri dari OLT melalui protokol SNMP dan CLI:</p>
                    <ul>
                        <li><strong>Monitoring Redaman Rx/Tx Optik:</strong> Mengetahui besaran daya terima sinyal (dalam satuan dBm) pada setiap ONU secara akurat.</li>
                        <li><strong>Deteksi Status Link ONU:</strong> Mengidentifikasi instan apakah modem pelanggan offline karena mati listrik (Dying Gasp) atau kabel optik putus (LOS).</li>
                        <li><strong>Pencatatan Suhu & Beban Port PON:</strong> Memastikan modul SFP PON tidak mengalami overheating yang dapat merusak kualitas transmisi data.</li>
                    </ul>

                    <p>Dengan modul <a href="/nms-olt">NMS OLT NODERA</a>, Anda dapat memonitor berbagai merk OLT seperti HIOSO, VSOL, HSGQ, dan ZTE langsung dari satu platform billing tanpa perlu software terpisah.</p>
                ',
            ],

            'cara-monitoring-onu-pada-olt' => [
                'slug' => 'cara-monitoring-onu-pada-olt',
                'title' => 'Cara Monitoring ONU pada OLT',
                'h1' => 'Cara Monitoring Redaman & Status ONU pada OLT (HIOSO, VSOL, HSGQ, ZTE)',
                'meta_description' => 'Panduan praktis mengecek redaman optik dBm, status online/offline, dan suhu ONU pada OLT HIOSO, VSOL, HSGQ, dan ZTE melalui NMS dan CLI.',
                'category' => 'Fiber Optic & FTTH',
                'date' => '2026-08-30',
                'reading_time' => '7 menit',
                'author' => 'Fiber Optic Specialist NODERA',
                'related_solution' => 'nms-olt',
                'excerpt' => 'Ketahui standar nilai redaman optik yang ideal dan cara memonitor kondisi perangkat ONU/ONT pelanggan dari dashboard NMS terpadu.',
                'content' => '
                    <p class="lead">Kualitas koneksi internet fiber optic sangat bergantung pada stabilitas redaman optik. Redaman yang terlalu tinggi (melebihi -27 dBm) menyebabkan packet loss, kecepatan drop, hingga koneksi putus-nyambung.</p>

                    <h2>Standar Nilai Redaman Optik (Optical Power Budget)</h2>
                    <ul>
                        <li><strong>Sangat Baik:</strong> -15 dBm hingga -20 dBm</li>
                        <li><strong>Baik (Normal):</strong> -21 dBm hingga -24 dBm</li>
                        <li><strong>Batas Toleransi:</strong> -25 dBm hingga -27 dBm</li>
                        <li><strong>Kritis (Segera Perbaiki):</strong> di atas -28 dBm (potensi LOS)</li>
                    </ul>

                    <h2>Langkah Pengecekan via NMS NODERA</h2>
                    <p>Pada platform <a href="/nms-olt">NMS OLT NODERA</a>, Anda cukup membuka tab <em>Daftar ONU</em>. Sistem akan menampilkan tabel seluruh modem pelanggan lengkap dengan status warna: hijau (normal), kuning (peringatan redaman tinggi), dan merah (offline/LOS). Anda juga dapat melakukan restart modem pelanggan dari jarak jauh (remote reboot) saat troubleshooting.</p>
                ',
            ],

            'cara-membuat-hotspot-voucher-mikrotik' => [
                'slug' => 'cara-membuat-hotspot-voucher-mikrotik',
                'title' => 'Cara Membuat Hotspot Voucher MikroTik',
                'h1' => 'Cara Membuat Hotspot Voucher MikroTik & Cetak Struk Thermal',
                'meta_description' => 'Panduan membuat hotspot server MikroTik, user profile batas waktu/kuota, generate kode voucher massal, dan cara cetak ke printer thermal Bluetooth.',
                'category' => 'Hotspot & Voucher',
                'date' => '2026-08-28',
                'reading_time' => '7 menit',
                'author' => 'Tim Teknis NODERA',
                'related_solution' => 'mikhmon-online',
                'excerpt' => 'Bisnis WiFi koin dan voucher hotspot tetap menjadi primadona di area publik. Simak cara setup hotspot MikroTik dan mencetak voucher dengan praktis.',
                'content' => '
                    <p class="lead">Hotspot MikroTik dengan sistem voucher adalah metode paling fleksibel untuk menjual akses internet di warung kopi, kontrakan, asrama, dan area publik tanpa perlu berlangganan bulanan.</p>

                    <h2>1. Setup Hotspot Server Setup</h2>
                    <p>Buka Winbox > IP > Hotspot > Hotspot Setup. Pilih interface wlan atau ether hotspot, tentukan IP address (misal 192.168.20.1/24), DNS name (misal <code>wifi.akses.net</code>), dan buat admin login.</p>

                    <h2>2. Membuat User Profile Paket Voucher</h2>
                    <p>Tentukan batasan kecepatan (rate limit) dan batas waktu (shared users, session timeout):</p>
                    <pre><code>/ip hotspot user profile add name="1-JAM" rate-limit="3M/3M" session-timeout=1h status-autorefresh=1m</code></pre>

                    <h2>3. Cetak & Manajemen dengan Mikhmon Online NODERA</h2>
                    <p>Gunakan layanan <a href="/mikhmon-online">Mikhmon Online Cloud NODERA</a> untuk men-generate kode voucher massal secara acak dan mencetaknya langsung ke printer thermal Bluetooth atau format PDF dengan desain voucher yang profesional.</p>
                ',
            ],

            'mikhmon-vs-billing-isp' => [
                'slug' => 'mikhmon-vs-billing-isp',
                'title' => 'Mikhmon vs Billing ISP',
                'h1' => 'Mikhmon vs Software Billing ISP: Mana yang Tepat untuk Usaha Anda?',
                'meta_description' => 'Perbandingan mendalam antara Mikhmon untuk manajemen voucher hotspot dan Software Billing ISP untuk pelanggan bulanan PPPoE. Ketahui kapan menggunakan keduanya.',
                'category' => 'Wawasan ISP',
                'date' => '2026-08-25',
                'reading_time' => '6 menit',
                'author' => 'Tim Produk NODERA',
                'related_solution' => 'software-billing-isp',
                'excerpt' => 'Sering bingung membedakan fungsi Mikhmon dan Software Billing ISP? Pahami perbedaan arsitektur, target pengguna, dan cara mengintegrasikan keduanya.',
                'content' => '
                    <p class="lead">Banyak pengusaha jaringan yang memulai dengan Mikhmon lalu bingung saat mulai melayani pelanggan rumahan dengan sistem langganan bulanan. Kedua platform ini memiliki tujuan yang berbeda namun saling melengkapi.</p>

                    <h2>Perbedaan Utama</h2>
                    <table class="table table-bordered" style="color: #cbd5e1; background: #0f172a;">
                        <thead>
                            <tr style="background: #1e293b; color: #38bdf8;">
                                <th>Fitur / Aspek</th>
                                <th>Mikhmon</th>
                                <th>Software Billing ISP (NODERA)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Tipe Layanan</strong></td>
                                <td>Voucher Hotspot (Jam/Hari)</td>
                                <td>Pelanggan Bulanan (PPPoE / Static)</td>
                            </tr>
                            <tr>
                                <td><strong>Sistem Penagihan</strong></td>
                                <td>Beli di awal (Prabayar)</td>
                                <td>Faktur bulanan, jatuh tempo, denda</td>
                            </tr>
                            <tr>
                                <td><strong>WhatsApp Gateway</strong></td>
                                <td>Terbatas / Custom</td>
                                <td>Otomatis invoice, reminder, kwitansi</td>
                            </tr>
                            <tr>
                                <td><strong>Kolektor & Kasir</strong></td>
                                <td>Tidak ada</td>
                                <td>Aplikasi Android + Printer Thermal</td>
                            </tr>
                            <tr>
                                <td><strong>NMS & GIS FO</strong></td>
                                <td>Tidak ada</td>
                                <td>Monitoring OLT & Peta Jalur Kabel ODP</td>
                            </tr>
                        </tbody>
                    </table>

                    <p>Kabar baiknya, di <a href="/software-billing-isp">Platform NODERA</a>, Anda mendapatkan keduanya: ekosistem Billing ISP lengkap untuk langganan bulanan sekaligus modul Mikhmon Cloud terintegrasi untuk voucher hotspot.</p>
                ',
            ],

            'cara-mengelola-pelanggan-isp' => [
                'slug' => 'cara-mengelola-pelanggan-isp',
                'title' => 'Cara Mengelola Pelanggan ISP',
                'h1' => 'SOP Cara Mengelola Pelanggan ISP agar Tertib, Rapi, dan Bebas Komplain',
                'meta_description' => 'Standard Operating Procedure (SOP) mengelola pelanggan ISP: mulai dari survei lokasi, registrasi data, pemasangan fisik, penagihan, hingga penanganan tiket gangguan.',
                'category' => 'Manajemen Bisnis ISP',
                'date' => '2026-08-22',
                'reading_time' => '7 menit',
                'author' => 'Konsultan Manajemen ISP',
                'related_solution' => 'software-billing-isp',
                'excerpt' => 'Kunci pertumbuhan ISP yang sustainable terletak pada SOP manajemen pelanggan yang disiplin. Simak panduan alur kerja dari registrasi hingga penanganan gangguan.',
                'content' => '
                    <p class="lead">Pertumbuhan jumlah pelanggan yang tidak diimbangi dengan sistem manajemen data yang tertib akan berujung pada kekacauan administrasi, komplain berulang, dan hilangnya kepercayaan pelanggan.</p>

                    <h2>Tahapan Siklus Hidup Pelanggan ISP:</h2>
                    <ol>
                        <li><strong>Pendaftaran & Verifikasi Lokasi:</strong> Catat data identitas, nomor WhatsApp, paket yang dipilih, dan titik koordinat rumah pada peta GIS.</li>
                        <li><strong>Instalasi & Mapping ODP:</strong> Teknisi memasang kabel drop core dan mencatat port ODP yang digunakan agar kapasitas tercatat akurat.</li>
                        <li><strong>Aktivasi PPPoE Otomatis:</strong> Akun router aktif otomatis sesuai paket kecepatan yang dipilih.</li>
                        <li><strong>Siklus Penagihan Teratur:</strong> Pengiriman invoice H-3 sebelum jatuh tempo via WhatsApp untuk memastikan pembayaran tepat waktu.</li>
                        <li><strong>Helpdesk & Ticketing:</strong> Layani keluhan gangguan melalui sistem tiket terpusat agar riwayat perbaikan tercatat rapi.</li>
                    </ol>

                    <p>Semua alur kerja di atas dapat dijalankan secara otomatis melalui <a href="/software-billing-isp">Software Billing ISP NODERA</a>.</p>
                ',
            ],

            'billing-mikrotik-untuk-rt-rw-net' => [
                'slug' => 'billing-mikrotik-untuk-rt-rw-net',
                'title' => 'Billing MikroTik untuk RT/RW Net',
                'h1' => 'Memilih Sistem Billing MikroTik yang Efisien untuk Usaha RT/RW Net',
                'meta_description' => 'Tips memilih software billing MikroTik untuk RT/RW Net: perbandingan resource server, kemudahan operasional, fitur isolir otomatis, dan harga yang terjangkau.',
                'category' => 'Tutorial RT/RW Net',
                'date' => '2026-08-18',
                'reading_time' => '6 menit',
                'author' => 'Tim Teknis NODERA',
                'related_solution' => 'billing-rt-rw-net',
                'excerpt' => 'Bagaimana memilih software billing MikroTik yang ringan, stabil, dan tidak membebani router? Pahami kriteria pentingnya di artikel ini.',
                'content' => '
                    <p class="lead">Bagi pemilik usaha RT/RW Net, keandalan sistem billing sangat krusial. Memilih software yang terlalu berat atau sering error justru menambah beban kerja harian.</p>

                    <h2>Kriteria Billing MikroTik Ideal:</h2>
                    <ul>
                        <li><strong>Koneksi API Ringan:</strong> Tidak membebani prosesor router MikroTik secara berlebihan.</li>
                        <li><strong>Notifikasi WhatsApp Tanpa Biaya Per-Pesan Mahal:</strong> Terhubung dengan gateway WhatsApp yang stabil.</li>
                        <li><strong>Bisa Diakses dari HP:</strong> Memudahkan admin mengecek status bayar pelanggan kapan pun dan di mana pun.</li>
                        <li><strong>Fitur Pembayaran QRIS Otomatis:</strong> Memberikan opsi bayar non-tunai yang praktis bagi pelanggan generasi sekarang.</li>
                    </ul>

                    <p>Ketahui bagaimana <a href="/billing-rt-rw-net">NODERA Billing RT/RW Net</a> memenuhi semua kriteria di atas dengan biaya berlangganan yang sangat ramah kantong.</p>
                ',
            ],

            'cara-mengelola-tagihan-pelanggan-isp' => [
                'slug' => 'cara-mengelola-tagihan-pelanggan-isp',
                'title' => 'Cara Mengelola Tagihan Pelanggan ISP',
                'h1' => 'Cara Efektif Mengelola Tagihan Pelanggan ISP & Menghindari Tunggakan',
                'meta_description' => 'Strategi mengelola tagihan bulanan pelanggan ISP: penentuan tanggal cut-off, reminder WhatsApp otomatis, denda keterlambatan, dan rekonsiliasi kasir.',
                'category' => 'Manajemen Bisnis ISP',
                'date' => '2026-08-15',
                'reading_time' => '6 menit',
                'author' => 'Konsultan Keuangan ISP',
                'related_solution' => 'software-billing-isp',
                'excerpt' => 'Arus kas yang sehat berakar dari disiplin penagihan. Simak strategi praktis menekan angka penunggak tagihan internet di ISP Anda.',
                'content' => '
                    <p class="lead">Tunggakan tagihan bulanan adalah musuh utama arus kas ISP. Jika dibiarkan, modal kerja untuk membayar sewa bandwidth upstream akan terganggu.</p>

                    <h2>Strategi Penagihan Efektif:</h2>
                    <ol>
                        <li><strong>Tentukan Tanggal Jatuh Tempo Serentak:</strong> Gunakan tanggal 20 sebagai batas akhir pembayaran serentak agar proses penagihan lebih fokus.</li>
                        <li><strong>Kirimkan Pengingat Bertahap:</strong> Kirim invoice pada tanggal 1, pengingat H-3 pada tanggal 17, dan peringatan isolir pada tanggal 19 via WhatsApp.</li>
                        <li><strong>Buka Isolir Otomatis:</strong> Pastikan sistem langsung membuka isolir segera setelah pelanggan membayar tanpa harus menunggu jam kerja admin.</li>
                    </ol>

                    <p>Otomasi seluruh alur penagihan ini dengan <a href="/software-billing-isp">Sistem Billing ISP NODERA</a> untuk menjaga arus kas bisnis Anda tetap surplus.</p>
                ',
            ],

            'apa-itu-olt-gpon-dan-epon' => [
                'slug' => 'apa-itu-olt-gpon-dan-epon',
                'title' => 'Apa Itu OLT GPON dan EPON?',
                'h1' => 'Perbedaan OLT GPON vs EPON: Mana yang Lebih Cocok untuk Bisnis ISP Anda?',
                'meta_description' => 'Perbandingan komprehensif OLT GPON vs EPON: kecepatan bandwidth, rasio splitter, jarak transmisi, harga perangkat, dan rekomendasi implementasi.',
                'category' => 'Fiber Optic & FTTH',
                'date' => '2026-08-12',
                'reading_time' => '8 menit',
                'author' => 'Fiber Optic Specialist NODERA',
                'related_solution' => 'nms-olt',
                'excerpt' => 'Memilih antara teknologi GPON atau EPON menentukan efisiensi biaya investasi jaringan FTTH Anda. Simak perbandingan mendalamnya di sini.',
                'content' => '
                    <p class="lead">Saat beralih ke kabel optik FTTH, keputusan pertama yang harus diambil adalah memilih antara teknologi GPON (Gigabit PON) atau EPON (Ethernet PON).</p>

                    <h2>Perbandingan Spesifikasi Teknis</h2>
                    <ul>
                        <li><strong>Kecepatan (Bandwidth):</strong> GPON menawarkan downstream 2.5 Gbps / upstream 1.25 Gbps. EPON menawarkan 1.25 Gbps simetris.</li>
                        <li><strong>Rasio Splitter per Port PON:</strong> GPON mampu membagi hingga 1:128 pelanggan per port PON, sedangkan EPON idealnya 1:64.</li>
                        <li><strong>Efisiensi Framing:</strong> GPON menggunakan protokol GEM yang lebih efisien (93%) dibanding EPON (sekitar 72%).</li>
                        <li><strong>Biaya Perangkat:</strong> OLT & ONU EPON umumnya lebih terjangkau, sedangkan GPON sedikit lebih mahal namun memiliki kapasitas masa depan lebih besar.</li>
                    </ul>

                    <p>Apa pun teknologi OLT yang Anda pilih, pastikan memonitor performanya melalui <a href="/nms-olt">NMS OLT NODERA</a> untuk memastikan kualitas sinyal optik tetap optimal.</p>
                ',
            ],

            'cara-monitoring-fiber-optic' => [
                'slug' => 'cara-monitoring-fiber-optic',
                'title' => 'Cara Monitoring Fiber Optic',
                'h1' => 'Panduan Monitoring Jaringan Fiber Optic & Troubleshooting Kabel Putus',
                'meta_description' => 'Teknik monitoring kabel fiber optic: cara menggunakan OTDR, Optical Power Meter (OPM), visual fault locator (VFL), dan integrasi pemetaan GIS ODP.',
                'category' => 'Fiber Optic & FTTH',
                'date' => '2026-08-08',
                'reading_time' => '7 menit',
                'author' => 'Fiber Optic Specialist NODERA',
                'related_solution' => 'gis-fiber-optic',
                'excerpt' => 'Kabel fiber optic rentan putus akibat terkena pohon atau kendaraan. Pelajari cara mendeteksi titik putus secara cepat dan presisi menggunakan GIS.',
                'content' => '
                    <p class="lead">Kecepatan tim teknisi dalam menemukan titik putus kabel optik menentukan kepuasan pelanggan saat terjadi insiden force majeure di lapangan.</p>

                    <h2>Alat Wajib Troubleshooting Fiber Optic:</h2>
                    <ul>
                        <li><strong>OTDR (Optical Time-Domain Reflectometer):</strong> Mengukur jarak meter titik putus atau lekukan kabel dengan presisi.</li>
                        <li><strong>OPM (Optical Power Meter):</strong> Mengukur daya sinyal optik dBm di ujung konektor SC/UPC atau SC/APC.</li>
                        <li><strong>VFL (Laser Pen):</strong> Menembakkan laser merah untuk melihat kebocoran core optik pada patchcord atau pigtail di OTB/ODC.</li>
                    </ul>

                    <h2>Kombinasi OTDR dengan Peta GIS NODERA</h2>
                    <p>Saat OTDR menunjukkan kabel putus di jarak 1.250 meter dari NOC, buka <a href="/gis-fiber-optic">Peta GIS Fiber Optic NODERA</a>. Sistem akan langsung menunjukkan tiang dan jalan mana yang berada tepat di jarak 1.250 meter tersebut, memangkas waktu pencarian lapangan dari berjam-jam menjadi beberapa menit saja.</p>
                ',
            ],

            'sistem-billing-isp-untuk-pemula' => [
                'slug' => 'sistem-billing-isp-untuk-pemula',
                'title' => 'Sistem Billing ISP untuk Pemula',
                'h1' => 'Panduan Memulai Sistem Billing ISP & RT/RW Net untuk Pemula',
                'meta_description' => 'Langkah awal setup sistem billing ISP bagi pemula: pemilihan server, koneksi ke MikroTik, pembuatan paket langganan, dan manajemen database pelanggan.',
                'category' => 'Wawasan ISP',
                'date' => '2026-08-05',
                'reading_time' => '6 menit',
                'author' => 'Tim Produk NODERA',
                'related_solution' => 'software-billing-isp',
                'excerpt' => 'Baru memulai bisnis ISP atau RT/RW Net? Simak langkah-langkah mudah membangun sistem billing otomatis pertama Anda tanpa pusing.',
                'content' => '
                    <p class="lead">Memulai bisnis penyedia internet tidak harus rumit. Dengan fondasi billing yang tepat sejak awal, usaha Anda akan lebih siap berkembang pesat tanpa kendala administrasi.</p>

                    <h2>Langkah Praktis untuk Pemula:</h2>
                    <ol>
                        <li><strong>Pilih Layanan Cloud SaaS:</strong> Tidak perlu membeli server fisik yang mahal. Gunakan paket Cloud SaaS NODERA yang langsung aktif.</li>
                        <li><strong>Koneksikan Router MikroTik:</strong> Masukkan IP dan port API router Anda ke dalam dashboard billing.</li>
                        <li><strong>Buat Paket Internet Menarik:</strong> Tentukan paket kecepatan (contoh: 10 Mbps Rp 150rb, 20 Mbps Rp 200rb).</li>
                        <li><strong>Daftarkan Pelanggan Pertama:</strong> Masukkan data pelanggan dan tetapkan tanggal jatuh tempo.</li>
                    </ol>

                    <p>Mulai langkah awal bisnis Anda bersama <a href="/harga">Paket Pemula NODERA Billing</a> sekarang juga.</p>
                ',
            ],

            'cara-membuat-invoice-otomatis-untuk-isp' => [
                'slug' => 'cara-membuat-invoice-otomatis-untuk-isp',
                'title' => 'Cara Membuat Invoice Otomatis untuk ISP',
                'h1' => 'Cara Membuat Invoice Tagihan Otomatis & Cetak Thermal untuk ISP',
                'meta_description' => 'Metode membuat invoice tagihan internet otomatis, pengiriman invoice PDF via WhatsApp, dan format cetak struk kasir thermal 58mm/80mm.',
                'category' => 'Manajemen Bisnis ISP',
                'date' => '2026-08-01',
                'reading_time' => '5 menit',
                'author' => 'Tim Produk NODERA',
                'related_solution' => 'software-billing-isp',
                'excerpt' => 'Invoice tagihan yang profesional meningkatkan citra resmi ISP Anda di mata pelanggan. Pelajari cara membuatnya secara otomatis.',
                'content' => '
                    <p class="lead">Invoice resmi lengkap dengan nomor faktur, rincian periode langganan, dan QR Code pembayaran memberikan rasa percaya bagi pelanggan korporat maupun perumahan.</p>

                    <h2>Komponen Invoice ISP Standar:</h2>
                    <ul>
                        <li>Nomor Invoice & Tanggal Terbit</li>
                        <li>Identitas Pelanggan (ID Pelanggan, Nama, Alamat)</li>
                        <li>Nama Paket & Periode Pemakaian (contoh: September 2026)</li>
                        <li>Rincian Biaya, PPN (jika ada), dan Kode Unik</li>
                        <li>Status Pembayaran (Lunas / Belum Lunas)</li>
                        <li>Metode Pembayaran (Rekening Bank / QRIS Dinamis)</li>
                    </ul>

                    <p>Di <a href="/software-billing-isp">NODERA Billing</a>, seluruh invoice ter-generate otomatis dan dapat dicetak langsung ke format thermal Bluetooth maupun diunduh dalam bentuk PDF resmi.</p>
                ',
            ],

            'cara-integrasi-qris-dengan-billing-isp' => [
                'slug' => 'cara-integrasi-qris-dengan-billing-isp',
                'title' => 'Cara Integrasi QRIS dengan Billing ISP',
                'h1' => 'Cara Integrasi Pembayaran QRIS Realtime Otomatis pada Billing ISP',
                'meta_description' => 'Panduan integrasi QRIS Dinamis dan Virtual Account dengan billing ISP: verifikasi mutasi otomatis 24 jam, notifikasi lunas instan, dan auto open isolir.',
                'category' => 'Integrasi Pembayaran',
                'date' => '2026-07-28',
                'reading_time' => '6 menit',
                'author' => 'Tim Finansial NODERA',
                'related_solution' => 'software-billing-isp',
                'excerpt' => 'Berikan kemudahan bayar bagi pelanggan Anda menggunakan QRIS all-payment (GoPay, OVO, Dana, ShopeePay, Mobile Banking) dengan verifikasi otomatis detik itu juga.',
                'content' => '
                    <p class="lead">Metode pembayaran manual via transfer bank sering merepotkan admin karena harus mengecek mutasi rekening satu per satu dan mengirim konfirmasi manual. Integrasi QRIS adalah solusi terbaiknya.</p>

                    <h2>Keunggulan QRIS Dinamis pada Billing ISP:</h2>
                    <ul>
                        <li><strong>Nominal Tepat Otomatis:</strong> Pelanggan cukup scan QR Code tanpa perlu memasukkan nominal transfer secara manual.</li>
                        <li><strong>Verifikasi Detik Itu Juga:</strong> Webhook payment gateway langsung mengirim sinyal sukses ke server billing saat uang masuk.</li>
                        <li><strong>Auto Buka Isolir 0 Detik:</strong> Router MikroTik langsung mengaktifkan kembali internet pelanggan secara otomatis pada detik yang sama.</li>
                    </ul>

                    <p>NODERA mendukung integrasi langsung dengan berbagai gateway terkemuka (Midtrans, Xendit, Tripay, Tokopay, dan QRIS Statis/Dinamis). Pelajari lebih lanjut di halaman <a href="/software-billing-isp">Software Billing ISP NODERA</a>.</p>
                ',
            ],

            'auto-isolir-pppoe-cara-kerja-manfaat' => [
                'slug' => 'auto-isolir-pppoe-cara-kerja-manfaat',
                'title' => 'Auto Isolir PPPoE: Cara Kerja dan Manfaat',
                'h1' => 'Auto Isolir PPPoE MikroTik: Cara Kerja, Manfaat, dan Best Practice',
                'meta_description' => 'Membahas tuntas sistem auto isolir PPPoE di MikroTik: alur kerja di RouterOS, pencegahan komplain pelanggan, dan dampaknya terhadap arus kas ISP.',
                'category' => 'Tutorial MikroTik',
                'date' => '2026-07-25',
                'reading_time' => '6 menit',
                'author' => 'Network Engineer NODERA',
                'related_solution' => 'billing-mikrotik',
                'excerpt' => 'Mengapa sistem isolir otomatis berbasis PPPoE jauh lebih unggul dibanding isolir manual? Temukan jawaban teknis dan manajerialnya.',
                'content' => '
                    <p class="lead">Mengisolir pelanggan yang belum membayar tagihan bukan sekadar memberi hukuman, melainkan prosedur standar industri untuk menjaga komitmen kontrak layanan internet.</p>

                    <h2>Alur Kerja Teknis Auto Isolir PPPoE:</h2>
                    <ol>
                        <li>Server billing mendeteksi pelanggan <code>user_pppoe_101</code> telah melewati waktu jatuh tempo.</li>
                        <li>Server billing mengirim perintah via API MikroTik untuk memasukkan IP user ke Address-List <code>ISOLIR_LIST</code> atau mengubah profile PPPoE.</li>
                        <li>Koneksi aktif user ditendang sesaat (<code>/ppp active remove</code>) agar user login kembali mendapatkan profil isolir.</li>
                        <li>Saat pelanggan membuka browser, halaman dialihkan ke portal notifikasi tagihan dengan tombol bayar QRIS instan.</li>
                    </ol>

                    <p>Implementasikan auto isolir mulus ini bersama <a href="/billing-mikrotik">NODERA Billing MikroTik</a>.</p>
                ',
            ],

            'nms-vs-monitoring-mikrotik' => [
                'slug' => 'nms-vs-monitoring-mikrotik',
                'title' => 'NMS vs Monitoring MikroTik',
                'h1' => 'NMS OLT vs Monitoring MikroTik: Pahami Perbedaan dan Fungsinya di NOC',
                'meta_description' => 'Perbedaan mendasar antara NMS OLT (Layer 1/2 Fiber Optic) dan Monitoring MikroTik (Layer 3 Routing): peran keduanya dalam menjaga kestabilan jaringan ISP.',
                'category' => 'Wawasan ISP',
                'date' => '2026-07-20',
                'reading_time' => '5 menit',
                'author' => 'Network Engineer NODERA',
                'related_solution' => 'nms-olt',
                'excerpt' => 'Sering menganggap NMS dan Monitoring Router sama? Pahami peran masing-masing perangkat pada layer OSI untuk analisa gangguan yang tepat.',
                'content' => '
                    <p class="lead">Di ruang NOC (Network Operations Center) ISP, dua layar utama yang selalu diawasi adalah dashboard NMS perangkat optik dan dashboard monitoring router layer 3.</p>

                    <h2>Perbedaan Fokus Pemantauan:</h2>
                    <ul>
                        <li><strong>NMS OLT (Layer Fisik & Data Link):</strong> Fokus pada redaman optik (Rx/Tx dBm), status kabel fiber (LOS), temperatur modul SFP, dan konektivitas modem ONT pelanggan.</li>
                        <li><strong>Monitoring MikroTik (Layer Network & Transport):</strong> Fokus pada utilisasi bandwidth total, latency/ping, packet drop, CPU router, antrean Simple Queue, dan routing BGP/OSPF.</li>
                    </ul>

                    <p>Kedua fungsi ini disatukan dalam satu dashboard terpadu di <a href="/monitoring-mikrotik">NODERA Monitoring</a> dan <a href="/nms-olt">NMS OLT NODERA</a>.</p>
                ',
            ],

            'cara-mengelola-jaringan-ftth' => [
                'slug' => 'cara-mengelola-jaringan-ftth',
                'title' => 'Cara Mengelola Jaringan FTTH',
                'h1' => 'Panduan Rancang Bangun & Manajemen Jaringan FTTH untuk ISP & RT/RW Net',
                'meta_description' => 'Best practices mengelola infrastruktur Fiber to the Home (FTTH): penataan kabel feeder, ODC, ODP, drop core, perhitungan optical budget, dan dokumentasi GIS.',
                'category' => 'Fiber Optic & FTTH',
                'date' => '2026-07-15',
                'reading_time' => '8 menit',
                'author' => 'Fiber Optic Specialist NODERA',
                'related_solution' => 'gis-fiber-optic',
                'excerpt' => 'Infrastruktur FTTH yang rapi mempermudah ekspansi jaringan hingga ribuan homepass tanpa pusing mengurut kabel. Simak panduan teknisnya.',
                'content' => '
                    <p class="lead">Jaringan FTTH (Fiber To The Home) adalah standar emas infrastruktur telekomunikasi saat ini. Membangun jaringan FTTH yang terstandarisasi sejak awal menjamin masa pakai kabel lebih dari 15 tahun.</p>

                    <h2>Hierarki Struktur Jaringan FTTH:</h2>
                    <ol>
                        <li><strong>Central Office / NOC:</strong> Tempat OLT dan ODF (Optical Distribution Frame) berada.</li>
                        <li><strong>Feeder Cable:</strong> Kabel optik kapasitas besar (24 - 96 core) dari ODF menuju ODC di area perumahan.</li>
                        <li><strong>ODC (Optical Distribution Cabinet):</strong> Tempat splitter primer (misal 1:4 atau 1:8) membagi sinyal ke kabel distribusi.</li>
                        <li><strong>Distribution Cable:</strong> Menghubungkan ODC ke kotak-kotak ODP di tiang perumahan.</li>
                        <li><strong>ODP (Optical Distribution Point):</strong> Kotak splitter sekunder (1:8 atau 1:16) tempat colokan kabel drop core pelanggan.</li>
                        <li><strong>Drop Cable & Roset:</strong> Kabel 1 core fleksibel yang masuk ke dalam rumah pelanggan menuju modem ONT.</li>
                    </ol>

                    <p>Dokumentasikan seluruh aset fisik jaringan FTTH Anda secara akurat menggunakan <a href="/gis-fiber-optic">Aplikasi GIS Fiber Optic NODERA</a>.</p>
                ',
            ],
        ];
    }

    /**
     * Halaman Solusi Tertarget (P1 SEO)
     */
    public function solution(Request $request, ?string $slug = null)
    {
        if (!$slug) {
            $slug = ltrim($request->path(), '/');
        }

        $solutions = self::getSolutions();
        if (!isset($solutions[$slug])) {
            abort(404);
        }

        $item = $solutions[$slug];
        $company = [];
        try {
            $companySetting = Setting::withoutGlobalScopes()->whereNull('tenant_id')->where('key', 'company')->first();
            $company = $companySetting?->value ?? [];
        } catch (\Throwable $e) {}

        $allSolutions = $solutions;
        $blogArticles = array_slice(self::getBlogArticles(), 0, 4, true);

        return view('seo.solution', compact('item', 'company', 'allSolutions', 'blogArticles'));
    }

    /**
     * Halaman Content Hub / Blog Index (P2 SEO)
     */
    public function blogIndex(Request $request)
    {
        $articles = self::getBlogArticles();
        $category = $request->query('kategori');
        if ($category) {
            $articles = array_filter($articles, fn($a) => strtolower($a['category']) === strtolower($category));
        }

        $company = [];
        try {
            $companySetting = Setting::withoutGlobalScopes()->whereNull('tenant_id')->where('key', 'company')->first();
            $company = $companySetting?->value ?? [];
        } catch (\Throwable $e) {}
        $solutions = self::getSolutions();

        return view('seo.blog-index', compact('articles', 'company', 'solutions', 'category'));
    }

    /**
     * Halaman Detail Artikel Blog (P2 SEO)
     */
    public function blogPost(string $slug)
    {
        $articles = self::getBlogArticles();
        if (!isset($articles[$slug])) {
            abort(404);
        }

        $article = $articles[$slug];
        $company = [];
        try {
            $companySetting = Setting::withoutGlobalScopes()->whereNull('tenant_id')->where('key', 'company')->first();
            $company = $companySetting?->value ?? [];
        } catch (\Throwable $e) {}

        // Related articles
        $relatedArticles = array_filter($articles, fn($k) => $k !== $slug, ARRAY_FILTER_USE_KEY);
        $relatedArticles = array_slice($relatedArticles, 0, 3, true);

        $solutions = self::getSolutions();
        $relatedSolution = isset($article['related_solution']) && isset($solutions[$article['related_solution']])
            ? $solutions[$article['related_solution']]
            : null;

        return view('seo.blog-post', compact('article', 'company', 'relatedArticles', 'relatedSolution', 'solutions'));
    }
}
