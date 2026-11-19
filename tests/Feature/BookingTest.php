<?php

declare(strict_types=1);

use Sunrice\Commerce\Orders\Actor;
use Sunrice\Commerce\Orders\Models\Order;
use Sunrice\Commerce\Orders\OrderStateMachine;
use Sunrice\Commerce\Orders\OrderStatus;
use Sunrice\CommerceRental\Availability;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Models\Unit;
use Sunrice\CommerceRental\Wording;

it('prices a rental as the daily price times the days', function () {
    $variant = rentalStore();
    $order = placeRental(rentalCart($variant, period(5, days: 3)));

    $item = $order->items()->firstOrFail();
    expect($item->unit_price)->toBe(450000)
        ->and($item->meta['price'])->toMatchArray(['daily' => 150000, 'days' => 3])
        ->and($order->subtotal)->toBe(450000)
        ->and($order->total)->toBe(470000);
});

it('books a free unit when the order is placed', function () {
    $variant = rentalStore();
    $order = placeRental(rentalCart($variant, period(5, days: 3)));

    $booking = Booking::query()->sole();
    expect($booking->order_id)->toBe($order->id)
        ->and($booking->order_item_id)->toBe($order->items()->value('id'))
        ->and($booking->unit->code)->toBe('JKT-GM-0001')
        ->and($booking->starts_on->toDateString())->toBe(now()->addDays(5)->toDateString())
        ->and($booking->ends_on->toDateString())->toBe(now()->addDays(7)->toDateString())
        // Returned on day 7, two rest days (8, 9): free again on day 10.
        ->and($booking->available_from->toDateString())->toBe(now()->addDays(10)->toDateString())
        ->and($booking->status)->toBe(Booking::ACTIVE);
});

it('never books the same unit twice: the next order gets the other unit, the one after is refused', function () {
    $variant = rentalStore(jakartaUnits: 2);
    placeRental(rentalCart($variant, period(5)));
    placeRental(rentalCart($variant, period(6)));

    expect(Booking::query()->orderBy('id')->get()->map(fn ($b) => $b->unit->code)->all())->toBe(['JKT-GM-0001', 'JKT-GM-0002']);

    submitCheckout(rentalCart($variant, period(7)))->assertSessionHas('commerce.error', Wording::trans('commerce-rental::rental.errors.unavailable'));
    expect(Order::query()->count())->toBe(2)
        ->and(Booking::query()->count())->toBe(2);
});

it('keeps the rest days after a return', function () {
    $variant = rentalStore(jakartaUnits: 1);
    placeRental(rentalCart($variant, period(5, days: 3)));   // days 5–7, free from day 10

    $availability = app(Availability::class);
    expect($availability->count($variant, period(8, days: 1)))->toBe(0)     // a rest day
        ->and($availability->count($variant, period(9, days: 1)))->toBe(0)  // a rest day
        ->and($availability->count($variant, period(10, days: 2)))->toBe(1)
        // Ending on day 2 needs rest days 3 and 4: free, the booking starts on day 5.
        ->and($availability->count($variant, period(1, days: 2)))->toBe(1)
        // Ending on day 3 would rest on 4 and 5, clashing with the booking.
        ->and($availability->count($variant, period(1, days: 3)))->toBe(0);
});

it('uses the buffer of the product over the default', function () {
    $variant = rentalStore(jakartaUnits: 1);
    DB::table('commerce_rental_buffers')->insert(['scope' => 'product', 'scope_id' => $variant->product_id, 'days' => 0]);
    placeRental(rentalCart($variant, period(5, days: 3)));

    expect(app(Availability::class)->count($variant, period(8, days: 1)))->toBe(1);
});

it('counts units per location', function () {
    $variant = rentalStore();

    expect(app(Availability::class)->count($variant, period(5, location: 'JKT')))->toBe(2)
        ->and(app(Availability::class)->count($variant, period(5, location: 'BDG')))->toBe(0);
    submitCheckout(rentalCart($variant, period(5, location: 'BDG')))->assertSessionHas('commerce.error', Wording::trans('commerce-rental::rental.errors.unavailable'));
});

it('refuses a rental line without dates', function () {
    $variant = rentalStore();
    submitCheckout(rentalCart($variant, null))->assertSessionHas('commerce.error', Wording::trans('commerce-rental::rental.errors.dates_missing'));

    expect(Order::query()->count())->toBe(0);
});

it('refuses a past start', function () {
    $variant = rentalStore();
    submitCheckout(rentalCart($variant, period(-1)))->assertSessionHas('commerce.error', __('commerce-rental::rental.errors.past'));

    expect(Booking::query()->count())->toBe(0);
});

it('frees the unit when the order is cancelled', function () {
    $variant = rentalStore(jakartaUnits: 1);
    $order = placeRental(rentalCart($variant, period(5)));

    app(OrderStateMachine::class)->transition($order, OrderStatus::CANCELLED, Actor::system(), 'customer');

    expect(Booking::query()->sole()->status)->toBe(Booking::RELEASED)
        ->and(app(Availability::class)->count($variant, period(5)))->toBe(1);
});

it('frees the unit when the order expires unpaid', function () {
    $variant = rentalStore(jakartaUnits: 1);
    placeRental(rentalCart($variant, period(5)));

    $this->travel(3)->hours();   // the transfer deadline is 2 hours
    $this->artisan('commerce:tick')->assertSuccessful();

    expect(Booking::query()->sole()->status)->toBe(Booking::RELEASED)
        ->and(app(Availability::class)->count($variant, period(5)))->toBe(1);
});

it('skips inactive units', function () {
    $variant = rentalStore(jakartaUnits: 1);
    Unit::query()->update(['active' => false]);

    expect(app(Availability::class)->count($variant, period(5)))->toBe(0);
});

it('books the same dress twice in one cart, on different dates', function () {
    $variant = rentalStore(jakartaUnits: 1);
    $cart = rentalCart($variant, period(5));
    $cart->items()->create(['variant_id' => $variant->id, 'quantity' => 1, 'meta' => period(20)->toMeta()]);

    $order = placeRental($cart);

    $bookings = Booking::query()->where('order_id', $order->id)->orderBy('starts_on')->get();
    expect($bookings)->toHaveCount(2)
        ->and($bookings->pluck('unit_id')->unique()->all())->toHaveCount(1)
        ->and($bookings->pluck('order_item_id')->unique()->all())->toHaveCount(2)
        ->and($order->subtotal)->toBe(900000);
});
