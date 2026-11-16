<?php

declare(strict_types=1);

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Sunrice\Commerce\Notifications\StoreEmail;
use Sunrice\Commerce\Payments\Actions\RecordPaymentResult;
use Sunrice\Commerce\Payments\PaymentResult;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Reminders;

beforeEach(function () {
    Notification::fake();
    $this->variant = rentalStore();
});

it('reminds a paid order of its pickup the day before, once, then of its return', function () {
    $order = placeRental(rentalCart($this->variant, period(1, days: 3)));
    app(RecordPaymentResult::class)->handle($order->payments->sole(), PaymentResult::paid($order->total));

    $this->artisan('rental:remind')->assertSuccessful();
    $this->artisan('rental:remind')->assertSuccessful();
    $booking = Booking::query()->where('order_id', $order->id)->sole();
    expect($booking->pickup_reminded_at)->not->toBeNull()->and($booking->return_reminded_at)->toBeNull();

    $this->travel(2)->days(); // the day before the return
    $this->artisan('rental:remind');
    expect($booking->fresh()->return_reminded_at)->not->toBeNull();

    $keys = [];
    Notification::assertSentOnDemand(StoreEmail::class, function (StoreEmail $mail) use (&$keys) {
        $keys[] = $mail->key;

        return true;
    });
    expect(array_values(array_filter($keys, fn ($k) => str_starts_with($k, 'rental_'))))->toBe([Reminders::PICKUP, Reminders::RETURN]);
});

it('does not remind an unpaid order of its pickup', function () {
    $order = placeRental(rentalCart($this->variant, period(1)));

    $this->artisan('rental:remind');

    expect(Booking::query()->where('order_id', $order->id)->value('pickup_reminded_at'))->toBeNull();
});

it('renders the reminder with the rental and its branch', function () {
    $order = placeRental(rentalCart($this->variant, period(1)));
    app(RecordPaymentResult::class)->handle($order->payments->sole(), PaymentResult::paid($order->total));
    $booking = Booking::query()->with('unit.branch', 'order.items')->where('order_id', $order->id)->sole();

    $html = (string) (new StoreEmail(Reminders::PICKUP, $order->fresh(), $order->customer, null, ['bookings' => [$booking]], 'id'))->toMail(new AnonymousNotifiable)->render();

    expect($html)->toContain('Gaun Merah')->toContain('Cabang Jakarta')->toContain('6281100000001')->toContain('Waktunya ambil dress');
});
