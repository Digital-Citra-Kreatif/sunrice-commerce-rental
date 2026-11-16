<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Sunrice\Commerce\Catalog\Models\Variant;

/**
 * @property int $id
 * @property int $variant_id
 * @property int $branch_id
 * @property string $code
 * @property string $condition
 * @property bool $active
 */
class Unit extends Model
{
    protected $table = 'commerce_rental_units';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean', 'variant_id' => 'integer', 'branch_id' => 'integer'];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
}
