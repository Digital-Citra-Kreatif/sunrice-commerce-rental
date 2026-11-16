<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Actions;

use Illuminate\Support\Facades\DB;
use Sunrice\Commerce\Orders\Actor;
use Sunrice\Commerce\Orders\Models\Order;
use Sunrice\Commerce\Orders\Timeline;
use Sunrice\CommerceRental\Availability;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Models\Unit;
use Sunrice\CommerceRental\RentalException;
use Sunrice\CommerceRental\RentalPeriod;

/**
 * Staff move a customer's booking to other days (a date change, an
 * extension agreed by WhatsApp). The same unit is kept when it's free;
 * otherwise another free unit of the same variant at the branch is taken.
 * The price isn't changed: an extension is charged by hand (an order edit
 * or a payment link), as the store decided.
 */
class ChangeDates
{
    public function __construct(protected Availability $availability) {}

    public function handle(Booking $booking, RentalPeriod $period, Actor $actor): Booking
    {
        if ($booking->kind !== Booking::ORDER || $booking->status !== Booking::ACTIVE || $booking->returned_at !== null) {
            throw new RentalException(__('commerce-rental::rental.ops.not_active'));
        }

        return DB::transaction(function () use ($booking, $period, $actor): Booking {
            /** @var Unit $unit */
            $unit = Unit::query()->with('variant')->findOrFail($booking->unit_id);
            $old = $booking->starts_on->format('j M').'–'.$booking->ends_on->format('j M Y');
            // Lock the variant's units at the branch, as checkout does, then look again without this booking.
            Unit::query()->where('variant_id', $unit->variant_id)->where('branch_id', $unit->branch_id)->lockForUpdate()->pluck('id');
            $free = fn (int $unitId) => ! Booking::query()->where('unit_id', $unitId)->whereKeyNot($booking->id)->overlapping($period, $booking->buffer_days)->exists();
            // A unit the customer already has can't be swapped for another.
            $target = $free($unit->id) ? $unit->id : ($booking->picked_up_at !== null ? null
                : Unit::query()->where('variant_id', $unit->variant_id)->where('branch_id', $unit->branch_id)->where('active', true)->where('condition', 'good')
                    ->orderBy('id')->pluck('id')->first(fn (int $id) => $free($id)));
            if ($target === null) {
                throw new RentalException(__('commerce-rental::rental.errors.unavailable'));
            }
            $booking->forceFill([
                'unit_id' => $target, 'starts_on' => $period->start->toDateString(), 'ends_on' => $period->end->toDateString(),
                'available_from' => $period->availableFrom($booking->buffer_days)->toDateString(),
            ])->save();
            $order = Order::query()->find($booking->order_id);
            if ($order !== null) {
                $code = (string) Unit::query()->whereKey($target)->value('code');
                app(Timeline::class)->record($order, 'note', __('commerce-rental::rental.ops.timeline_dates', [
                    'old' => $old, 'new' => $period->start->format('j M').'–'.$period->end->format('j M Y'), 'unit' => $code,
                ]), ['booking_id' => $booking->id], $actor);
            }

            return $booking;
        });
    }
}
