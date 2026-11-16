<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Models\Branch;
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
            ->where('condition', 'good')
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

    /**
     * Free units of the variant for these days at each active branch that has
     * it, in the branches' order: [{branch, available}].
     *
     * @return list<array{branch: Branch, available: int}>
     */
    public function atBranches(Variant $variant, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $out = [];
        $branches = Branch::query()->where('active', true)->whereHas('units', fn ($q) => $q->where('variant_id', $variant->id))->orderBy('sort_order')->orderBy('id')->get();
        foreach ($branches as $branch) {
            $out[] = ['branch' => $branch, 'available' => $this->count($variant, new RentalPeriod($start, $end, $branch->id))];
        }

        return $out;
    }

    /**
     * For a date picker: how many units could go out on each day from $from
     * for $days days (a one-day rental starting that day, with the rest days
     * after it). Two queries, whatever the number of days.
     *
     * @return array<string, int> Y-m-d => free units
     */
    public function calendar(Variant $variant, int $branchId, CarbonImmutable $from, int $days): array
    {
        $buffer = $this->buffers->forVariant($variant);
        $units = Unit::query()->where('variant_id', $variant->id)->where('branch_id', $branchId)->where('active', true)->where('condition', 'good')->pluck('id')->all();
        $to = $from->addDays($days + $buffer + 1);
        $bookings = $units === [] ? collect() : Booking::query()->whereIn('unit_id', $units)->where('status', Booking::ACTIVE)
            ->whereDate('starts_on', '<', $to->toDateString())->whereDate('available_from', '>', $from->toDateString())
            ->get(['unit_id', 'starts_on', 'available_from'])->groupBy('unit_id');
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->addDays($i);
            $until = $day->addDays(1 + $buffer)->toDateString();
            $free = 0;
            foreach ($units as $unit) {
                $taken = ($bookings[$unit] ?? collect())->contains(fn (Booking $b) => $b->starts_on->toDateString() < $until && $b->available_from->toDateString() > $day->toDateString());
                $free += $taken ? 0 : 1;
            }
            $out[$day->toDateString()] = $free;
        }

        return $out;
    }

    /** Whether this variant is rented out (has units) rather than sold. */
    public function isRental(Variant $variant): bool
    {
        return Unit::query()->where('variant_id', $variant->id)->exists();
    }
}
