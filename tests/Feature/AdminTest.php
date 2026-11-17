<?php

declare(strict_types=1);

use Sunrice\CommerceRental\Actions\MoveUnit;
use Sunrice\CommerceRental\Availability;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Models\Transfer;
use Sunrice\CommerceRental\Models\Unit;
use Sunrice\CommerceRental\RentalException;

beforeEach(function () {
    $this->variant = rentalStore(jakartaUnits: 1);
    $this->unit = Unit::query()->firstOrFail();
});

it('lists, creates and edits branches and units', function () {
    actingAsSuperAdmin();

    $this->get('/admin/rental/branches')->assertOk();
    $this->get('/admin/rental/units')->assertOk();
    $this->get('/admin/rental/blocks')->assertOk();
    $this->get("/admin/rental/units/{$this->unit->id}/edit")->assertOk();

    $this->post('/admin/rental/branches', ['name' => 'Yogyakarta', 'code' => 'YOG', 'whatsapp' => '6281100000003', 'active' => true, 'sort_order' => 3])
        ->assertSessionHasNoErrors();
    $yog = branch('YOG');
    $this->post('/admin/rental/units', ['code' => 'YOG-GM-0001', 'variant' => $this->variant->id, 'branch' => $yog->id, 'condition' => 'good', 'active' => true])
        ->assertSessionHasNoErrors();
    expect(Unit::query()->where('code', 'YOG-GM-0001')->value('branch_id'))->toBe($yog->id);

    $this->post('/admin/rental/units', ['code' => 'YOG-GM-0001', 'variant' => $this->variant->id, 'branch' => $yog->id, 'condition' => 'good'])
        ->assertSessionHasErrors('code');
});

it('moves a unit to another branch and keeps the history', function () {
    $user = actingAsSuperAdmin();

    $this->post("/admin/rental/units/{$this->unit->id}/actions/move", ['branch' => branch('BDG')->id, 'note' => 'Lebih laku di Bandung'])
        ->assertSessionHasNoErrors();

    expect($this->unit->fresh()->branch_id)->toBe(branch('BDG')->id)
        ->and(Transfer::query()->sole()->only(['from_branch_id', 'to_branch_id', 'user_id', 'note']))
        ->toBe(['from_branch_id' => branch('JKT')->id, 'to_branch_id' => branch('BDG')->id, 'user_id' => $user->id, 'note' => 'Lebih laku di Bandung'])
        ->and(app(Availability::class)->count($this->variant, period(5, branch: 'BDG')))->toBe(1)
        ->and(app(Availability::class)->count($this->variant, period(5)))->toBe(0);
});

it('refuses to move a unit with customer bookings to come', function () {
    placeRental(rentalCart($this->variant, period(5)));

    expect(fn () => app(MoveUnit::class)->handle($this->unit, branch('BDG')))->toThrow(RentalException::class)
        ->and($this->unit->fresh()->branch_id)->toBe(branch('JKT')->id);
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

it('refuses to delete a branch with units', function () {
    actingAsSuperAdmin();

    $this->delete('/admin/rental/branches/'.branch('JKT')->id);

    expect(branch('JKT'))->not->toBeNull();
});

it('draws the calendar of a branch with bookings, blocks and rest days', function () {
    $order = placeRental(rentalCart($this->variant, period(1, days: 2)));
    actingAsSuperAdmin();

    $this->get('/admin/rental/calendar?days=7')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Rental/Calendar', false)
        ->where('filters.branch', branch('JKT')->id)
        ->has('dates', 7)
        ->where('units.0.code', 'JKT-GM-0001')
        ->where('units.0.bookings.0.kind', 'order')
        ->where('units.0.bookings.0.start', period(1)->start->toDateString())
        ->where('units.0.bookings.0.free', period(1, days: 2)->end->addDays(3)->toDateString())
        ->where('units.0.bookings.0.stage', 'to_pick_up'));

    $this->get('/admin/rental/calendar?search=NOPE')->assertInertia(fn ($page) => $page->where('total', 0));
});
