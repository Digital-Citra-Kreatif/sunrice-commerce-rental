<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Sunrice\Commerce\Orders\Actor;
use Sunrice\Commerce\Orders\Models\Order;
use Sunrice\Commerce\Orders\Timeline;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Models\Unit;
use Sunrice\CommerceRental\RentalException;

/**
 * The unit came back. Late, its rest days start from the day it came back
 * (so the next rental isn't handed a dress still being cleaned); the
 * returned result says which later bookings that now clashes with. In
 * repair, the unit stays out of bookings until staff mark it good again.
 */
class RecordReturn
{
    /** @return array<int, Booking> later bookings of the unit that the late return now overlaps */
    public function handle(Booking $booking, Actor $actor, string $condition = 'good', ?string $note = null): array
    {
        if ($booking->kind !== Booking::ORDER || $booking->status !== Booking::ACTIVE) {
            throw new RentalException(__('commerce-rental::rental.ops.not_active'));
        }
        if ($booking->returned_at !== null) {
            throw new RentalException(__('commerce-rental::rental.ops.already_returned'));
        }

        return DB::transaction(function () use ($booking, $actor, $condition, $note): array {
            $today = CarbonImmutable::today();
            $lateFrom = $today->addDays(1 + $booking->buffer_days);
            $attributes = ['returned_at' => now(), 'picked_up_at' => $booking->picked_up_at ?? now(), 'return_condition' => $condition, 'return_note' => $note ?: null];
            if ($lateFrom->gt(CarbonImmutable::instance($booking->available_from))) {
                $attributes['available_from'] = $lateFrom->toDateString();
            }
            $booking->forceFill($attributes)->save();
            if ($condition !== 'good') {
                Unit::query()->whereKey($booking->unit_id)->update(['condition' => $condition, 'updated_at' => now()]);
            }
            $clashes = Booking::query()->where('unit_id', $booking->unit_id)->whereKeyNot($booking->id)->where('status', Booking::ACTIVE)
                ->whereDate('starts_on', '<', $booking->available_from->toDateString())->whereDate('starts_on', '>=', $today->toDateString())
                ->orderBy('starts_on')->get()->all();
            $order = Order::query()->find($booking->order_id);
            if ($order !== null) {
                app(Timeline::class)->record($order, 'note', __('commerce-rental::rental.ops.timeline_returned', [
                    'unit' => $booking->unit->code ?? '', 'condition' => __('commerce-rental::rental.conditions.'.$condition),
                ]).($note ? " — {$note}" : ''), ['booking_id' => $booking->id], $actor);
            }

            return $clashes;
        });
    }
}
