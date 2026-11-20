<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Illuminate\Support\Facades\Route;
use Sunrice\Commerce\Catalog\Models\Product;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\Commerce\Storefront\StorefrontRoutes;
use Sunrice\Commerce\Support\MoneyFormatter;
use Sunrice\CommerceRental\Models\Unit;

/** What the booking form (commerce-rental::booking) needs for a product: its rental sizes, the locations, the limits. */
final class BookingForm
{
    /**
     * @param  list<array{id: int, title: string, price: string}>  $variants
     * @param  list<array{id: int, name: string}>  $locations
     * @param  list<array{key: string, name: string, days: int, fee: string}>  $packages
     */
    public function __construct(
        public readonly Product $product,
        public readonly array $variants,
        public readonly array $locations,
        public readonly string $minDate,
        public readonly ?string $maxDate,
        public readonly int $minDays,
        public readonly int $maxDays,
        public readonly string $action,
        public readonly string $availabilityUrl,
        public readonly array $packages = [],
        public readonly ?string $pricingNote = null,
    ) {}

    /**
     * $form->branches, as themes from before locations read it: the locations.
     *
     * @deprecated use $locations
     */
    public function __get(string $name): mixed
    {
        if ($name === 'branches') {
            return $this->locations;
        }
        trigger_error('Undefined property: '.self::class.'::$'.$name, E_USER_WARNING);

        return null;
    }

    /** The form for the product of this entry, or null when it isn't rented out. */
    public static function forEntry(int $entryId): ?self
    {
        $product = Product::query()->where('entry_id', $entryId)->first();

        return $product !== null ? self::forProduct($product) : null;
    }

    public static function forProduct(Product $product): ?self
    {
        $variants = Variant::query()->where('product_id', $product->id)->where('active', true)
            ->whereIn('id', Unit::query()->where('active', true)->select('variant_id'))->orderBy('position')->get();
        if ($variants->isEmpty() || ! Route::has('commerce-rental.cart.add')) {
            return null;
        }
        $rules = app(RentalRules::class);
        $money = app(MoneyFormatter::class);
        $locations = app(Availability::class)->locationsWith($variants->pluck('id')->map(fn ($id) => (int) $id)->all());

        return new self(
            $product,
            $variants->map(fn (Variant $v) => ['id' => $v->id, 'title' => $v->title, 'price' => $money->format($v->price)])->values()->all(),
            array_map(fn ($l) => ['id' => $l->id, 'name' => $l->name], $locations),
            $rules->earliest()->toDateString(),
            $rules->latest()?->toDateString(),
            $rules->minDays(),
            $rules->maxDays(),
            StorefrontRoutes::url('commerce-rental.cart.add'),
            StorefrontRoutes::url('commerce-rental.availability'),
            array_values(array_map(fn (array $p) => [...$p, 'fee' => $money->format($p['fee'])], app(RentalSettings::class)->packages())),
            app(RentalSettings::class)->get('pricing') === 'daily' ? null : __('commerce-rental::rental.form.pricing', [
                'days' => app(RentalSettings::class)->int('base_days'), 'fee' => $money->format(app(RentalSettings::class)->int('extra_day_fee')),
            ]),
        );
    }
}
