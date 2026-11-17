<?php

declare(strict_types=1);

// Defaults for Settings → Store → Rentals (the admin's values win).
return [
    'buffer_days' => (int) env('COMMERCE_RENTAL_BUFFER_DAYS', 2),
    'min_days' => 4,
    'max_days' => 30,
    'min_days_ahead' => 0,
    'max_days_ahead' => 0, // 0: no limit
    'pricing' => 'package', // package: the price covers base_days, each day more costs extra_day_fee | daily: price × days
    'base_days' => 4,
    'extra_day_fee' => 50000,
    'packages' => [], // [['name' => 'Luar kota', 'days' => 7, 'fee' => 50000]]
    'deposit_mode' => 'none', // none | percent | fixed
    'deposit_percent' => 50,
    'deposit_fixed' => 0,
    'balance_days_before' => 3,
    'pickup_reminder_days' => 1,
    'return_reminder_days' => 1,
];
