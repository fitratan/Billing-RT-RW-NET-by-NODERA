<?php

return [
    // Harga langganan per bulan
    'standalone_monthly_price' => (float) env('NODERAPAY_STANDALONE_PRICE', 20000),
    'bundle_monthly_price'     => (float) env('NODERAPAY_BUNDLE_PRICE', 30000),

    // Grace period sebelum dinonaktifkan jika belum perpanjang (hari)
    'grace_period_days'        => (int) env('NODERAPAY_GRACE_DAYS', 3),

    // Masa aktif default (hari)
    'default_duration_days'    => (int) env('NODERAPAY_DURATION_DAYS', 30),
];
