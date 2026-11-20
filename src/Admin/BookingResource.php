<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Admin;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Resources\Forms\DatePicker;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\Textarea;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\Action;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Filter;
use Sunrice\Admin\Resources\Tables\Table;
use Sunrice\Commerce\Orders\Actor;
use Sunrice\CommerceMultiLocations\Locations;
use Sunrice\CommerceRental\Actions\ChangeDates;
use Sunrice\CommerceRental\Actions\RecordPickup;
use Sunrice\CommerceRental\Actions\RecordReturn;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\RentalException;
use Sunrice\CommerceRental\RentalPeriod;
use Sunrice\CommerceRental\Wording;

/**
 * Rentals → Bookings: customers' rentals by day and location, to hand over
 * ("Picked up"), take back ("Returned", with how it came back) and move to
 * other days. Bookings come from orders: they're never created here.
 */
final class BookingResource extends Resource
{
    public const STAGES = ['to_pick_up' => 'To pick up', 'out' => 'Rented out', 'late' => 'Late', 'returned' => 'Returned'];

    protected static string $model = Booking::class;

    public static function key(): string
    {
        return 'bookings';
    }

    public static function label(): string
    {
        return 'Bookings';
    }

    public static function singularLabel(): string
    {
        return 'Booking';
    }

    public static function navigationGroup(): string
    {
        return 'Rentals';
    }

    public static function navigationIcon(): string
    {
        return 'calendar-check';
    }

    public static function navigationSort(): int
    {
        return 10;
    }

    /** @return Builder<Model> */
    public static function query(): Builder
    {
        return parent::query()->where('kind', Booking::ORDER)->where('status', Booking::ACTIVE)->with(['unit.location', 'order']);
    }

    public static function can(Authorizable $user, string $ability, ?Model $record = null): bool
    {
        // Bookings come from orders: no New, Edit form or Delete here.
        return ! in_array($ability, ['create', 'delete'], true);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            DatePicker::make('starts_on')->label('Pickup day')->disabled(),
            DatePicker::make('ends_on')->label('Return day')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $today = fn () => now()->toDateString();

        return $table
            ->columns([
                Column::make('order.number')->label('Order'),
                Column::make('order.customer_name')->label('Customer'),
                Column::make('unit.code')->label('Unit'),
                Column::make('location')->label(Wording::label())->state(fn (Model $b) => $b instanceof Booking ? ($b->unit->location->name ?? '') : ''),
                Column::make('starts_on')->label('Pickup')->date()->sortable(),
                Column::make('ends_on')->label('Return')->date()->sortable(),
                Column::make('stage')->label('Status')->badge()->state(fn (Model $b) => $b instanceof Booking ? self::STAGES[$b->stage()] : ''),
            ])
            ->filters([
                Filter::make('day')->label('Day')->options(['pickups' => 'Pickups today', 'returns' => 'Returns today'])
                    ->query(fn (Builder $q, string $v) => $q->whereDate($v === 'pickups' ? 'starts_on' : 'ends_on', $today())),
                Filter::make('stage')->label('Status')->options(self::STAGES)->query(fn (Builder $q, string $v) => match ($v) {
                    'to_pick_up' => $q->whereNull('picked_up_at')->whereNull('returned_at'),
                    'out' => $q->whereNotNull('picked_up_at')->whereNull('returned_at')->whereDate('ends_on', '>=', $today()),
                    'late' => $q->whereNotNull('picked_up_at')->whereNull('returned_at')->whereDate('ends_on', '<', $today()),
                    default => $q->whereNotNull('returned_at'),
                }),
                Filter::make('location')->label(Wording::label())->options(fn () => app(Locations::class)->all()->pluck('name', 'id')->all())
                    ->query(fn (Builder $q, string $v) => $q->whereHas('unit', fn ($u) => $u->where('location_id', (int) $v))),
                Filter::dateRange('starts_on')->label('Pickup between'),
            ])
            ->actions([
                Action::make('picked-up')->label('Picked up')->confirm('The customer has the dress?')
                    ->visible(fn (Model $b) => $b instanceof Booking && $b->picked_up_at === null && $b->returned_at === null)
                    ->handle(fn (Model $b) => self::attempt(fn () => app(RecordPickup::class)->handle(self::booking($b), Actor::admin()), 'Marked as picked up.')),
                Action::make('returned')->label('Returned')
                    ->visible(fn (Model $b) => $b instanceof Booking && $b->returned_at === null)
                    ->form(
                        Select::make('condition')->label('It came back')->options(['good' => 'Good: ready after the rest days', 'repair' => 'Needs repair'])->required()->default('good'),
                        Textarea::make('note')->label('Note (damage, stains, missing parts)')->maxLength(500),
                    )
                    ->handle(function (Model $b, array $input): string {
                        $clashes = self::attempt(fn () => app(RecordReturn::class)->handle(self::booking($b), Actor::admin(), (string) $input['condition'], $input['note'] ?? null));
                        if (is_array($clashes) && $clashes !== []) {
                            return __('commerce-rental::rental.ops.returned_clash', ['count' => count($clashes), 'date' => $clashes[0]->starts_on->format('j M')]);
                        }

                        return __('commerce-rental::rental.ops.returned');
                    }),
                Action::make('dates')->label('Change dates')
                    ->visible(fn (Model $b) => $b instanceof Booking && $b->returned_at === null)
                    ->form(
                        DatePicker::make('start')->label('Pickup day')->required(),
                        DatePicker::make('end')->label('Return day')->required()->rules(['after_or_equal:start']),
                    )
                    ->handle(function (Model $b, array $input): string {
                        $booking = self::booking($b);
                        $period = RentalPeriod::make((string) $input['start'], (string) $input['end'], (int) ($booking->unit->location_id ?? 0));
                        self::attempt(fn () => app(ChangeDates::class)->handle($booking, $period, Actor::admin()));

                        return __('commerce-rental::rental.ops.dates_changed');
                    }),
                Action::make('order')->label('Open order')->url(fn (Model $b) => $b instanceof Booking && $b->order_id ? route('sunrice.admin.commerce.orders.show', $b->order_id) : ''),
            ])
            ->defaultSort('starts_on')
            ->perPage(50)
            ->exportable();
    }

    protected static function booking(Model $record): Booking
    {
        if (! $record instanceof Booking) {
            throw new ActionFailed('Not a booking.');
        }

        return $record;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $step
     * @return T|string
     */
    protected static function attempt(callable $step, ?string $done = null): mixed
    {
        try {
            $result = $step();
        } catch (RentalException $e) {
            throw new ActionFailed($e->getMessage());
        }

        return $done ?? $result;
    }
}
