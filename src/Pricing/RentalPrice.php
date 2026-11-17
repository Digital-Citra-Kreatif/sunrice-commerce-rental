<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Pricing;

use Sunrice\Commerce\Pricing\CalculationState;
use Sunrice\Commerce\Pricing\Contracts\CalculationStage;
use Sunrice\CommerceRental\RentalPeriod;
use Sunrice\CommerceRental\RentalSettings;

/**
 * The rental price, after unit prices (20) and before subtotals (30), so
 * discounts and vouchers apply to the whole rental (Settings → Rentals →
 * Pricing):
 * - "package" (the default): the product's price covers the first
 *   base_days days, each day more adds extra_day_fee;
 * - "daily": the product's price × the days;
 * - a chosen package (Out of town, 7 days): the product's price plus the
 *   package's fee, whatever the pricing.
 */
class RentalPrice implements CalculationStage
{
    public const PRIORITY = 25;

    public function __construct(protected RentalSettings $settings) {}

    public function run(CalculationState $state): void
    {
        foreach ($state->lines as $line) {
            $period = RentalPeriod::fromMeta($line->meta);
            if ($period === null) {
                continue;
            }
            [$unit, $regular, $meta] = $this->price($line->unitPrice, $line->regularUnitPrice, $period);
            $line->priceMeta = [...$line->priceMeta, ...$meta];
            $line->unitPrice = $unit;
            $line->regularUnitPrice = $regular;
        }
    }

    /**
     * The rental's price from the product's: [price, regular price, what it's made of].
     *
     * @return array{0: int, 1: int, 2: array<string, int|string>}
     */
    public function price(int $price, int $regular, RentalPeriod $period): array
    {
        $days = $period->days();
        $package = $this->settings->package($period->package);
        if ($package !== null) {
            return [$price + $package['fee'], $regular + $package['fee'], ['days' => $days, 'package' => $package['key'], 'package_fee' => $package['fee']]];
        }
        if ($this->settings->get('pricing') === 'daily') {
            return [$price * $days, $regular * $days, ['daily' => $price, 'days' => $days]];
        }
        $extra = max(0, $days - max(1, $this->settings->int('base_days'))) * $this->settings->int('extra_day_fee');

        return [$price + $extra, $regular + $extra, ['days' => $days, 'extra_days_fee' => $extra]];
    }
}
