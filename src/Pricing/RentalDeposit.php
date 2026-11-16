<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Pricing;

use Carbon\CarbonImmutable;
use Sunrice\Commerce\Pricing\CalculationState;
use Sunrice\Commerce\Pricing\Contracts\CalculationStage;
use Sunrice\CommerceRental\RentalPeriod;
use Sunrice\CommerceRental\RentalSettings;

/**
 * The deposit for a cart with rentals (Settings → Rentals → Deposit): a
 * percentage of the total or a fixed amount per dress, the balance due
 * balance_days_before the first pickup day. When that day is today or
 * already past, there's no deposit: the order is paid in full.
 */
class RentalDeposit implements CalculationStage
{
    /** After the totals (90). */
    public const PRIORITY = 95;

    public function __construct(protected RentalSettings $settings) {}

    public function run(CalculationState $state): void
    {
        $mode = (string) $this->settings->get('deposit_mode');
        if ($mode === RentalSettings::DEPOSIT_NONE || $state->totals['total'] <= 0) {
            return;
        }
        $first = null;
        $dresses = 0;
        foreach ($state->lines as $line) {
            $period = RentalPeriod::fromMeta($line->meta);
            if ($period === null) {
                continue;
            }
            $first = $first === null || $period->start->lt($first) ? $period->start : $first;
            $dresses += $line->quantity;
        }
        if ($first === null) {
            return;
        }
        $due = $first->subDays($this->settings->int('balance_days_before'));
        if ($due->lte(CarbonImmutable::today())) {
            return;
        }

        $amount = match ($mode) {
            RentalSettings::DEPOSIT_PERCENT => (int) round($state->totals['total'] * min(99, max(1, $this->settings->int('deposit_percent'))) / 100),
            RentalSettings::DEPOSIT_FIXED => $this->settings->int('deposit_fixed') * $dresses,
            default => 0,
        };
        $state->deposit = ['amount' => $amount, 'balance_due_at' => $due->toIso8601String(), 'label' => __('commerce-rental::rental.deposit')];
    }
}
