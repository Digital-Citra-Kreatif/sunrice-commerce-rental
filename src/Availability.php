<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Sunrice\Commerce\Catalog\Models\Product;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\CommerceMultiLocations\Locations;
use Sunrice\CommerceMultiLocations\Models\Location;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Models\Unit;

/** Which units of a variant are free for a period at a location. */
class Availability
{
    public function __construct(protected Buffers $buffers) {}

    /**
     * Free units, lowest id first. With $lock (inside a transaction) every
     * active unit of the variant at the location is locked first, so two
     * checkouts for the same dress queue up instead of both seeing it free.
     *
     * @return Collection<int, Unit>
     */
    public function freeUnits(Variant $variant, RentalPeriod $period, bool $lock = false, ?int $buffer = null): Collection
    {
        $buffer ??= $this->buffers->forVariant($variant);
        $units = Unit::query()
            ->where('variant_id', $variant->id)
            ->where('location_id', $period->locationId)
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
     * Free units of the variant for these days at each active location that
     * has it, in the locations' order: [{location, available}].
     *
     * @return list<array{location: Location, available: int}>
     */
    public function atLocations(Variant $variant, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $out = [];
        foreach ($this->locationsWith([$variant->id]) as $location) {
            $out[] = ['location' => $location, 'available' => $this->count($variant, new RentalPeriod($start, $end, $location->id))];
        }

        return $out;
    }

    /**
     * The active locations with an active unit of these variants, in order.
     *
     * @param  list<int>  $variantIds
     * @return list<Location>
     */
    public function locationsWith(array $variantIds): array
    {
        $ids = Unit::query()->whereIn('variant_id', $variantIds)->where('active', true)->distinct()->pluck('location_id')->map(fn ($id) => (int) $id)->all();

        return app(Locations::class)->active()->filter(fn (Location $l) => in_array($l->id, $ids, true))->values()->all();
    }

    /**
     * For a date picker: how many units could go out on each day from $from
     * for $days days (a one-day rental starting that day, with the rest days
     * after it). Two queries, whatever the number of days.
     *
     * @return array<string, int> Y-m-d => free units
     */
    public function calendar(Variant $variant, int $locationId, CarbonImmutable $from, int $days): array
    {
        $buffer = $this->buffers->forVariant($variant);
        $units = Unit::query()->where('variant_id', $variant->id)->where('location_id', $locationId)->where('active', true)->where('condition', 'good')->pluck('id')->all();
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

    /**
     * Narrows a products query to those with a unit free for the whole period
     * (at its location, or at any when the period's location is 0), with the
     * store's default rest days: for listings ("available 10–12 Dec"); the
     * add to cart and the checkout check again with each product's own.
     *
     * @param  Builder<Product>  $products
     */
    public function whereFree(Builder $products, RentalPeriod $period): void
    {
        $buffer = app(RentalSettings::class)->int('buffer_days');
        $products->whereHas('variants', fn ($variants) => $variants->where('active', true)->whereIn('id', Unit::query()
            ->where('active', true)->where('condition', 'good')
            ->when($period->locationId > 0, fn ($q) => $q->where('location_id', $period->locationId))
            ->whereDoesntHave('bookings', fn ($q) => $q->overlapping($period, $buffer))
            ->select('variant_id')));
    }

    /** Whether this variant is rented out (has units) rather than sold. */
    public function isRental(Variant $variant): bool
    {
        return Unit::query()->where('variant_id', $variant->id)->exists();
    }
}
