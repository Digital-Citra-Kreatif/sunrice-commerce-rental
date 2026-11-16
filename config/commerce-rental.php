<?php

declare(strict_types=1);

return [
    // Days a unit rests after its return day (cleaning, ironing, inspection) before it can go out
    // again. Overridden per category or per product in commerce_rental_buffers.
    'buffer_days' => (int) env('COMMERCE_RENTAL_BUFFER_DAYS', 2),

    // The longest rental, in days, and how far ahead a rental may start.
    'max_days' => 30,
    'max_days_ahead' => 365,
];
