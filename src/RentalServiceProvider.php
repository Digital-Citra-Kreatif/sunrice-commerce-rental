<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Sunrice\Commerce\Facades\Commerce;
use Sunrice\Commerce\Features\Feature;
use Sunrice\CommerceRental\Pricing\RentalDeposit;
use Sunrice\CommerceRental\Pricing\RentalPrice;
use Sunrice\CommerceRental\Pricing\RentalReservations;

class RentalServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('commerce-rental')
            ->hasConfigFile()
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Buffers::class);
        $this->app->singleton(Availability::class);
        $this->app->scoped(RentalSettings::class);
    }

    public function packageBooted(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Commerce::registerFeature(new Feature('rental', 'selling', 'Rentals',
            'Rent products out by date: units per branch, bookings with rest days between rentals, no double booking.', ['orders']));
        Commerce::registerDiscountProvider(RentalReservations::class);
        Commerce::registerCalculationStage(RentalPrice::class, RentalPrice::PRIORITY);
        Commerce::registerCalculationStage(RentalDeposit::class, RentalDeposit::PRIORITY);
        Commerce::registerSettingsSection(RentalSettings::section());
    }
}
