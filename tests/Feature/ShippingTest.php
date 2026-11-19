<?php

declare(strict_types=1);

use Sunrice\Commerce\Cart\CartView;
use Sunrice\Commerce\Checkout\CheckoutData;
use Sunrice\Commerce\Settings\StoreSettings;
use Sunrice\Commerce\Shipping\ShippingRequest;
use Sunrice\CommerceRental\Models\Unit;

beforeEach(function () {
    $this->variant = rentalStore();
    branch('JKT')->forceFill(['address' => 'Jl. Senopati 10', 'postal_code' => '12190', 'latitude' => -6.2297, 'longitude' => 106.8090, 'phone' => '0217000001'])->save();
});

it('quotes deliveries from the branch that has the dress', function () {
    app(StoreSettings::class)->saveSection('general', ['name' => 'UPLOVE']);
    $cart = rentalCart($this->variant, period(5));
    $state = CartView::for($cart, CheckoutData::fromCart($cart)->merge(checkoutInput())->context())->state;

    $origin = ShippingRequest::fromState($state)->origin;
    expect($origin->get('postal_code'))->toBe('12190')
        ->and($origin->get('name'))->toBe('UPLOVE Jakarta')
        ->and($origin->get('phone'))->toBe('0217000001')
        ->and((float) $origin->get('latitude'))->toBe(-6.2297);
});

it('keeps the branch on a delivered order, for the courier booking', function () {
    $order = placeRental(rentalCart($this->variant, period(5)))->fresh();

    expect($order->shippingOrigin()->get('line1'))->toBe('Jl. Senopati 10')
        ->and($order->shippingOrigin()->get('postal_code'))->toBe('12190');
});

it('refuses to deliver dresses from two branches in one order', function () {
    Unit::query()->create(['variant_id' => $this->variant->id, 'branch_id' => branch('BDG')->id, 'code' => 'BDG-GM-0001']);
    $cart = rentalCart($this->variant, period(5));
    $cart->items()->create(['variant_id' => $this->variant->id, 'quantity' => 1, 'meta' => period(5, branch: 'BDG')->toMeta()]);

    submitCheckout($cart)->assertSessionHas('commerce.error', __('commerce-rental::rental.errors.one_branch'));
});
