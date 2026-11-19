<?php

declare(strict_types=1);

use Sunrice\Commerce\Analytics\Period;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Reports\UtilizationReport;

it('reports the days each dress was rented at each location', function () {
    $variant = rentalStore(jakartaUnits: 2);
    placeRental(rentalCart($variant, period(1, days: 3)));
    placeRental(rentalCart($variant, period(2, days: 2)));
    Booking::query()->where('kind', 'order')->first()->forceFill(['picked_up_at' => now()])->save();

    $from = now()->addDay()->toDateString();
    $to = now()->addDays(10)->toDateString();
    $result = (new UtilizationReport)->run(new Period($from, $to));

    expect($result->rows)->toHaveCount(1)
        ->and($result->rows[0])->toMatchArray(['product' => 'Gaun Merah', 'location' => 'Jakarta', 'units' => 2, 'rentals' => 2, 'rented' => 5, 'utilization' => 25.0, 'late' => 0]);

    actingAsSuperAdmin();
    $this->get("/admin/commerce/reports/rental-utilization?from={$from}&to={$to}")->assertOk();
});
