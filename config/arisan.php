<?php

return [
    'domain' => env('ARISAN_DOMAIN', 'dgtlnetsolution.com'),
    'monthly_price' => (float) env('ARISAN_MONTHLY_PRICE', 10000),
    'trial_days' => (int) env('ARISAN_TRIAL_DAYS', 0),
];
