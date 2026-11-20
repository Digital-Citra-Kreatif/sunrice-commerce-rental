<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Commerce\Cart\CartView;
use Sunrice\Commerce\Cart\Models\Cart;
use Sunrice\Commerce\Catalog\Actions\ScaffoldStore;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\Commerce\Checkout\CheckoutData;
use Sunrice\Commerce\Commerce;
use Sunrice\Commerce\Orders\Models\Order;
use Sunrice\Commerce\Regions\RegionImporter;
use Sunrice\Commerce\Settings\StoreSettings;
use Sunrice\Commerce\Shipping\Models\ShippingMethod;
use Sunrice\Commerce\Shipping\Models\ShippingZone;
use Sunrice\CommerceMultiLocations\Models\Location;
use Sunrice\CommerceRental\Models\Unit;
use Sunrice\CommerceRental\RentalPeriod;
use Sunrice\CommerceRental\Tests\TestCase;
use Workbench\App\Commerce\TestTransfer;
use Workbench\App\Models\User;

uses(TestCase::class)->in('Feature', 'Unit');
// tests/Concurrency picks its own MySQL test case.

/**
 * A rental store: a "Gaun Merah" (size S, 150.000 a day) with two units in
 * Jakarta (JKT-GM-0001, -0002) and none in Bandung, a flat-rate courier and
 * a test transfer. Returns the variant.
 */
function rentalStore(int $jakartaUnits = 2): Variant
{
    (new RegionImporter(__DIR__.'/Fixtures/regions'))->import(4);
    app(ScaffoldStore::class)->handle();
    publishDress('Gaun Merah', [['id' => 'gm-s', 'sku' => 'GM-S', 'price' => 150000, 'weight' => 800, 'track_stock' => false]]);

    $zone = ShippingZone::query()->create(['name' => 'Indonesia']);
    $zone->regions()->create(['country' => 'ID']);
    $zone->methods()->create(['type' => 'flat', 'title' => ['id' => 'Kurir toko', 'en' => 'Store courier'], 'settings' => ['cost' => 20000], 'active' => true]);
    app(Commerce::class)->registerPaymentMethod(TestTransfer::class);
    app(StoreSettings::class)->saveSection('payments', ['methods' => ['test_transfer' => ['active' => true, 'title' => ['id' => 'Transfer', 'en' => 'Transfer'], 'deadline_hours' => 2]]]);
    app()->forgetScopedInstances();

    // These tests price per day with no shortest rental; PricingTest covers the store's defaults.
    app(StoreSettings::class)->saveSection('rental', ['pricing' => 'daily', 'min_days' => 1]);
    app()->forgetScopedInstances();

    $variant = Variant::query()->where('sku', 'GM-S')->firstOrFail();
    $jakarta = Location::query()->create(['code' => 'JKT', 'name' => 'Jakarta', 'whatsapp' => '6281100000001', 'pickup' => true]);
    Location::query()->create(['code' => 'BDG', 'name' => 'Bandung', 'whatsapp' => '6281100000002', 'pickup' => true, 'sort_order' => 1]);
    app()->forgetScopedInstances();
    for ($i = 1; $i <= $jakartaUnits; $i++) {
        Unit::query()->create(['variant_id' => $variant->id, 'location_id' => $jakarta->id, 'code' => sprintf('JKT-GM-%04d', $i)]);
    }

    return $variant;
}

/** Publishes a product entry with these variants, through Sunrice's own actions. */
function publishDress(string $title, array $variants): void
{
    $collection = Collection::query()->where('handle', 'products')->first();
    if ($collection === null) {
        $definition = collect((require __DIR__.'/../vendor/sunrice/commerce/resources/structure/commerce.php')['blueprints'])->firstWhere('handle', 'product');
        Blueprint::query()->firstOrCreate(['handle' => 'product'], ['title' => 'Product', 'fields' => $definition['fields']]);
        $collection = Collection::query()->create(['handle' => 'products', 'title' => 'Products', 'blueprint' => 'product', 'route' => '/products/{slug}']);
    }
    $translation = app(CreateEntry::class)->handle($collection, ['title' => $title, 'slug' => Str::slug($title), 'data' => ['product' => ['variants' => $variants]]]);
    app(PublishEntry::class)->handle($translation->fresh());
}

function location(string $code): Location
{
    return Location::query()->where('code', $code)->firstOrFail();
}

/** A rental period starting $inDays from today for $days days, at a location. */
function period(int $inDays, int $days = 3, string $location = 'JKT'): RentalPeriod
{
    $start = now()->startOfDay()->addDays($inDays);

    return RentalPeriod::make($start->toDateString(), $start->copy()->addDays($days - 1)->toDateString(), location($location)->id);
}

/** A guest cart with one rental line, sent as the visitor's cookie. */
function rentalCart(Variant $variant, ?RentalPeriod $period, int $quantity = 1): Cart
{
    $cart = Cart::query()->create(['token' => Str::random(64), 'status' => 'active', 'currency' => 'IDR', 'last_activity_at' => now()]);
    $cart->items()->create(['variant_id' => $variant->id, 'quantity' => $quantity, 'meta' => $period?->toMeta()]);
    test()->withCookie((string) config('commerce.cart.cookie', 'commerce_cart'), $cart->token);

    return $cart;
}

/** @return array<string, mixed> */
function checkoutInput(array $overrides = []): array
{
    $method = ShippingMethod::query()->where('type', 'flat')->value('id');

    return array_replace_recursive([
        'name' => 'Rina', 'email' => 'rina@example.com', 'phone' => '0812 3456 7890',
        'shipping_address' => ['name' => 'Rina', 'phone' => '0812 3456 7890', 'country' => 'ID', 'province_code' => '32', 'city_code' => '32.73',
            'district_code' => '32.73.01', 'line1' => 'Jl. Sarijadi 1'],
        'billing_same' => '1',
        'shipping_rate' => "flat:{$method}:default",
        'payment_method' => 'test_transfer',
    ], $overrides);
}

/** POSTs /checkout; returns the response (errors are in the session). */
function submitCheckout(Cart $cart, array $input = []): TestResponse
{
    app()->forgetScopedInstances();
    $input = checkoutInput($input);
    $hash = CartView::for($cart->fresh(), CheckoutData::fromCart($cart->fresh())->merge($input)->context())->state->hash();

    return test()->post('/checkout', [...$input, 'action' => 'place', 'totals_hash' => $hash]);
}

function placeRental(Cart $cart, array $input = []): Order
{
    submitCheckout($cart, $input)->assertSessionHasNoErrors();

    return Order::query()->latest('id')->firstOrFail();
}

function actingAsSuperAdmin(): User
{
    $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password')]);
    $user->assignRole(Role::findOrCreate((string) config('sunrice-admin.super_admin_role', 'super-admin'), 'web'));
    test()->actingAs($user, 'web');

    return $user;
}
