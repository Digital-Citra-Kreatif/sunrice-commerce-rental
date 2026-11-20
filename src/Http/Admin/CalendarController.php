<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Http\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Commerce\Analytics\ProductTitles;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\CommerceMultiLocations\Locations;
use Sunrice\CommerceMultiLocations\Models\Location;
use Sunrice\CommerceRental\Admin\BookingResource;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Models\Unit;

/**
 * Rentals → Calendar: a location's units down the side, days across, each
 * booking, block and the rest days after them drawn on its unit's row.
 * 60 units a page; search by unit code, SKU or variant. (The page's props
 * keep the names `branches` and `filters.branch` it was built with.)
 */
class CalendarController
{
    public const PER_PAGE = 60;

    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'branch' => ['nullable', 'integer'], 'from' => ['nullable', 'date_format:Y-m-d'], 'days' => ['nullable', 'integer', 'in:7,14,28'],
            'search' => ['nullable', 'string', 'max:60'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $locations = app(Locations::class)->all();
        $locationId = (int) ($data['branch'] ?? $locations->first()->id ?? 0);
        $days = (int) ($data['days'] ?? 14);
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from']) : CarbonImmutable::today();
        $to = $from->addDays($days - 1);
        $search = trim((string) ($data['search'] ?? ''));
        $page = (int) ($data['page'] ?? 1);

        $query = Unit::query()->where('location_id', $locationId)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', "%{$search}%")
                ->orWhereIn('variant_id', Variant::query()->where('sku', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")->select('id'))));
        $total = (clone $query)->count();
        $units = $query->orderBy('code')->forPage($page, self::PER_PAGE)->get(['id', 'code', 'variant_id', 'condition', 'active']);
        $variants = Variant::withTrashed()->whereIn('id', $units->pluck('variant_id')->unique())->get(['id', 'product_id', 'sku', 'title'])->keyBy('id');
        $titles = ProductTitles::for($variants->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values()->all());
        $bookings = Booking::query()->whereIn('unit_id', $units->pluck('id'))->where('status', Booking::ACTIVE)
            ->whereDate('starts_on', '<=', $to->toDateString())->whereDate('available_from', '>', $from->toDateString())
            ->with('order:id,number,customer_name')->orderBy('starts_on')->get()->groupBy('unit_id');

        return Inertia::render('Rental/Calendar', [
            'branches' => $locations->map(fn (Location $l) => ['id' => $l->id, 'name' => $l->name])->values(),
            'filters' => ['branch' => $locationId, 'from' => $from->toDateString(), 'days' => $days, 'search' => $search, 'page' => $page],
            'dates' => collect(range(0, $days - 1))->map(fn (int $i) => $from->addDays($i)->toDateString())->all(),
            'today' => CarbonImmutable::today()->toDateString(),
            'total' => $total,
            'perPage' => self::PER_PAGE,
            'units' => $units->map(function (Unit $unit) use ($variants, $titles, $bookings): array {
                $variant = $variants[$unit->variant_id] ?? null;

                return [
                    'id' => $unit->id, 'code' => $unit->code, 'available' => $unit->active && $unit->condition === 'good', 'condition' => $unit->condition,
                    'product' => $variant !== null ? ($titles[(int) $variant->product_id]['title'] ?? '') : '', 'variant' => $variant->title ?? '', 'sku' => $variant->sku ?? '',
                    'bookings' => ($bookings[$unit->id] ?? collect())->map(fn (Booking $b) => [
                        'id' => $b->id, 'kind' => $b->kind, 'start' => $b->starts_on->toDateString(), 'end' => $b->ends_on->toDateString(),
                        'free' => $b->available_from->toDateString(),
                        'stage' => $b->kind === Booking::ORDER ? $b->stage() : null,
                        'label' => $b->kind === Booking::ORDER ? trim(($b->order->number ?? '#'.$b->order_id).' · '.($b->order->customer_name ?? '')) : ($b->note ?: 'Blocked'),
                        'href' => $b->kind === Booking::ORDER && $b->order_id ? route('sunrice.admin.commerce.orders.show', $b->order_id, false) : null,
                    ])->values()->all(),
                ];
            })->values(),
            'stages' => BookingResource::STAGES,
        ]);
    }
}
