<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Illuminate\Database\Eloquent\Collection;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\CommerceRental\Models\Unit;

/** Which units of a variant are free for a period at a branch. */
class Availability
{
    public function __construct(protected Buffers $buffers) {}

    /**
     * Free units, lowest id first. With $lock (inside a transaction) every
     * active unit of the variant at the branch is locked first, so two
     * checkouts for the same dress queue up instead of both seeing it free.
     *
     * @return Collection<int, Unit>
     */
    public function freeUnits(Variant $variant, RentalPeriod $period, bool $lock = false, ?int $buffer = null): Collection
    {
        $buffer ??= $this->buffers->forVariant($variant);
        $units = Unit::query()
            ->where('variant_id', $variant->id)
            ->where('branch_id', $period->branchId)
            ->where('active', true)
            ->orderBy('id');
        if ($lock) {
            $ids = (clone $units)->lockForUpdate()->pluck('id')->all();
            if ($ids === []) {
                return new Collection;
            }
            $units = Unit::query()->whereIn('id', $ids)->orderBy('id');
        }

        return $units->whereDoesntHave('bookings', fn ($q) => $q->overlapping($period, $buffer))->get();
    }

    public function count(Variant $variant, RentalPeriod $period): int
    {
        return $this->freeUnits($variant, $period)->count();
    }

    /** Whether this variant is rented out (has units) rather than sold. */
    public function isRental(Variant $variant): bool
    {
        return Unit::query()->where('variant_id', $variant->id)->exists();
    }
}
