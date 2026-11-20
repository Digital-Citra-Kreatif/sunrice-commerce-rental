<?php

declare(strict_types=1);

return [
    // What the places dresses are picked up at are called (config commerce-rental.location_term can change it).
    'term' => 'location',
    'errors' => [
        'package' => 'This rental package is no longer offered.',
        'package_days' => 'This package is for :days days.',
        'dates_missing' => 'Choose the rental dates and :location for every dress.',
        'unavailable' => 'Sorry, this dress is already booked on those dates. Please choose other dates or another :location.',
        'past' => 'The rental date has passed.',
        'too_long' => 'A rental can last at most :days days.',
        'too_far' => 'The rental date is too far ahead.',
        'too_soon' => 'Book at least :days days before the rental starts.',
        'too_short' => 'Rentals are at least :days days.',
    ],
    'deposit' => 'Deposit now',
    'move' => [
        'same_location' => 'The unit is already at that :location.',
        'booked' => '{1} The unit has :count customer booking at its :location. Move it once it is returned, or change the booking first.|[2,*] The unit has :count customer bookings at its :location. Move it once they are returned, or change the bookings first.',
        'done' => ':code moved to :name.',
    ],
    'location' => ['has_units' => 'The :location still has rental units. Move or delete them first.'],
    'unit' => ['has_bookings' => 'The unit has orders. Make it inactive or retired instead.'],
    'block' => [
        'ends_before' => 'The end date is before the start.',
        'clash' => 'The unit is taken :from – :to.',
    ],
    'line' => ':from – :to (:days days) · :Location: :name',
    'line_one_day' => ':from (1 day) · :Location: :name',
    'form' => [
        'package' => 'Rental',
        'regular' => 'Regular',
        'package_option' => ':name: :days days, :fee more',
        'pricing' => 'The price is for :days days; each day more is :fee.',
        'title' => 'Rent this dress',
        'size' => 'Size',
        'start' => 'Pickup day',
        'end' => 'Return day',
        'location' => ':Location',
        'choose' => 'Choose dates to see what is free.',
        'available' => 'Available at :name',
        'unavailable' => 'Not available at :name on these dates',
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
    'order' => [
        'locations' => '{1} Pick up and return at|[2,*] Pick up and return at',
        'whatsapp' => 'Chat with the :location on WhatsApp',
        'whatsapp_text' => 'Hi, I have a question about rental order :number.',
    ],
];
