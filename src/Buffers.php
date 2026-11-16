<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental;

use Illuminate\Support\Facades\DB;
use Sunrice\Cms\Models\Entry;
use Sunrice\Commerce\Catalog\Models\Variant;

/**
 * Rest days after a return: the product's own number, else the first of its
 * categories that has one (the lowest), else the store default.
 */
class Buffers
{
    public function forVariant(Variant $variant): int
    {
        $product = $variant->product;
        $own = DB::table('commerce_rental_buffers')->where('scope', 'product')->where('scope_id', $variant->product_id)->value('days');
        if ($own !== null) {
            return (int) $own;
        }
        $termIds = $this->categoryIds((int) $product->entry_id);
        if ($termIds !== []) {
            $category = DB::table('commerce_rental_buffers')->where('scope', 'category')->whereIn('scope_id', $termIds)->min('days');
            if ($category !== null) {
                return (int) $category;
            }
        }

        return max(0, (int) config('commerce-rental.buffer_days', 2));
    }

    /** @return array<int, int> */
    protected function categoryIds(int $entryId): array
    {
        $entry = Entry::query()->find($entryId);

        return $entry === null ? [] : $entry->terms()->pluck('sunrice_terms.id')->map(fn ($id) => (int) $id)->all();
    }
}
