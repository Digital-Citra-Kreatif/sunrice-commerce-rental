<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Sunrice\CommerceRental\Availability;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Tests\Concurrency\ConcurrencyTestCase;

uses(ConcurrencyTestCase::class);

it('makes a second checkout for the same dress wait, then see it booked', function () {
    $variant = rentalStore(jakartaUnits: 1);
    $period = period(5);
    $availability = app(Availability::class);

    // Customer A is placing the order: the dress's units are locked.
    DB::beginTransaction();
    $units = $availability->freeUnits($variant, $period, lock: true);
    expect($units)->toHaveCount(1);

    // Customer B, at the same moment, can't read the units to book them: it waits (here 1 s, then gives up).
    $other = DB::connection('mysql_other');
    $other->statement('SET SESSION innodb_lock_wait_timeout = 1');
    $other->beginTransaction();
    expect(fn () => $other->table('commerce_rental_units')->where('variant_id', $variant->id)->lockForUpdate()->get())
        ->toThrow(QueryException::class);
    $other->rollBack();

    // A books it and commits.
    Booking::query()->create(['unit_id' => $units->first()->id, 'kind' => Booking::ORDER, 'order_id' => 1,
        'starts_on' => $period->start->toDateString(), 'ends_on' => $period->end->toDateString(),
        'available_from' => $period->availableFrom(2)->toDateString(), 'buffer_days' => 2, 'status' => Booking::ACTIVE]);
    DB::commit();

    // B tries again: the lock is free, and the unit is taken.
    $other->beginTransaction();
    $locked = $other->table('commerce_rental_units')->where('variant_id', $variant->id)->lockForUpdate()->pluck('id')->all();
    $taken = $other->table('commerce_rental_bookings')->whereIn('unit_id', $locked)->where('status', 'active')
        ->where('starts_on', '<', $period->availableFrom(2)->toDateString())->where('available_from', '>', $period->start->toDateString())->exists();
    $other->rollBack();

    expect($locked)->toHaveCount(1)->and($taken)->toBeTrue();
});
