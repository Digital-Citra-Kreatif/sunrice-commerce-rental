<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Sunrice\CommerceMultiLocations\Models\Location;

/**
 * @property int $id
 * @property int $unit_id
 * @property int $from_location_id
 * @property int $to_location_id
 * @property int|null $user_id
 * @property string|null $note
 * @property Carbon|null $created_at
 */
class Transfer extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'commerce_rental_transfers';

    protected $guarded = ['id'];

    /** @return BelongsTo<Location, $this> */
    public function from(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function to(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }
}
