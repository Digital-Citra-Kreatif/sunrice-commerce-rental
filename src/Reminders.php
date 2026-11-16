<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Sunrice\Commerce\Notifications\Notifier;
use Sunrice\Commerce\Orders\Models\Order;
use Sunrice\Commerce\Orders\OrderStatus;
use Sunrice\CommerceRental\Models\Booking;

/**
 * Emails customers before their pickup day and before their return day
 * (Settings → Rentals → Reminders, days before; 0 for none), one email per
 * order listing its rentals that day, each booking once. Only orders that
 * are paid or have their deposit paid are reminded of a pickup.
 */
class Reminders
{
    public const PICKUP = 'rental_pickup_reminder';

    public const RETURN = 'rental_return_reminder';

    public function __construct(protected RentalSettings $settings, protected Notifier $notifier) {}

    /** @return int emails sent */
    public function __invoke(): int
    {
        return $this->send(self::PICKUP, 'pickup_reminder_days', 'starts_on', 'pickup_reminded_at', fn ($q) => $q->whereNull('picked_up_at'))
            + $this->send(self::RETURN, 'return_reminder_days', 'ends_on', 'return_reminded_at', fn ($q) => $q->whereNull('returned_at'));
    }

    /** @param  callable(mixed): mixed  $scope */
    protected function send(string $email, string $setting, string $column, string $flag, callable $scope): int
    {
        $days = $this->settings->int($setting);
        if ($days <= 0) {
            return 0;
        }
        $today = CarbonImmutable::today();
        $bookings = Booking::query()->where('kind', Booking::ORDER)->where('status', Booking::ACTIVE)->whereNull($flag)
            ->whereDate($column, '>=', $today->toDateString())->whereDate($column, '<=', $today->addDays($days)->toDateString())
            ->tap($scope)->with('unit.branch')->orderBy($column)->limit(500)->get();
        $sent = 0;
        /** @var Collection<int, Booking> $group */
        foreach ($bookings->groupBy('order_id') as $orderId => $group) {
            $order = Order::query()->find($orderId);
            if ($order === null || in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED, OrderStatus::COMPLETED], true)
                || ($email === self::PICKUP && ! $order->isPaid() && ! $order->isPartiallyPaid())) {
                continue;
            }
            Booking::query()->whereIn('id', $group->pluck('id'))->update([$flag => now()]);
            $sent += $this->notifier->send($email, $order, ['bookings' => $group->all()]) > 0 ? 1 : 0;
        }

        return $sent;
    }
}
