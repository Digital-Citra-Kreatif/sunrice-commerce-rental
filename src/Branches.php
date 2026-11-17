<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Sunrice\Commerce\Pricing\CalculationState;
use Sunrice\CommerceRental\Models\Branch;

/** The branches a cart's rentals come from. */
class Branches
{
    /** @return list<int> */
    public function idsOfCart(CalculationState $state): array
    {
        $ids = [];
        foreach ($state->lines as $line) {
            $period = RentalPeriod::fromMeta($line->meta);
            if ($period !== null) {
                $ids[$period->branchId] = $period->branchId;
            }
        }

        return array_values($ids);
    }

    /** The branch of the cart's rentals (the first, if there are several), or null without rentals. */
    public function ofCart(CalculationState $state): ?Branch
    {
        $ids = $this->idsOfCart($state);

        return $ids === [] ? null : Branch::query()->find($ids[0]);
    }
}
