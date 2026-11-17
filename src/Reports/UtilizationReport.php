<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Reports;

use Carbon\CarbonImmutable;
use Sunrice\Commerce\Analytics\Period;
use Sunrice\Commerce\Analytics\ProductTitles;
use Sunrice\Commerce\Analytics\Reports\Report;
use Sunrice\Commerce\Analytics\Reports\ReportResult;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Models\Branch;
use Sunrice\CommerceRental\Models\Unit;

/**
 * Shop → Reports → Rentals: for each dress (variant) at each branch, how
 * many days its units were out with customers in the period, as a share
 * of the days they could have been, the rentals that started then and the
 * late returns. Units in repair or retired count as not rentable.
 */
class UtilizationReport extends Report
{
    public function key(): string
    {
        return 'rental-utilization';
    }

    public function label(): string
    {
        return 'Rentals';
    }

    public function description(): string
    {
        return 'Days each dress was rented out at each branch, against the days its units could have been. Rest days and blocked dates count as not rented.';
    }

    public function feature(): ?string
    {
        return 'rental';
    }

    public function run(Period $period): ReportResult
    {
        $from = CarbonImmutable::parse($period->from)->startOfDay();
        $to = CarbonImmutable::parse($period->to)->startOfDay();
        $days = (int) $from->diffInDays($to) + 1;

        $units = Unit::query()->where('active', true)->where('condition', 'good')->get(['id', 'variant_id', 'branch_id']);
        $bookings = Booking::query()->where('kind', Booking::ORDER)->where('status', Booking::ACTIVE)
            ->whereDate('starts_on', '<=', $to->toDateString())->whereDate('ends_on', '>=', $from->toDateString())
            ->with('unit:id,variant_id,branch_id')->get(['id', 'unit_id', 'starts_on', 'ends_on', 'picked_up_at', 'returned_at']);

        $groups = [];
        foreach ($units as $unit) {
            $groups["{$unit->variant_id}:{$unit->branch_id}"] ??= ['variant' => $unit->variant_id, 'branch' => $unit->branch_id, 'units' => 0, 'rented' => 0, 'rentals' => 0, 'late' => 0];
            $groups["{$unit->variant_id}:{$unit->branch_id}"]['units']++;
        }
        foreach ($bookings as $booking) {
            if ($booking->unit === null) {
                continue;
            }
            $key = "{$booking->unit->variant_id}:{$booking->unit->branch_id}";
            $groups[$key] ??= ['variant' => $booking->unit->variant_id, 'branch' => $booking->unit->branch_id, 'units' => 0, 'rented' => 0, 'rentals' => 0, 'late' => 0];
            $start = CarbonImmutable::instance($booking->starts_on)->max($from);
            $end = CarbonImmutable::instance($booking->ends_on)->min($to);
            $groups[$key]['rented'] += (int) $start->diffInDays($end) + 1;
            $groups[$key]['rentals'] += $booking->starts_on->gte($from) ? 1 : 0;
            $late = $booking->returned_at !== null ? $booking->returned_at->startOfDay()->gt($booking->ends_on) : $booking->ends_on->lt(today());
            $groups[$key]['late'] += $late && $booking->picked_up_at !== null ? 1 : 0;
        }

        $variants = Variant::withTrashed()->whereIn('id', array_unique(array_column($groups, 'variant')))->get(['id', 'product_id', 'sku', 'title'])->keyBy('id');
        $titles = ProductTitles::for($variants->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values()->all());
        $branches = Branch::query()->pluck('name', 'id');
        $rows = [];
        foreach ($groups as $g) {
            $variant = $variants[$g['variant']] ?? null;
            $capacity = $g['units'] * $days;
            $rows[] = [
                'product' => $variant !== null ? ($titles[(int) $variant->product_id]['title'] ?? "Product #{$variant->product_id}") : "Variant #{$g['variant']}",
                'variant' => (string) ($variant->title ?? ''), 'sku' => (string) ($variant->sku ?? ''),
                'branch' => (string) ($branches[$g['branch']] ?? ''), 'units' => $g['units'], 'rentals' => $g['rentals'], 'rented' => $g['rented'],
                'utilization' => $capacity > 0 ? round(min(100, $g['rented'] / $capacity * 100), 1) : null, 'late' => $g['late'],
            ];
        }
        usort($rows, fn (array $a, array $b) => ($b['utilization'] ?? -1) <=> ($a['utilization'] ?? -1));
        $unitsTotal = array_sum(array_column($rows, 'units'));
        $rentedTotal = array_sum(array_column($rows, 'rented'));

        return new ReportResult(
            columns: [self::column('product', 'Product'), self::column('variant', 'Size'), self::column('sku', 'SKU'), self::column('branch', 'Branch'),
                self::column('units', 'Units', 'count'), self::column('rentals', 'Rentals started', 'count'), self::column('rented', 'Days rented', 'count'),
                self::column('utilization', 'Utilization %', 'percent'), self::column('late', 'Late returns', 'count')],
            rows: $rows,
            summary: self::summary([
                'rentals' => array_sum(array_column($rows, 'rentals')), 'rented' => $rentedTotal,
                'utilization' => $unitsTotal > 0 ? round($rentedTotal / ($unitsTotal * $days) * 100, 1) : 0, 'late' => array_sum(array_column($rows, 'late')),
            ], null, [
                'rentals' => ['Rentals started', 'count'], 'rented' => ['Days rented', 'count'], 'utilization' => ['Utilization', 'percent'], 'late' => ['Late returns', 'count'],
            ]),
        );
    }
}
