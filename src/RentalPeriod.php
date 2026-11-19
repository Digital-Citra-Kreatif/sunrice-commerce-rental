<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * A rental from the pickup day to the return day, both included: 10 to 12
 * June is 3 days, picked up and returned at a location. Kept on the cart line
 * as meta `rental` {start, end, location} (lines from before locations say
 * `branch`: the same id).
 */
final class RentalPeriod
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly int $locationId,
        /** A rental package's key (Settings → Rentals → Packages: "Luar kota, 7 days"), or null for a regular rental. */
        public readonly ?string $package = null,
    ) {
        if ($end->lt($start)) {
            throw new InvalidArgumentException('The return day is before the pickup day.');
        }
    }

    public static function make(string $start, string $end, int $locationId, ?string $package = null): self
    {
        return new self(CarbonImmutable::parse($start)->startOfDay(), CarbonImmutable::parse($end)->startOfDay(), $locationId, $package ?: null);
    }

    /**
     * The period in a cart line's meta, or null when the line isn't a rental (or is malformed).
     *
     * @param  array<string, mixed>  $meta
     */
    public static function fromMeta(array $meta): ?self
    {
        $rental = $meta['rental'] ?? null;
        $location = is_array($rental) ? ($rental['location'] ?? $rental['branch'] ?? null) : null;
        if (! is_array($rental) || ! isset($rental['start'], $rental['end']) || $location === null) {
            return null;
        }
        try {
            return self::make((string) $rental['start'], (string) $rental['end'], (int) $location, isset($rental['package']) ? (string) $rental['package'] : null);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{rental: array{start: string, end: string, location: int, package?: string}} */
    public function toMeta(): array
    {
        $rental = ['start' => $this->start->toDateString(), 'end' => $this->end->toDateString(), 'location' => $this->locationId];
        if ($this->package !== null) {
            $rental['package'] = $this->package;
        }

        return ['rental' => $rental];
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    /** The first day the unit can go out again. */
    public function availableFrom(int $buffer): CarbonImmutable
    {
        return $this->end->addDays(1 + max(0, $buffer));
    }
}
