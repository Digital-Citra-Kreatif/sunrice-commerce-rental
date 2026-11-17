<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Sunrice\CommerceRental\Models\Branch;

/** "10 Des 2026 – 12 Des 2026 (3 hari) · Cabang Jakarta" under a rental's line (Commerce::describeLinesUsing). */
class LineDescriber
{
    /** @var array<int, string|null> */
    protected array $branches = [];

    /**
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    public function __invoke(array $meta): array
    {
        $period = RentalPeriod::fromMeta($meta);
        if ($period === null) {
            return [];
        }
        $branch = $this->branches[$period->branchId] ??= Branch::query()->whereKey($period->branchId)->value('name');
        $replace = ['from' => $period->start->translatedFormat('j M Y'), 'to' => $period->end->translatedFormat('j M Y'), 'days' => $period->days(), 'branch' => $branch ?? '—'];

        $line = __($period->days() === 1 ? 'commerce-rental::rental.line_one_day' : 'commerce-rental::rental.line', $replace);
        $package = app(RentalSettings::class)->package($period->package);

        return [$package !== null ? $package['name'].' · '.$line : $line];
    }
}
