<?php

declare(strict_types=1);

return [
    'errors' => [
        'dates_missing' => 'Choose the rental dates and branch for every dress.',
        'unavailable' => 'Sorry, this dress is already booked on those dates. Please choose other dates or another branch.',
        'past' => 'The rental date has passed.',
        'too_long' => 'A rental can last at most :days days.',
        'too_far' => 'The rental date is too far ahead.',
        'too_soon' => 'Book at least :days days before the rental starts.',
        'too_short' => 'Rentals are at least :days days.',
    ],
    'deposit' => 'Deposit now',
    'move' => [
        'same_branch' => 'The unit is already at that branch.',
        'booked' => '{1} The unit has :count customer booking at its branch. Move it once it is returned, or change the booking first.|[2,*] The unit has :count customer bookings at its branch. Move it once they are returned, or change the bookings first.',
        'done' => ':code moved to :branch.',
    ],
    'branch' => ['has_units' => 'The branch still has units. Move or delete them first.'],
    'unit' => ['has_bookings' => 'The unit has orders. Make it inactive or retired instead.'],
    'block' => [
        'ends_before' => 'The end date is before the start.',
        'clash' => 'The unit is taken :from – :to.',
    ],
];
