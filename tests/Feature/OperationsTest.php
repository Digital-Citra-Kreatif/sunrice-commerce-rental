<?php

declare(strict_types=1);

use Sunrice\Commerce\Orders\Actor;
use Sunrice\Commerce\Payments\Actions\RecordPaymentResult;
use Sunrice\Commerce\Payments\PaymentResult;
use Sunrice\CommerceRental\Actions\ChangeDates;
use Sunrice\CommerceRental\Actions\RecordPickup;
use Sunrice\CommerceRental\Actions\RecordReturn;
use Sunrice\CommerceRental\Availability;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Models\Unit;
use Sunrice\CommerceRental\RentalException;

beforeEach(function () {
    $this->variant = rentalStore(jakartaUnits: 2);
});

function paidRental($variant, $period)
{
    $order = placeRental(rentalCart($variant, $period));
    app(RecordPaymentResult::class)->handle($order->payments->sole(), PaymentResult::paid($order->total));

    return Booking::query()->where('order_id', $order->id)->sole();
}

it('hands a paid rental over and takes it back', function () {
    $booking = paidRental($this->variant, period(0));

    app(RecordPickup::class)->handle($booking, Actor::system());
    expect($booking->fresh()->stage())->toBe('out');

    $clashes = app(RecordReturn::class)->handle($booking->fresh(), Actor::system());
    expect($clashes)->toBe([])->and($booking->fresh()->stage())->toBe('returned')
        ->and($booking->order->events()->pluck('message')->implode(' '))->toContain('diambil')->toContain('dikembalikan');
});

it('refuses to hand over an unpaid rental', function () {
    $order = placeRental(rentalCart($this->variant, period(0)));
    $booking = Booking::query()->where('order_id', $order->id)->sole();

    expect(fn () => app(RecordPickup::class)->handle($booking, Actor::system()))->toThrow(RentalException::class);
});

it('starts the rest days from a late return and says what that now overlaps', function () {
    $booking = paidRental($this->variant, period(0, days: 2)); // today and tomorrow
    app(RecordPickup::class)->handle($booking, Actor::system());
    $next = Booking::query()->create(['unit_id' => $booking->unit_id, 'kind' => Booking::BLOCK, 'starts_on' => period(6)->start, 'ends_on' => period(6)->end, 'status' => Booking::ACTIVE]);

    $this->travel(4)->days(); // two days late
    $clashes = app(RecordReturn::class)->handle($booking->fresh(), Actor::system());

    expect($booking->fresh()->available_from->toDateString())->toBe(now()->addDays(3)->toDateString()) // today + 1 + 2 rest days
        ->and($booking->fresh()->stage())->toBe('returned')
        ->and(array_map(fn ($b) => $b->id, $clashes))->toBe([$next->id]);
});

it('takes a unit in repair out of bookings', function () {
    $booking = paidRental($this->variant, period(0));
    app(RecordPickup::class)->handle($booking, Actor::system());

    app(RecordReturn::class)->handle($booking->fresh(), Actor::system(), 'repair', 'Resleting rusak');

    expect(Unit::query()->find($booking->unit_id)->condition)->toBe('repair')
        ->and(app(Availability::class)->count($this->variant, period(10)))->toBe(1);
});

it('moves a booking to other days, on another unit when its own is taken', function () {
    $booking = paidRental($this->variant, period(5));
    $other = Unit::query()->whereKeyNot($booking->unit_id)->value('id');
    Booking::query()->create(['unit_id' => $booking->unit_id, 'kind' => Booking::BLOCK, 'starts_on' => period(20)->start, 'ends_on' => period(20)->end, 'status' => Booking::ACTIVE]);

    app(ChangeDates::class)->handle($booking->fresh(), period(10), Actor::system());
    expect($booking->fresh()->starts_on->toDateString())->toBe(period(10)->start->toDateString())
        ->and($booking->fresh()->unit_id)->toBe($booking->unit_id);

    app(ChangeDates::class)->handle($booking->fresh(), period(20), Actor::system());
    expect($booking->fresh()->unit_id)->toBe($other);

    Booking::query()->create(['unit_id' => $other, 'kind' => Booking::BLOCK, 'starts_on' => period(40)->start, 'ends_on' => period(40)->end, 'status' => Booking::ACTIVE]);
    Booking::query()->create(['unit_id' => $booking->unit_id, 'kind' => Booking::BLOCK, 'starts_on' => period(40)->start, 'ends_on' => period(40)->end, 'status' => Booking::ACTIVE]);
    expect(fn () => app(ChangeDates::class)->handle($booking->fresh(), period(40), Actor::system()))->toThrow(RentalException::class);
});

it('lists bookings for staff and runs the actions from the list', function () {
    $booking = paidRental($this->variant, period(0));
    actingAsSuperAdmin();

    $this->get('/admin/rental/bookings')->assertOk();
    $this->get('/admin/rental/bookings?filters[day]=pickups')->assertOk();
    $this->post("/admin/rental/bookings/{$booking->id}/actions/picked-up")->assertSessionHasNoErrors();
    $this->post("/admin/rental/bookings/{$booking->id}/actions/returned", ['condition' => 'good', 'note' => 'Bersih'])->assertSessionHasNoErrors();

    expect($booking->fresh()->return_note)->toBe('Bersih');
});
