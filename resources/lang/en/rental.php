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
    'line' => ':from – :to (:days days) · :branch branch',
    'line_one_day' => ':from (1 day) · :branch branch',
    'form' => [
        'title' => 'Rent this dress',
        'size' => 'Size',
        'start' => 'Pickup day',
        'end' => 'Return day',
        'branch' => 'Branch',
        'choose' => 'Choose dates to see what is free.',
        'available' => 'Available at :branch',
        'unavailable' => 'Not available at :branch on these dates',
        'price' => ':days days: :price',
        'add' => 'Add to cart',
        'buy_now' => 'Rent now',
        'rules' => 'Rentals of :min–:max days. Each dress is cleaned after it comes back before it goes out again.',
    ],
    'conditions' => ['good' => 'good', 'repair' => 'needs repair', 'retired' => 'retired'],
    'ops' => [
        'not_active' => 'This booking is no longer active.',
        'already_picked_up' => 'Already marked as picked up.',
        'already_returned' => 'Already marked as returned.',
        'unpaid' => 'The order is not paid (not even its deposit).',
        'returned' => 'Marked as returned.',
        'returned_clash' => 'Marked as returned. It came back late: its rest days now overlap :count later booking(s) (from :date). Give them another unit or other dates.',
        'dates_changed' => 'Dates changed. The price did not change: charge any difference with an order edit or a payment link.',
        'timeline_picked_up' => 'Unit :unit picked up by the customer',
        'timeline_returned' => 'Unit :unit returned, :condition',
        'timeline_dates' => 'Rental dates changed from :old to :new (unit :unit)',
    ],
];
