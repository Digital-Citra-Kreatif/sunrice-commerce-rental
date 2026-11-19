<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Sunrice\Commerce\Settings\StoreSettings;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $whatsapp
 * @property string|null $address
 * @property bool $active
 * @property string|null $phone
 * @property string|null $postal_code
 * @property string|null $latitude
 * @property string|null $longitude
 */
class Branch extends Model
{
    protected $table = 'commerce_rental_branches';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'boolean', 'sort_order' => 'integer'];

    /**
     * The branch as a shipping origin (commerce's Address fields): where
     * couriers pick its deliveries up, named "<store name> <branch>".
     *
     * @return array<string, string|null>
     */
    public function shippingAddress(): array
    {
        return [
            'name' => trim(app(StoreSettings::class)->get('general.name', '').' '.$this->name), 'phone' => $this->phone ?: $this->whatsapp, 'line1' => $this->address,
            'postal_code' => $this->postal_code, 'country' => 'ID',
            'latitude' => $this->latitude !== null ? (string) $this->latitude : null, 'longitude' => $this->longitude !== null ? (string) $this->longitude : null,
        ];
    }

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
}
