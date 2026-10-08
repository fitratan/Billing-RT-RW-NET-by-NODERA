<?php

return [
    // Domain utama untuk subdomain Mikhmon (cth: mikhmon-xxx.dgtlnetsolution.com)
    'domain' => env('MIKHMON_DOMAIN', 'dgtlnetsolution.com'),

    // Folder tempat instalasi Mikhmon dibuat. WAJIB di dalam docroot (public/)
    // supaya bisa dilayani langsung oleh Apache:
    //   - subdomain mikhmon-xxx.dgtlnetsolution.com → rewrite .htaccess → /mikhmon-xxx/
    //   - URL https://domain.com/mikhmon-xxx/ juga ikut bisa diakses
    // Di server: MIKHMON_BASE_PATH=/home/dgtlnets/public_html/public
    'base_path' => env('MIKHMON_BASE_PATH', public_path()),

    // Harga langganan per bulan (default 10.000)
    'monthly_price' => (float) env('MIKHMON_MONTHLY_PRICE', 10000),
];
