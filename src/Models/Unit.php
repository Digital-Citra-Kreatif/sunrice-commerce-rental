<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\CommerceMultiLocations\Models\Location;

/**
 * @property int $id
 * @property int $variant_id
 * @property int $location_id
 * @property string $code
 * @property string $condition
 * @property bool $active
 * @property string|null $note
 */
class Unit extends Model
{
    protected $table = 'commerce_rental_units';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean', 'variant_id' => 'integer', 'location_id' => 'integer'];

    /** @return BelongsTo<Location, $this> where the unit is picked up and returned */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Variant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<Transfer, $this> */
    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class)->latest('id');
    }

    /**
     * Active bookings (orders and blocks) that end today or later.
     *
     * @return HasMany<Booking, $this>
     */
    public function upcoming(): HasMany
    {
        return $this->bookings()->where('status', Booking::ACTIVE)->whereDate('ends_on', '>=', now()->toDateString())->orderBy('starts_on');
    }
}
