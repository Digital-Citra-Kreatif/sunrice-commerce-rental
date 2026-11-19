<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Sunrice\Commerce\Facades\Commerce;
use Sunrice\Commerce\Features\Feature;
use Sunrice\Commerce\Notifications\EmailDefinition;
use Sunrice\Commerce\Pricing\CalculationState;
use Sunrice\CommerceMultiLocations\DeletionGuards;
use Sunrice\CommerceMultiLocations\Fulfilment;
use Sunrice\CommerceMultiLocations\Models\Location;
use Sunrice\CommerceRental\Console\ImportUnitsCommand;
use Sunrice\CommerceRental\Console\RemindCommand;
use Sunrice\CommerceRental\Models\Unit;
use Sunrice\CommerceRental\Pricing\RentalDeposit;
use Sunrice\CommerceRental\Pricing\RentalPrice;
use Sunrice\CommerceRental\Pricing\RentalReservations;
use Sunrice\CommerceRental\Reports\UtilizationReport;

class RentalServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('commerce-rental')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews('commerce-rental')
            ->hasCommands([RemindCommand::class, ImportUnitsCommand::class]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Buffers::class);
        $this->app->singleton(Availability::class);
        $this->app->scoped(RentalSettings::class);
        $this->app->scoped(LineDescriber::class);
    }

    public function packageBooted(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->publishes([dirname(__DIR__).'/dist' => public_path('vendor/commerce-rental')], ['commerce-rental-assets', 'laravel-assets']);

        Commerce::registerFeature(new Feature('rental', 'selling', 'Rentals',
            'Rent products out by date: units per location, bookings with rest days between rentals, no double booking.', ['orders', 'locations']));
        Commerce::registerDiscountProvider(RentalReservations::class);
        Commerce::registerCalculationStage(RentalPrice::class, RentalPrice::PRIORITY);
        Commerce::registerCalculationStage(RentalDeposit::class, RentalDeposit::PRIORITY);
        Commerce::registerSettingsSection(RentalSettings::section());
        Commerce::registerReport(UtilizationReport::class);
        // A rental is picked up at, or delivered from, the location of its unit (Locations decides the courier origin from it).
        app(Fulfilment::class)->resolveUsing(fn (CalculationState $state): ?array => self::locationsOf($state));
        app(DeletionGuards::class)->register(fn (Location $location): ?string => Unit::query()->where('location_id', $location->id)->exists()
            ? Wording::trans('commerce-rental::rental.location.has_units') : null);
        // Listings: ?rent_from=2026-12-10&rent_to=2026-12-12[&rent_location=1] keeps the dresses free then (?rent_branch= too).
        Commerce::registerCatalogFilter('rental', function (Request $request): ?array {
            $from = (string) $request->query('rent_from', '');
            $to = (string) ($request->query('rent_to') ?: $from);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1 || $to < $from) {
                return null;
            }

            return ['from' => $from, 'to' => $to, 'location' => (int) $request->query('rent_location', $request->query('rent_branch', 0))];
        }, fn (Builder $products, mixed $value) => is_array($value)
            ? app(Availability::class)->whereFree($products, RentalPeriod::make((string) $value['from'], (string) $value['to'], (int) $value['location']))
            : null);
        Commerce::describeLinesUsing(fn (array $meta): array => app(LineDescriber::class)($meta));
        // The booking form on the product page of a rental (a theme can place <x-commerce-rental::booking> itself instead).
        Commerce::registerStorefrontSlot('product.before_add_to_cart', 'commerce-rental::slot', 'rental');
        Commerce::registerStorefrontSlot('order.after_summary', 'commerce-rental::order-locations', 'rental');
        $this->loadRoutesFrom(__DIR__.'/../routes/storefront.php');
        Commerce::registerEmail(new EmailDefinition(Reminders::PICKUP, 'Rental pickup reminder', feature: 'rental', lang: 'commerce-rental::emails.pickup',
            description: 'Before the pickup day (Settings → Rentals → Reminders).'));
        Commerce::registerEmail(new EmailDefinition(Reminders::RETURN, 'Rental return reminder', feature: 'rental', lang: 'commerce-rental::emails.return',
            description: 'Before the return day (Settings → Rentals → Reminders).'));
        Commerce::emailTemplate('commerce-rental::emails.bookings', Reminders::PICKUP);
        Commerce::emailTemplate('commerce-rental::emails.bookings', Reminders::RETURN);
        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('rental:remind')->hourly()->between('7:00', '20:00')->withoutOverlapping());
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'commerce-rental');
    }

    /**
     * The locations of a cart's rental lines, in the order they appear, or
     * null when it has none.
     *
     * @return list<int>|null
     */
    public static function locationsOf(CalculationState $state): ?array
    {
        $ids = [];
        foreach ($state->lines as $line) {
            $period = RentalPeriod::fromMeta($line->meta);
            if ($period !== null) {
                $ids[$period->locationId] = $period->locationId;
            }
        }

        return $ids === [] ? null : array_values($ids);
    }
}
