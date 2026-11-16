<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $whatsapp
 * @property string|null $address
 * @property bool $active
 */
class Branch extends Model
{
    protected $table = 'commerce_rental_branches';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean', 'sort_order' => 'integer'];

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
}
