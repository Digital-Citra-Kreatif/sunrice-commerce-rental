<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
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
