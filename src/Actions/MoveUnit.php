<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Actions;

use Illuminate\Support\Facades\DB;
use Sunrice\CommerceMultiLocations\Models\Location;
use Sunrice\CommerceRental\Models\Transfer;
use Sunrice\CommerceRental\Models\Unit;
use Sunrice\CommerceRental\RentalException;
use Sunrice\CommerceRental\Wording;

/**
 * Moves a unit to another location and records it. Refused while the unit
 * has customer bookings to come: those customers pick it up at the old
 * location (move it after they return it, or change their bookings first).
 */
class MoveUnit
{
    public function handle(Unit $unit, Location $to, ?int $userId = null, ?string $note = null): Transfer
    {
        return DB::transaction(function () use ($unit, $to, $userId, $note): Transfer {
            /** @var Unit $locked */
            $locked = Unit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
            if ($locked->location_id === $to->id) {
                throw new RentalException(Wording::trans('commerce-rental::rental.move.same_location'));
            }
            $booked = $locked->upcoming()->where('kind', 'order')->count();
            if ($booked > 0) {
                throw new RentalException(Wording::choice('commerce-rental::rental.move.booked', $booked, ['count' => $booked]));
            }
            $transfer = Transfer::query()->create(['unit_id' => $locked->id, 'from_location_id' => $locked->location_id, 'to_location_id' => $to->id,
                'user_id' => $userId, 'note' => $note !== null && $note !== '' ? $note : null]);
            $locked->forceFill(['location_id' => $to->id])->save();
            $unit->setRawAttributes($locked->getAttributes(), true);

            return $transfer;
        });
    }
}
