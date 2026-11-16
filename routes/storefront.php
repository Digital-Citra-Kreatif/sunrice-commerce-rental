<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;
use Sunrice\Commerce\Storefront\StorefrontRoutes;
use Sunrice\CommerceRental\Http\RentalController;

StorefrontRoutes::localized(function (string $suffix): void {
    Route::prefix('rental')->group(function () use ($suffix): void {
        Route::post('cart', [RentalController::class, 'add'])->middleware([ProtectAgainstSpam::class, 'throttle:30,1'])->name('commerce-rental.cart.add'.$suffix);
        Route::get('availability', [RentalController::class, 'availability'])->middleware('throttle:120,1')->name('commerce-rental.availability'.$suffix);
    });
});
