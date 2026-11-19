<?php

declare(strict_types=1);

use Sunrice\CommerceRental\Actions\MoveUnit;
use Sunrice\CommerceRental\Availability;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Models\Transfer;
use Sunrice\CommerceRental\Models\Unit;
use Sunrice\CommerceRental\RentalException;
use Sunrice\CommerceRental\Wording;

beforeEach(function () {
    $this->variant = rentalStore(jakartaUnits: 1);
    $this->unit = Unit::query()->firstOrFail();
});

it('lists, creates and edits units at the locations', function () {
    actingAsSuperAdmin();

    $this->get('/admin/locations/locations')->assertOk();
    $this->get('/admin/rental/units')->assertOk();
    $this->get('/admin/rental/blocks')->assertOk();
    $this->get("/admin/rental/units/{$this->unit->id}/edit")->assertOk();

    $this->post('/admin/locations/locations', ['name' => 'Yogyakarta', 'code' => 'YOG', 'whatsapp' => '6281100000003', 'pickup' => true, 'ships_from' => true, 'active' => true, 'sort_order' => 3])
        ->assertSessionHasNoErrors();
    $yog = location('YOG');
    $this->post('/admin/rental/units', ['code' => 'YOG-GM-0001', 'variant' => $this->variant->id, 'location' => $yog->id, 'condition' => 'good', 'active' => true])
        ->assertSessionHasNoErrors();
    expect(Unit::query()->where('code', 'YOG-GM-0001')->value('location_id'))->toBe($yog->id);

    $this->post('/admin/rental/units', ['code' => 'YOG-GM-0001', 'variant' => $this->variant->id, 'location' => $yog->id, 'condition' => 'good'])
        ->assertSessionHasErrors('code');
});

it('moves a unit to another location and keeps the history', function () {
    $user = actingAsSuperAdmin();

    $this->post("/admin/rental/units/{$this->unit->id}/actions/move", ['location' => location('BDG')->id, 'note' => 'Lebih laku di Bandung'])
        ->assertSessionHasNoErrors();

    expect($this->unit->fresh()->location_id)->toBe(location('BDG')->id)
        ->and(Transfer::query()->sole()->only(['from_location_id', 'to_location_id', 'user_id', 'note']))
        ->toBe(['from_location_id' => location('JKT')->id, 'to_location_id' => location('BDG')->id, 'user_id' => $user->id, 'note' => 'Lebih laku di Bandung'])
        ->and(app(Availability::class)->count($this->variant, period(5, location: 'BDG')))->toBe(1)
        ->and(app(Availability::class)->count($this->variant, period(5)))->toBe(0);
});

it('refuses to move a unit with customer bookings to come', function () {
    placeRental(rentalCart($this->variant, period(5)));

    expect(fn () => app(MoveUnit::class)->handle($this->unit, location('BDG')))->toThrow(RentalException::class)
        ->and($this->unit->fresh()->location_id)->toBe(location('JKT')->id);
});

it('blocks dates so customers cannot book them', function () {
    actingAsSuperAdmin();
    $days = period(5, days: 2);

    $this->post('/admin/rental/blocks', ['unit' => $this->unit->id, 'starts_on' => $days->start->toDateString(), 'ends_on' => $days->end->toDateString(), 'note' => 'Foto katalog'])
        ->assertSessionHasNoErrors();

    $block = Booking::query()->where('kind', 'block')->sole();
    expect($block->available_from->toDateString())->toBe($days->end->addDay()->toDateString())
        ->and(app(Availability::class)->count($this->variant, period(6)))->toBe(0)
        ->and(app(Availability::class)->count($this->variant, period(7)))->toBe(1); // the day after, no rest days

    submitCheckout(rentalCart($this->variant, period(5)))->assertSessionHas('commerce.error');

    // Deleting the block frees the dates.
    $this->delete("/admin/rental/blocks/{$block->id}")->assertSessionHasNoErrors();
    expect(app(Availability::class)->count($this->variant, period(5)))->toBe(1);
});

it('refuses a block over a customer booking', function () {
    placeRental(rentalCart($this->variant, period(5)));
    actingAsSuperAdmin();

    $this->post('/admin/rental/blocks', ['unit' => $this->unit->id, 'starts_on' => period(6)->start->toDateString(), 'ends_on' => period(6)->start->toDateString()])
        ->assertSessionHasErrors('starts_on');
    expect(Booking::query()->where('kind', 'block')->count())->toBe(0);
});

it('refuses to delete a location with rental units', function () {
    actingAsSuperAdmin();

    $this->delete('/admin/locations/locations/'.location('JKT')->id)
        ->assertSessionHas('error', 'The location still has rental units. Move or delete them first.');

    expect(location('JKT'))->not->toBeNull();
});

it('calls the locations what the store calls them', function () {
    config(['commerce-rental.location_term' => ['id' => 'cabang', 'en' => 'branch']]);
    app()->setLocale('id');

    expect(Wording::location())->toBe('cabang')
        ->and(Wording::label())->toBe('Branch')
        ->and(Wording::trans('commerce-rental::rental.line_one_day', ['from' => '1 Des', 'name' => 'Jakarta']))->toBe('1 Des (1 hari) · Cabang Jakarta');

    config(['commerce-rental.location_term' => null]);
    expect(Wording::trans('commerce-rental::rental.line_one_day', ['from' => '1 Des', 'name' => 'Jakarta']))->toBe('1 Des (1 hari) · Lokasi Jakarta');
});

it('draws the calendar of a location with bookings, blocks and rest days', function () {
    $order = placeRental(rentalCart($this->variant, period(1, days: 2)));
    actingAsSuperAdmin();

    $this->get('/admin/rental/calendar?days=7')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Rental/Calendar', false)
        ->where('filters.branch', location('JKT')->id)
        ->has('dates', 7)
        ->where('units.0.code', 'JKT-GM-0001')
        ->where('units.0.bookings.0.kind', 'order')
        ->where('units.0.bookings.0.start', period(1)->start->toDateString())
        ->where('units.0.bookings.0.free', period(1, days: 2)->end->addDays(3)->toDateString())
        ->where('units.0.bookings.0.stage', 'to_pick_up'));

    $this->get('/admin/rental/calendar?search=NOPE')->assertInertia(fn ($page) => $page->where('total', 0));
});
