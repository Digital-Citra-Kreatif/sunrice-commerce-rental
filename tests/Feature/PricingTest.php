<?php

declare(strict_types=1);

use Sunrice\Commerce\Cart\Models\CartItem;
use Sunrice\Commerce\Settings\StoreSettings;

/** The store's defaults, as UPLOVE prices: 4 days for the product's price, 50.000 each day more, an out-of-town package of 7 days for 50.000 more. */
beforeEach(function () {
    $this->variant = rentalStore();
    app(StoreSettings::class)->saveSection('rental', ['pricing' => 'package', 'min_days' => 4, 'base_days' => 4, 'extra_day_fee' => 50000,
        'packages' => [['name' => 'Luar kota', 'days' => 7, 'fee' => 50000]]]);
    app()->forgetScopedInstances();
});

it('charges the price for the first 4 days and 50.000 for each day more', function () {
    expect(placeRental(rentalCart($this->variant, period(5, days: 4)))->subtotal)->toBe(150000)
        ->and(placeRental(rentalCart($this->variant, period(10, days: 6)))->subtotal)->toBe(250000);
});

it('refuses less than 4 days', function () {
    submitCheckout(rentalCart($this->variant, period(5, days: 3)))->assertSessionHas('commerce.error', __('commerce-rental::rental.errors.too_short', ['days' => 4]));
});

it('rents the out-of-town package for 7 days at 50.000 more, and names it under the line', function () {
    $start = period(5)->start->toDateString();

    $this->from('/products/gaun-merah')->post('/rental/cart', ['variant' => $this->variant->id, 'branch' => branch('JKT')->id, 'start' => $start, 'package' => 'luar-kota'])
        ->assertSessionHas('commerce.success');
    $item = CartItem::query()->sole();
    expect($item->meta['rental'])->toMatchArray(['start' => $start, 'end' => period(5, days: 7)->end->toDateString(), 'package' => 'luar-kota']);

    $this->withCookie((string) config('commerce.cart.cookie', 'commerce_cart'), $item->cart->token);
    $order = placeRental($item->cart);
    expect($order->subtotal)->toBe(200000)
        ->and($order->items->sole()->details()[0])->toStartWith('Luar kota · ');
});

it('shows the packages and the price on the product page and in the availability answer', function () {
    $this->get('/products/gaun-merah')->assertSee('Luar kota: 7 hari')->assertSee('Harga untuk 4 hari');

    $start = period(5)->start->toDateString();
    $this->getJson("/rental/availability?variant={$this->variant->id}&start={$start}&package=luar-kota")
        ->assertJson(['days' => 7, 'price' => 200000, 'end' => period(5, days: 7)->end->toDateString()]);
    $this->getJson("/rental/availability?variant={$this->variant->id}&start={$start}&end=".period(5, days: 5)->end->toDateString())
        ->assertJson(['days' => 5, 'price' => 200000]);
});

it('lets customers book any time ahead by default', function () {
    expect(placeRental(rentalCart($this->variant, period(700, days: 4)))->subtotal)->toBe(150000);
});
