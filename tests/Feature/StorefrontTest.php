<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Sunrice\Commerce\Cart\Models\Cart;
use Sunrice\Commerce\Cart\Models\CartItem;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\Commerce\Catalog\ProductQuery;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Models\Unit;

beforeEach(function () {
    $this->variant = rentalStore(jakartaUnits: 1);
    $this->jkt = branch('JKT');
});

function addRental(array $input)
{
    return test()->from('/products/gaun-merah')->post('/rental/cart', $input);
}

it('shows the booking form on a rental product page', function () {
    $this->get('/products/gaun-merah')->assertOk()
        ->assertSee('data-commerce-rental', false)
        ->assertSee(__('commerce-rental::rental.form.title'))
        ->assertSee('Jakarta')
        ->assertDontSee('<option value="'.branch('BDG')->id.'"', false); // Bandung has no unit of it
});

it('renders the booking form as a component for themes', function () {
    $html = Blade::render('<x-commerce-rental::booking :entry="$entry" />', ['entry' => $this->variant->product->entry()]);

    expect($html)->toContain('data-commerce-rental')->toContain('name="start"');
});

it('adds a rental to the cart with its dates and branch, shown under the line', function () {
    $p = period(5);

    addRental(['variant' => $this->variant->id, 'branch' => $this->jkt->id, 'start' => $p->start->toDateString(), 'end' => $p->end->toDateString()])
        ->assertRedirect('/products/gaun-merah')->assertSessionHas('commerce.success');

    $item = CartItem::query()->sole();
    expect($item->meta)->toBe($p->toMeta());

    $cart = Cart::query()->sole();
    $this->withCookie('commerce_cart', $cart->token)->get('/cart')->assertOk()
        ->assertSee('Cabang Jakarta')->assertSee('3 hari')->assertSee('450.000');
});

it('puts the same dress on other dates on its own line', function () {
    foreach ([period(5), period(20)] as $p) {
        addRental(['variant' => $this->variant->id, 'branch' => $this->jkt->id, 'start' => $p->start->toDateString(), 'end' => $p->end->toDateString()]);
        $this->withCookie('commerce_cart', Cart::query()->value('token'));
    }

    expect(CartItem::query()->count())->toBe(2);
});

it('refuses dates that are taken or out of the limits', function () {
    $taken = period(5);
    Booking::query()->create(['unit_id' => Unit::query()->value('id'), 'kind' => Booking::BLOCK, 'starts_on' => $taken->start, 'ends_on' => $taken->end, 'status' => Booking::ACTIVE]);

    addRental(['variant' => $this->variant->id, 'branch' => $this->jkt->id, 'start' => $taken->start->toDateString(), 'end' => $taken->end->toDateString()])
        ->assertSessionHas('commerce.error', __('commerce-rental::rental.errors.unavailable'));
    addRental(['variant' => $this->variant->id, 'branch' => $this->jkt->id, 'start' => now()->subDay()->toDateString(), 'end' => now()->toDateString()])
        ->assertSessionHas('commerce.error', __('commerce-rental::rental.errors.past'));
    addRental(['variant' => $this->variant->id, 'branch' => branch('BDG')->id, 'start' => period(30)->start->toDateString(), 'end' => period(30)->end->toDateString()])
        ->assertSessionHas('commerce.error', __('commerce-rental::rental.errors.unavailable'));

    expect(CartItem::query()->count())->toBe(0);
});

it('answers what is free at each branch, and on each day of a month', function () {
    $p = period(5);
    $this->getJson("/rental/availability?variant={$this->variant->id}&start={$p->start->toDateString()}&end={$p->end->toDateString()}")
        ->assertOk()->assertJson(['days' => 3, 'price' => 450000, 'message' => null, 'branches' => [['id' => $this->jkt->id, 'name' => 'Jakarta', 'available' => 1]]]);

    placeRental(rentalCart($this->variant, $p));
    $this->getJson("/rental/availability?variant={$this->variant->id}&start={$p->start->toDateString()}&end={$p->end->toDateString()}")
        ->assertJson(['branches' => [['available' => 0]]]);

    $month = $p->start->format('Y-m');
    $days = $this->getJson("/rental/availability?variant={$this->variant->id}&branch={$this->jkt->id}&month={$month}")->assertOk()->json('days');
    expect($days[$p->start->toDateString()])->toBe(0);
    // Free again after the return and the 2 rest days.
    $free = $p->end->addDays(3)->toDateString();
    if (isset($days[$free])) {
        expect($days[$free])->toBe(1);
    }
});

it('shows the rental on the order page', function () {
    $order = placeRental(rentalCart($this->variant, period(5)));

    $this->get($order->url())->assertOk()->assertSee('Cabang Jakarta')
        ->assertSee('https://wa.me/6281100000001', false)->assertSee(__('commerce-rental::rental.order.whatsapp'));
    expect($order->items->sole()->details()[0])->toContain('3 hari');
});

it('filters the catalog to dresses free on some days, at a branch', function () {
    publishDress('Gaun Biru', [['id' => 'gb-s', 'sku' => 'GB-S', 'price' => 100000, 'track_stock' => false]]);
    $blue = Variant::query()->where('sku', 'GB-S')->firstOrFail();
    Unit::query()->create(['variant_id' => $blue->id, 'branch_id' => branch('BDG')->id, 'code' => 'BDG-GB-0001']);
    placeRental(rentalCart($this->variant, period(5)));
    $count = fn (array $q) => ProductQuery::fromRequest(Request::create('/products', 'GET', $q))->count();
    $p = period(5);

    expect($count([]))->toBe(2)
        ->and($count(['rent_from' => $p->start->toDateString(), 'rent_to' => $p->end->toDateString()]))->toBe(1) // the red one is taken
        ->and($count(['rent_from' => period(30)->start->toDateString(), 'rent_to' => period(30)->end->toDateString()]))->toBe(2)
        ->and($count(['rent_from' => period(30)->start->toDateString(), 'rent_to' => period(30)->end->toDateString(), 'rent_branch' => $this->jkt->id]))->toBe(1)
        ->and($count(['rent_from' => 'nonsense']))->toBe(2);
});
