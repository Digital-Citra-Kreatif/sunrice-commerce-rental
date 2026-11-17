<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Http;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sunrice\Commerce\Cart\Actions\AddToCart;
use Sunrice\Commerce\Cart\CartException;
use Sunrice\Commerce\Catalog\LiveEntries;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\Commerce\Storefront\StorefrontRoutes;
use Sunrice\Commerce\Support\MoneyFormatter;
use Sunrice\CommerceRental\Availability;
use Sunrice\CommerceRental\Models\Branch;
use Sunrice\CommerceRental\Pricing\RentalPrice;
use Sunrice\CommerceRental\RentalPeriod;
use Sunrice\CommerceRental\RentalRules;
use Sunrice\CommerceRental\RentalSettings;
use Throwable;

/**
 * The booking form's endpoints: add a rental to the cart, and how many
 * units are free (for the form's availability line and date picker).
 * What's free is checked again, under lock, when the order is placed.
 */
class RentalController
{
    public function __construct(protected Availability $availability, protected RentalRules $rules) {}

    public function add(Request $request, AddToCart $add): RedirectResponse
    {
        $data = $request->validate([
            'variant' => ['required', 'integer'], 'branch' => ['required', 'integer'],
            'start' => ['required', 'date_format:Y-m-d'], 'end' => ['required_without:package', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'], 'package' => ['nullable', 'string', 'max:60'],
        ]);
        $variant = Variant::query()->with('product')->find((int) $data['variant']);
        $branch = Branch::query()->where('active', true)->find((int) $data['branch']);
        if ($variant === null || $branch === null || ! $this->availability->isRental($variant)) {
            return back()->withInput()->with('commerce.error', __('commerce-rental::rental.errors.dates_missing'));
        }
        $period = $this->period($data['start'], $data['end'] ?? $data['start'], $branch->id, $data['package'] ?? null);
        $quantity = (int) ($data['quantity'] ?? 1);
        $refusal = $this->rules->refusal($period) ?? ($this->availability->count($variant, $period) < $quantity ? __('commerce-rental::rental.errors.unavailable') : null);
        if ($refusal !== null) {
            return back()->withInput()->with('commerce.error', $refusal);
        }
        try {
            $add->handle($variant->id, $quantity, $period->toMeta());
        } catch (CartException $e) {
            return back()->withInput()->with('commerce.error', $e->getMessage());
        }
        $title = (string) ((($e = $variant->product->entry()) !== null ? LiveEntries::translation($e)?->title : null) ?? '');

        return ($request->boolean('buy_now') && Route::has('commerce.checkout') ? redirect(StorefrontRoutes::url('commerce.checkout')) : back())
            ->with('commerce.success', __('commerce::storefront.cart.added', ['product' => $title]));
    }

    /** A package fixes the length: its days from the pickup day, whatever end was sent. */
    protected function period(string $start, string $end, int $branch, ?string $package): RentalPeriod
    {
        $chosen = app(RentalSettings::class)->package($package ?: null);
        if ($chosen !== null) {
            $end = CarbonImmutable::parse($start)->addDays($chosen['days'] - 1)->toDateString();
        }

        return RentalPeriod::make($start, $end, $branch, $package ?: null);
    }

    /**
     * ?variant=&start=&end=[&package=] → the price and the free units at each branch;
     * with &branch= and &month=YYYY-MM instead, the free units on each day.
     */
    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'variant' => ['required', 'integer'], 'branch' => ['nullable', 'integer'],
            'start' => ['nullable', 'date_format:Y-m-d'], 'end' => ['nullable', 'date_format:Y-m-d'], 'month' => ['nullable', 'date_format:Y-m'],
            'package' => ['nullable', 'string', 'max:60'],
        ]);
        $variant = Variant::query()->find((int) $data['variant']);
        if ($variant === null || ! $this->availability->isRental($variant)) {
            return response()->json(['message' => __('commerce-rental::rental.errors.dates_missing')], 404);
        }

        if (isset($data['month'], $data['branch'])) {
            $first = CarbonImmutable::createFromFormat('Y-m-d', $data['month'].'-01')?->startOfDay() ?? CarbonImmutable::today();
            $from = $first->max($this->rules->earliest());

            return response()->json(['days' => $this->rules->latest() !== null && $from->gt($this->rules->latest()) ? [] : $this->availability->calendar($variant, (int) $data['branch'], $from, (int) $from->diffInDays($first->endOfMonth()) + 1)]);
        }

        if (! isset($data['start']) || (! isset($data['end']) && empty($data['package']))) {
            return response()->json(['message' => __('commerce-rental::rental.errors.dates_missing')], 422);
        }
        try {
            $period = $this->period($data['start'], $data['end'] ?? $data['start'], 0, $data['package'] ?? null);
        } catch (Throwable) {
            return response()->json(['message' => __('commerce-rental::rental.errors.dates_missing')], 422);
        }
        $refusal = $this->rules->refusal($period);
        $money = app(MoneyFormatter::class);

        $price = app(RentalPrice::class)->price($variant->price, $variant->price, $period)[0];

        return response()->json([
            'days' => $period->days(),
            'end' => $period->end->toDateString(),
            'price' => $price,
            'price_formatted' => $money->format($price),
            'message' => $refusal,
            'branches' => $refusal !== null ? [] : array_map(fn (array $row) => [
                'id' => $row['branch']->id, 'name' => $row['branch']->name, 'available' => $row['available'],
            ], $this->availability->atBranches($variant, $period->start, $period->end)),
        ]);
    }
}
