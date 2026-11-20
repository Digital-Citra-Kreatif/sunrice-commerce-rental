<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Illuminate\Support\Facades\Route;
use Sunrice\Admin\Assets\PluginBundle;
use Sunrice\Admin\BasePlugin;
use Sunrice\Admin\Navigation\NavigationItem;
use Sunrice\Admin\Panel;
use Sunrice\Commerce\CommercePlugin;
use Sunrice\CommerceMultiLocations\MultiLocationsPlugin;
use Sunrice\CommerceRental\Admin\BlockResource;
use Sunrice\CommerceRental\Admin\BookingResource;
use Sunrice\CommerceRental\Admin\UnitResource;
use Sunrice\CommerceRental\Http\Admin\CalendarController;

/**
 * The rental screens, added after the store and its locations in the app's
 * panel provider:
 *
 *     $panel->plugin(CmsPlugin::make())->plugin(CommercePlugin::make())
 *         ->plugin(MultiLocationsPlugin::make())->plugin(RentalPlugin::make());
 *
 * Rentals → Bookings, Units and Blocked dates (permissions rental.bookings.*,
 * rental.units.*, rental.blocks.*); the places units are picked up at are
 * the store's locations (Locations → Locations).
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
        return [CommercePlugin::ID, MultiLocationsPlugin::ID];
    }

    public function register(Panel $panel): void
    {
        $panel->resources(BookingResource::class, UnitResource::class, BlockResource::class);
        // Rentals → Calendar: a React page (resources/js/admin.tsx, built to dist/).
        $panel->navigation(NavigationItem::make('rental.calendar')->label('Calendar')->url('rental/calendar')->icon('calendar-days')
            ->group('Rentals')->sort(5)->permission('rental.bookings.view'));
        $panel->routes(function (): void {
            Route::get('rental/calendar', CalendarController::class)->name('rental.calendar')->middleware('can:rental.bookings.view');
        });
        $panel->bundle(PluginBundle::make(self::ID)->named('Sunrice Commerce Rental')
            ->publishedFrom(dirname(__DIR__).'/dist', public_path('vendor/commerce-rental'))
            ->module('admin.js')->pages('Rental/'));
    }
}
