<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Pricing;

use Sunrice\Commerce\Checkout\CheckoutRefused;
use Sunrice\Commerce\Orders\Models\Order;
use Sunrice\Commerce\Orders\Models\OrderItem;
use Sunrice\Commerce\Pricing\CalcLine;
use Sunrice\Commerce\Pricing\CalculationState;
use Sunrice\Commerce\Pricing\Contracts\DiscountProvider;
use Sunrice\Commerce\Pricing\Contracts\ReservesDiscounts;
use Sunrice\CommerceRental\Availability;
use Sunrice\CommerceRental\Buffers;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\RentalPeriod;
use Sunrice\CommerceRental\RentalRules;

/**
 * Books the units of an order's rental lines inside PlaceOrder's
 * transaction (ReservesDiscounts::commit), and frees them when the order
 * is cancelled or expires unpaid. It gives no discount: apply() is empty.
 */
class RentalReservations implements DiscountProvider, ReservesDiscounts
{
    public function __construct(protected Availability $availability, protected Buffers $buffers) {}

    public function priority(): int
    {
        return 0;
    }

    public function apply(CalculationState $state): void {}

    public function commit(Order $order, CalculationState $state): void
    {
        $lines = array_values($state->lines);
        // A stable lock order (by variant) so two orders never wait on each other.
        usort($lines, fn (CalcLine $a, CalcLine $b) => $a->variant->id <=> $b->variant->id);
        $items = $order->items()->get();

        foreach ($lines as $line) {
            $period = RentalPeriod::fromMeta($line->meta);
            if ($period === null) {
                if ($this->availability->isRental($line->variant)) {
                    throw new CheckoutRefused(__('commerce-rental::rental.errors.dates_missing'));
                }

                continue;
            }
            $this->validate($period);

            $buffer = $this->buffers->forVariant($line->variant);
            $units = $this->availability->freeUnits($line->variant, $period, lock: true, buffer: $buffer)->take($line->quantity);
            if ($units->count() < $line->quantity) {
                throw new CheckoutRefused(__('commerce-rental::rental.errors.unavailable'));
            }

            $item = $this->item($items->all(), $line);
            foreach ($units as $unit) {
                Booking::query()->create([
                    'unit_id' => $unit->id, 'kind' => Booking::ORDER, 'order_id' => $order->id, 'order_item_id' => $item?->id,
                    'starts_on' => $period->start->toDateString(), 'ends_on' => $period->end->toDateString(),
                    'available_from' => $period->availableFrom($buffer)->toDateString(), 'buffer_days' => $buffer,
                    'status' => Booking::ACTIVE,
                ]);
            }
        }
    }

    public function release(Order $order): void
    {
        Booking::query()->where('order_id', $order->id)->where('kind', Booking::ORDER)->where('status', Booking::ACTIVE)
            ->update(['status' => Booking::RELEASED, 'updated_at' => now()]);
    }

    protected function validate(RentalPeriod $period): void
    {
        $refusal = app(RentalRules::class)->refusal($period);
        if ($refusal !== null) {
            throw new CheckoutRefused($refusal);
        }
    }

    /** @param  array<int, OrderItem>  $items */
    protected function item(array $items, CalcLine $line): ?OrderItem
    {
        foreach ($items as $item) {
            if ((int) $item->variant_id === $line->variant->id && ($item->meta['line'] ?? []) == $line->meta) {
                return $item;
            }
        }

        return null;
    }
}
