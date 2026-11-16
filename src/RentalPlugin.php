<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Sunrice\Admin\BasePlugin;
use Sunrice\Admin\Panel;
use Sunrice\Commerce\CommercePlugin;
use Sunrice\CommerceRental\Admin\BlockResource;
use Sunrice\CommerceRental\Admin\BookingResource;
use Sunrice\CommerceRental\Admin\BranchResource;
use Sunrice\CommerceRental\Admin\UnitResource;

/**
 * The rental screens, added after the store in the app's panel provider:
 *
 *     $panel->plugin(CmsPlugin::make())->plugin(CommercePlugin::make())->plugin(RentalPlugin::make());
 *
 * Rentals → Bookings, Units, Branches and Blocked dates (permissions
 * rental.bookings.*, rental.units.*, rental.branches.*, rental.blocks.*).
 */
final class RentalPlugin extends BasePlugin
{
    public const ID = 'rental';

    public function id(): string
    {
        return self::ID;
    }

    /** @return list<string> */
    public function requires(): array
    {
        return [CommercePlugin::ID];
    }

    public function register(Panel $panel): void
    {
        $panel->resources(BookingResource::class, UnitResource::class, BranchResource::class, BlockResource::class);
    }
}
