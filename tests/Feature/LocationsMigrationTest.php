<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Sunrice\CommerceMultiLocations\Models\Location;
use Sunrice\CommerceRental\Models\Transfer;
use Sunrice\CommerceRental\Models\Unit;
use Sunrice\CommerceRental\RentalPeriod;

/** The migration that turns rental branches into locations. */
function branchesMigration(): object
{
    return require __DIR__.'/../../database/migrations/2026_11_20_000100_move_rental_branches_to_locations.php';
}

beforeEach(function () {
    $this->variant = rentalStore(jakartaUnits: 1);
});

it('turns branches into locations, keeping their ids, units and moves', function () {
    $unit = Unit::query()->firstOrFail();
    Transfer::query()->create(['unit_id' => $unit->id, 'from_location_id' => location('BDG')->id, 'to_location_id' => location('JKT')->id, 'note' => 'Back']);
    $jakarta = location('JKT')->id;
    $bandung = location('BDG')->id;
    branchesMigration()->down();
    Location::query()->delete();

    expect(Schema::hasColumn('commerce_rental_units', 'branch_id'))->toBeTrue()
        ->and(DB::table('commerce_rental_branches')->orderBy('id')->pluck('code', 'id')->all())->toBe([$jakarta => 'JKT', $bandung => 'BDG']);
    DB::table('commerce_rental_branches')->where('code', 'JKT')->update(['postal_code' => '12190', 'phone' => '0217000001']);

    branchesMigration()->up();

    expect(Schema::hasTable('commerce_rental_branches'))->toBeFalse()
        ->and(Schema::hasColumn('commerce_rental_units', 'branch_id'))->toBeFalse()
        ->and(Location::query()->orderBy('id')->pluck('code', 'id')->all())->toBe([$jakarta => 'JKT', $bandung => 'BDG'])
        ->and(Location::query()->find($jakarta)->only(['postal_code', 'phone', 'whatsapp', 'pickup', 'ships_from']))
        ->toBe(['postal_code' => '12190', 'phone' => '0217000001', 'whatsapp' => '6281100000001', 'pickup' => true, 'ships_from' => true])
        ->and($unit->fresh()->location_id)->toBe($jakarta)
        ->and(Transfer::query()->sole()->only(['from_location_id', 'to_location_id']))->toBe(['from_location_id' => $bandung, 'to_location_id' => $jakarta]);
});

it('uses the location with the same code, and points rental lines at it', function () {
    $jakarta = location('JKT')->id;
    $cart = rentalCart($this->variant, period(5));
    branchesMigration()->down();
    Location::query()->delete();
    // The store already had a "JKT" location (another id) before rentals moved to locations.
    $existing = Location::query()->create(['code' => 'JKT', 'name' => 'Toko Jakarta', 'pickup' => true]);
    $cart->items()->update(['meta' => json_encode(['rental' => ['start' => '2026-12-10', 'end' => '2026-12-12', 'branch' => $jakarta]])]);

    branchesMigration()->up();

    expect(Unit::query()->firstOrFail()->location_id)->toBe($existing->id)
        ->and($cart->items()->first()->meta['rental'])->toBe(['start' => '2026-12-10', 'end' => '2026-12-12', 'location' => $existing->id])
        ->and(Location::query()->where('code', 'BDG')->exists())->toBeTrue();
});

it('reads rental lines from before locations', function () {
    $period = RentalPeriod::fromMeta(['rental' => ['start' => '2026-12-10', 'end' => '2026-12-12', 'branch' => 7]]);

    expect($period?->locationId)->toBe(7)
        ->and(RentalPeriod::make('2026-12-10', '2026-12-12', 7)->toMeta())->toBe(['rental' => ['start' => '2026-12-10', 'end' => '2026-12-12', 'location' => 7]]);
});

it('takes the location from forms that still send branch', function () {
    $p = period(5);

    $this->from('/products/gaun-merah')->post('/rental/cart', ['variant' => $this->variant->id, 'branch' => location('JKT')->id,
        'start' => $p->start->toDateString(), 'end' => $p->end->toDateString()])->assertSessionHas('commerce.success');
});
