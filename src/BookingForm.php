<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Illuminate\Support\Facades\Route;
use Sunrice\Commerce\Catalog\Models\Product;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\Commerce\Storefront\StorefrontRoutes;
use Sunrice\Commerce\Support\MoneyFormatter;
use Sunrice\CommerceRental\Models\Branch;
use Sunrice\CommerceRental\Models\Unit;

/** What the booking form (commerce-rental::booking) needs for a product: its rental sizes, the branches, the limits. */
final class BookingForm
{
    /**
     * @param  list<array{id: int, title: string, price: string}>  $variants
     * @param  list<array{id: int, name: string}>  $branches
     */
    public function __construct(
        public readonly Product $product,
        public readonly array $variants,
        public readonly array $branches,
        public readonly string $minDate,
        public readonly string $maxDate,
        public readonly int $minDays,
        public readonly int $maxDays,
        public readonly string $action,
        public readonly string $availabilityUrl,
    ) {}

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
        $branches = Branch::query()->where('active', true)
            ->whereHas('units', fn ($q) => $q->whereIn('variant_id', $variants->pluck('id'))->where('active', true))
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'name']);

        return new self(
            $product,
            $variants->map(fn (Variant $v) => ['id' => $v->id, 'title' => $v->title, 'price' => $money->format($v->price)])->values()->all(),
            $branches->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name])->values()->all(),
            $rules->earliest()->toDateString(),
            $rules->latest()->toDateString(),
            $rules->minDays(),
            $rules->maxDays(),
            StorefrontRoutes::url('commerce-rental.cart.add'),
            StorefrontRoutes::url('commerce-rental.availability'),
        );
    }
}
