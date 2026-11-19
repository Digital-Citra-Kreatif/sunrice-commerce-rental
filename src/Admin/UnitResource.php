<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Admin;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Resources\Forms\BelongsToSelect;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\Textarea;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\Action;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Filter;
use Sunrice\Admin\Resources\Tables\Table;
use Sunrice\Admin\Support\Card;
use Sunrice\Commerce\Catalog\Models\Variant;
use Sunrice\CommerceMultiLocations\Locations;
use Sunrice\CommerceMultiLocations\Models\Location;
use Sunrice\CommerceRental\Actions\MoveUnit;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Models\Transfer;
use Sunrice\CommerceRental\Models\Unit;
use Sunrice\CommerceRental\RentalException;
use Sunrice\CommerceRental\Wording;

/**
 * Rentals → Units: each physical dress, with its code (on its label), the
 * variant it is (product and size) and the location that has it.
 */
final class UnitResource extends Resource
{
    public const CONDITIONS = ['good' => 'Good', 'repair' => 'In repair', 'retired' => 'Retired'];

    protected static string $model = Unit::class;

    public static function key(): string
    {
        return 'units';
    }

    public static function label(): string
    {
        return 'Units';
    }

    public static function singularLabel(): string
    {
        return 'Unit';
    }

    public static function navigationGroup(): string
    {
        return 'Rentals';
    }

    public static function navigationIcon(): string
    {
        return 'shirt';
    }

    public static function navigationSort(): int
    {
        return 20;
    }

    /** @return Builder<Model> */
    public static function query(): Builder
    {
        return parent::query()->with(['location', 'variant']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Unit')->columns(2)->schema([
                TextInput::make('code')->required()->maxLength(40)->unique()->help('As on the label sewn in the dress: JKT-GM-0001.'),
                BelongsToSelect::make('variant')->label('Product variant')->required()
                    ->title(fn (Model $v) => $v instanceof Variant ? trim(($v->sku ?? '').' · '.$v->title, ' ·') : '')->searchColumns('sku', 'title'),
                BelongsToSelect::make('location')->label(Wording::label())->required()->title('name')->onlyOnCreate()
                    ->help('To move it later, use Move: the move is recorded.'),
                Select::make('condition')->options(self::CONDITIONS)->required()->default('good'),
                Toggle::make('active')->default(true)->help('Inactive units are never booked.'),
                Textarea::make('note')->rows(3)->maxLength(1000)->span(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Column::make('code')->searchable()->sortable(),
                Column::make('variant.sku')->label('SKU'),
                Column::make('variant.title')->label('Variant'),
                Column::make('location.name')->label(Wording::label()),
                Column::make('condition')->badge()->format(fn (?string $c) => self::CONDITIONS[$c ?? ''] ?? (string) $c),
                Column::make('active')->boolean(),
            ])
            ->filters([
                Filter::make('location_id')->label(Wording::label())->options(fn () => app(Locations::class)->all()->pluck('name', 'id')->all()),
                Filter::make('condition')->options(self::CONDITIONS),
                Filter::make('active')->boolean(),
            ])
            ->actions([
                Action::make('move')->label('Move to '.Wording::location('en'))
                    ->form(
                        Select::make('location')->label('To '.Wording::location('en'))->options(fn () => app(Locations::class)->active()->pluck('name', 'id')->all())->required(),
                        Textarea::make('note')->label('Why (optional)')->maxLength(500),
                    )
                    ->handle(function (Model $unit, array $input): string {
                        /** @var Unit $unit */
                        $to = Location::query()->findOrFail((int) $input['location']);
                        try {
                            app(MoveUnit::class)->handle($unit, $to, auth()->id(), $input['note'] ?? null);
                        } catch (RentalException $e) {
                            throw new ActionFailed($e->getMessage());
                        }

                        return Wording::trans('commerce-rental::rental.move.done', ['code' => $unit->code, 'name' => $to->name]);
                    }),
            ])
            ->defaultSort('code')
            ->perPage(50)
            ->exportable();
    }

    public static function aside(Model $record, Authorizable $user): array
    {
        /** @var Unit $record */
        /** @var Collection<int, Booking> $upcoming */
        $upcoming = $record->upcoming()->limit(10)->get();
        /** @var Collection<int, Transfer> $transfers */
        $transfers = $record->transfers()->with(['from', 'to'])->limit(10)->get();

        return [
            Card::make('Bookings to come')
                ->list('Dates', $upcoming->map(fn (Booking $b) => [
                    'label' => $b->starts_on->format('j M').' – '.$b->ends_on->format('j M Y'),
                    'meta' => $b->kind === Booking::BLOCK ? 'Blocked'.($b->note ? ": {$b->note}" : '') : 'Order #'.$b->order_id,
                ]), empty: 'Free.'),
            Card::make('Moves')
                ->list('History', $transfers->map(fn (Transfer $t) => [
                    'label' => ($t->from->name ?? '?').' → '.($t->to->name ?? '?'), 'meta' => $t->note, 'at' => $t->created_at,
                ]), empty: 'Never moved.'),
        ];
    }

    public static function deleteRecord(Model $record): void
    {
        if ($record instanceof Unit && $record->bookings()->where('kind', Booking::ORDER)->exists()) {
            throw new ActionFailed(__('commerce-rental::rental.unit.has_bookings'));
        }
        parent::deleteRecord($record);
    }
}
