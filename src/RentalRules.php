<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Carbon\CarbonImmutable;

/**
 * The booking limits from Settings → Rentals: the earliest and latest
 * pickup day and the shortest and longest rental. The booking form, the
 * add-to-cart and the checkout all check with it.
 */
class RentalRules
{
    public function __construct(protected RentalSettings $settings) {}

    /** Why these dates can't be booked, in the customer's language, or null when they can. */
    public function refusal(RentalPeriod $period): ?string
    {
        $today = CarbonImmutable::today();
        if ($period->start->lt($today)) {
            return __('commerce-rental::rental.errors.past');
        }
        if ($period->start->lt($this->earliest())) {
            return __('commerce-rental::rental.errors.too_soon', ['days' => $this->settings->int('min_days_ahead')]);
        }
        if ($period->days() < $this->minDays()) {
            return __('commerce-rental::rental.errors.too_short', ['days' => $this->minDays()]);
        }
        if ($period->days() > $this->maxDays()) {
            return __('commerce-rental::rental.errors.too_long', ['days' => $this->maxDays()]);
        }
        if ($period->start->gt($this->latest())) {
            return __('commerce-rental::rental.errors.too_far');
        }

        return null;
    }

    public function earliest(): CarbonImmutable
    {
        return CarbonImmutable::today()->addDays($this->settings->int('min_days_ahead'));
    }

    public function latest(): CarbonImmutable
    {
        return CarbonImmutable::today()->addDays(max(1, $this->settings->int('max_days_ahead')));
    }

    public function minDays(): int
    {
        return max(1, $this->settings->int('min_days'));
    }

    public function maxDays(): int
    {
        return max($this->minDays(), $this->settings->int('max_days'));
    }
}
