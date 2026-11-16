<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Actions;

use Illuminate\Support\Facades\DB;
use Sunrice\CommerceRental\Models\Branch;
use Sunrice\CommerceRental\Models\Transfer;
use Sunrice\CommerceRental\Models\Unit;
use Sunrice\CommerceRental\RentalException;

/**
 * Moves a unit to another branch and records it. Refused while the unit
 * has customer bookings to come: those customers pick it up at the old
 * branch (move it after they return it, or change their bookings first).
 */
class MoveUnit
{
    public function handle(Unit $unit, Branch $to, ?int $userId = null, ?string $note = null): Transfer
    {
        return DB::transaction(function () use ($unit, $to, $userId, $note): Transfer {
            /** @var Unit $locked */
            $locked = Unit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
            if ($locked->branch_id === $to->id) {
                throw new RentalException(__('commerce-rental::rental.move.same_branch'));
            }
            $booked = $locked->upcoming()->where('kind', 'order')->count();
            if ($booked > 0) {
                throw new RentalException(trans_choice('commerce-rental::rental.move.booked', $booked, ['count' => $booked]));
            }
            $transfer = Transfer::query()->create(['unit_id' => $locked->id, 'from_branch_id' => $locked->branch_id, 'to_branch_id' => $to->id,
                'user_id' => $userId, 'note' => $note !== null && $note !== '' ? $note : null]);
            $locked->forceFill(['branch_id' => $to->id])->save();
            $unit->setRawAttributes($locked->getAttributes(), true);

            return $transfer;
        });
    }
}
