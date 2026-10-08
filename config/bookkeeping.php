<?php

return [
    'domain' => env('BOOKKEEPING_DOMAIN', 'dgtlnetsolution.com'),
    'base_path' => env('BOOKKEEPING_BASE_PATH', public_path()),
    'monthly_price' => (float) env('BOOKKEEPING_MONTHLY_PRICE', 10000),
];
