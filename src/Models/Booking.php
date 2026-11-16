<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Sunrice\CommerceRental\RentalPeriod;

/**
 * @property int $id
 * @property int $unit_id
 * @property string $kind order|block
 * @property int|null $order_id
 * @property int|null $order_item_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property Carbon $available_from
 * @property int $buffer_days
 * @property string $status active|released
 * @property string|null $note
 * @property int|null $user_id
 */
class Booking extends Model
{
    public const ORDER = 'order';

    public const BLOCK = 'block';

    public const ACTIVE = 'active';

    public const RELEASED = 'released';

    protected $table = 'commerce_rental_bookings';

    protected $guarded = ['id'];

    protected $casts = [
        'starts_on' => 'date', 'ends_on' => 'date', 'available_from' => 'date',
        'buffer_days' => 'integer', 'unit_id' => 'integer', 'order_id' => 'integer', 'order_item_id' => 'integer',
    ];

    /**
     * A block (staff taking a unit out for some days: a photo shoot, repairs)
     * is free again the day after it ends, without rest days. It is saved only
     * when the unit has nothing else on those days: the unit row is locked
     * first, as checkout locks it, so a block and an order can't both get it.
     */
    protected static function booted(): void
    {
        static::saving(function (Booking $booking): void {
            if ($booking->kind !== self::BLOCK || $booking->status !== self::ACTIVE) {
                return;
            }
            if ($booking->ends_on->lt($booking->starts_on)) {
                throw ValidationException::withMessages(['ends_on' => __('commerce-rental::rental.block.ends_before')]);
            }
            $booking->buffer_days = 0;
            $booking->available_from = $booking->ends_on->copy()->addDay();
            Unit::query()->whereKey($booking->unit_id)->lockForUpdate()->first();
            $period = new RentalPeriod(CarbonImmutable::instance($booking->starts_on), CarbonImmutable::instance($booking->ends_on), 0);
            $clash = self::query()->where('unit_id', $booking->unit_id)->when($booking->exists, fn ($q) => $q->whereKeyNot($booking->id))
                ->overlapping($period, 0)->orderBy('starts_on')->first();
            if ($clash !== null) {
                throw ValidationException::withMessages(['starts_on' => __('commerce-rental::rental.block.clash', [
                    'from' => $clash->starts_on->format('j M'), 'to' => $clash->available_from->copy()->subDay()->format('j M Y'),
                ])]);
            }
        });
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Active bookings that keep a unit from being rented for $period with $buffer rest days after
     * it: an existing booking [s', free'), against the new [s, e + 1 + buffer).
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeOverlapping(Builder $query, RentalPeriod $period, int $buffer): void
    {
        $query->where('status', self::ACTIVE)
            ->whereDate('starts_on', '<', $period->availableFrom($buffer)->toDateString())
            ->whereDate('available_from', '>', $period->start->toDateString());
    }
}
