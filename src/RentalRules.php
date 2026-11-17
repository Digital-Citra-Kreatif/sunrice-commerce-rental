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
        if ($period->package !== null) {
            // A package has its own length, whatever the shortest and longest rentals are.
            $package = $this->settings->package($period->package);
            if ($package === null) {
                return __('commerce-rental::rental.errors.package');
            }
            if ($period->days() !== $package['days']) {
                return __('commerce-rental::rental.errors.package_days', ['days' => $package['days']]);
            }
        } elseif ($period->days() < $this->minDays()) {
            return __('commerce-rental::rental.errors.too_short', ['days' => $this->minDays()]);
        } elseif ($period->days() > $this->maxDays()) {
            return __('commerce-rental::rental.errors.too_long', ['days' => $this->maxDays()]);
        }
        if ($this->latest() !== null && $period->start->gt($this->latest())) {
            return __('commerce-rental::rental.errors.too_far');
        }

        return null;
    }

    public function earliest(): CarbonImmutable
    {
        return CarbonImmutable::today()->addDays($this->settings->int('min_days_ahead'));
    }

    /** The last day a rental can start, or null when there's no limit. */
    public function latest(): ?CarbonImmutable
    {
        $days = $this->settings->int('max_days_ahead');

        return $days > 0 ? CarbonImmutable::today()->addDays($days) : null;
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
