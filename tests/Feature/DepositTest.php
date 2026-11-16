<?php

declare(strict_types=1);

use Sunrice\Commerce\Orders\OrderStatus;
use Sunrice\Commerce\Payments\Actions\RecordPaymentResult;
use Sunrice\Commerce\Payments\PaymentResult;
use Sunrice\Commerce\Settings\StoreSettings;
use Sunrice\CommerceRental\RentalSettings;

function rentalSettings(array $values): void
{
    app(StoreSettings::class)->saveSection('rental', $values);
    app()->forgetScopedInstances();
}

it('asks a percentage deposit, the balance due days before pickup', function () {
    $variant = rentalStore();
    rentalSettings(['deposit_mode' => 'percent', 'deposit_percent' => 50, 'balance_days_before' => 3]);
    $start = period(10);

    $order = placeRental(rentalCart($variant, $start))->fresh();

    expect($order->total)->toBe(470000)
        ->and($order->deposit_total)->toBe(235000)
        ->and($order->payments->sole()->amount)->toBe(235000)
        ->and($order->balance_due_at->toDateString())->toBe($start->start->subDays(3)->toDateString());

    app(RecordPaymentResult::class)->handle($order->payments->sole(), PaymentResult::paid(235000));
    expect($order->fresh()->payment_status)->toBe(OrderStatus::PARTIALLY_PAID)
        ->and($order->fresh()->balanceDue())->toBe(235000);
});

it('asks a fixed deposit per dress', function () {
    $variant = rentalStore();
    rentalSettings(['deposit_mode' => 'fixed', 'deposit_fixed' => 100000, 'balance_days_before' => 3]);
    $cart = rentalCart($variant, period(10), quantity: 2);

    expect(placeRental($cart)->fresh()->deposit_total)->toBe(200000);
});

it('is paid in full when the pickup is too close for a deposit', function () {
    $variant = rentalStore();
    rentalSettings(['deposit_mode' => 'percent', 'deposit_percent' => 50, 'balance_days_before' => 3]);

    $order = placeRental(rentalCart($variant, period(2)))->fresh();

    expect($order->deposit_total)->toBe(0)->and($order->payments->sole()->amount)->toBe(470000);
});

it('has no deposit by default', function () {
    $variant = rentalStore();

    expect(placeRental(rentalCart($variant, period(10)))->fresh()->deposit_total)->toBe(0);
});

it('applies the booking limits from the settings', function () {
    $variant = rentalStore();
    rentalSettings(['min_days' => 2, 'max_days' => 5, 'min_days_ahead' => 3]);

    submitCheckout(rentalCart($variant, period(5, days: 1)))->assertSessionHas('commerce.error', __('commerce-rental::rental.errors.too_short', ['days' => 2]));
    submitCheckout(rentalCart($variant, period(5, days: 6)))->assertSessionHas('commerce.error', __('commerce-rental::rental.errors.too_long', ['days' => 5]));
    submitCheckout(rentalCart($variant, period(1, days: 3)))->assertSessionHas('commerce.error', __('commerce-rental::rental.errors.too_soon', ['days' => 3]));
    placeRental(rentalCart($variant, period(5, days: 3)));
});

it('takes the default rest days from the settings', function () {
    $variant = rentalStore(jakartaUnits: 1);
    rentalSettings(['buffer_days' => 0]);
    placeRental(rentalCart($variant, period(5, days: 3))); // days 5–7, free again on day 8

    placeRental(rentalCart($variant, period(8, days: 2)));
});

it('shows the rentals section in the store settings', function () {
    rentalStore();
    actingAsSuperAdmin();

    $this->get('/admin/commerce/settings/rental')->assertOk();
    $this->put('/admin/commerce/settings/rental', ['values' => ['buffer_days' => 3, 'min_days' => 1, 'max_days' => 14, 'min_days_ahead' => 1, 'max_days_ahead' => 180,
        'deposit_mode' => 'percent', 'deposit_percent' => 30, 'balance_days_before' => 5]])->assertSessionHasNoErrors();
    app()->forgetScopedInstances();
    expect(app(RentalSettings::class)->int('deposit_percent'))->toBe(30)->and(app(RentalSettings::class)->int('max_days'))->toBe(14);
});
