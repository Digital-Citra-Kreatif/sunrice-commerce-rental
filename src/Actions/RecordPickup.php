<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Actions;

use Sunrice\Commerce\Orders\Actor;
use Sunrice\Commerce\Orders\Models\Order;
use Sunrice\Commerce\Orders\Timeline;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\RentalException;

/** The customer took the unit: noted on the booking and the order's timeline. */
class RecordPickup
{
    public function handle(Booking $booking, Actor $actor): void
    {
        if ($booking->kind !== Booking::ORDER || $booking->status !== Booking::ACTIVE) {
            throw new RentalException(__('commerce-rental::rental.ops.not_active'));
        }
        if ($booking->picked_up_at !== null) {
            throw new RentalException(__('commerce-rental::rental.ops.already_picked_up'));
        }
        $order = Order::query()->find($booking->order_id);
        if ($order !== null && ! $order->isPaid() && ! $order->isPartiallyPaid()) {
            throw new RentalException(__('commerce-rental::rental.ops.unpaid'));
        }
        $booking->forceFill(['picked_up_at' => now()])->save();
        if ($order !== null) {
            app(Timeline::class)->record($order, 'note', __('commerce-rental::rental.ops.timeline_picked_up', ['unit' => $booking->unit->code ?? '']), ['booking_id' => $booking->id], $actor);
        }
    }
}
