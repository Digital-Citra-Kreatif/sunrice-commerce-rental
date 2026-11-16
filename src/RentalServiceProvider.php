<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Illuminate\Support\Facades\Blade;
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
            ->hasTranslations()
            ->hasViews('commerce-rental');
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

        Commerce::registerFeature(new Feature('rental', 'selling', 'Rentals',
            'Rent products out by date: units per branch, bookings with rest days between rentals, no double booking.', ['orders']));
        Commerce::registerDiscountProvider(RentalReservations::class);
        Commerce::registerCalculationStage(RentalPrice::class, RentalPrice::PRIORITY);
        Commerce::registerCalculationStage(RentalDeposit::class, RentalDeposit::PRIORITY);
        Commerce::registerSettingsSection(RentalSettings::section());
        Commerce::describeLinesUsing(fn (array $meta): array => app(LineDescriber::class)($meta));
        // The booking form on the product page of a rental (a theme can place <x-commerce-rental::booking> itself instead).
        Commerce::registerStorefrontSlot('product.before_add_to_cart', 'commerce-rental::slot', 'rental');
        $this->loadRoutesFrom(__DIR__.'/../routes/storefront.php');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'commerce-rental');
    }
}
