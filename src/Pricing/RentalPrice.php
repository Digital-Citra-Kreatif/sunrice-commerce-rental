<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Pricing;

use Sunrice\Commerce\Pricing\CalculationState;
use Sunrice\Commerce\Pricing\Contracts\CalculationStage;
use Sunrice\CommerceRental\RentalPeriod;

/**
 * Turns the daily price into the rental price (daily price × days) after
 * unit prices (20) and before subtotals (30), so discounts and vouchers
 * apply to the whole rental.
 */
class RentalPrice implements CalculationStage
{
    public const PRIORITY = 25;

    public function run(CalculationState $state): void
    {
        foreach ($state->lines as $line) {
            $period = RentalPeriod::fromMeta($line->meta);
            if ($period === null) {
                continue;
            }
            $days = $period->days();
            $line->priceMeta = [...$line->priceMeta, 'daily' => $line->unitPrice, 'days' => $days];
            $line->unitPrice *= $days;
            $line->regularUnitPrice *= $days;
        }
    }
}
