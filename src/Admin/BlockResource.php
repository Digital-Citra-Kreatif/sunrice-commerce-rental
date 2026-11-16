<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Resources\Forms\BelongsToSelect;
use Sunrice\Admin\Resources\Forms\DatePicker;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Filter;
use Sunrice\Admin\Resources\Tables\Table;
use Sunrice\CommerceRental\Models\Booking;
use Sunrice\CommerceRental\Models\Unit;

/**
 * Rentals → Blocked dates: a unit kept from customers for some days (a
 * photo shoot, repairs, a rental taken by phone). Deleting one frees it.
 */
final class BlockResource extends Resource
{
    protected static string $model = Booking::class;

    public static function key(): string
    {
        return 'blocks';
    }

    public static function label(): string
    {
        return 'Blocked dates';
    }

    public static function singularLabel(): string
    {
        return 'Blocked dates';
    }

    public static function navigationGroup(): string
    {
        return 'Rentals';
    }

    public static function navigationIcon(): string
    {
        return 'calendar-x';
    }

    public static function navigationSort(): int
    {
        return 40;
    }

    /** @return Builder<Model> */
    public static function query(): Builder
    {
        return parent::query()->where('kind', Booking::BLOCK)->with('unit');
    }

    public static function newRecord(): Model
    {
        return new Booking(['kind' => Booking::BLOCK, 'status' => Booking::ACTIVE, 'buffer_days' => 0, 'user_id' => auth()->id()]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Block')->columns(2)->schema([
                BelongsToSelect::make('unit')->required()->title('code')->span(2)
                    ->query(fn (Builder $q) => $q->where('active', true)),
                DatePicker::make('starts_on')->label('From')->required(),
                DatePicker::make('ends_on')->label('To (included)')->required()->rules(['after_or_equal:starts_on']),
                TextInput::make('note')->label('Why')->maxLength(190)->span(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Column::make('unit.code')->label('Unit'),
                Column::make('starts_on')->label('From')->date()->sortable(),
                Column::make('ends_on')->label('To')->date()->sortable(),
                Column::make('note')->label('Why')->searchable(),
            ])
            ->filters([
                Filter::make('upcoming')->label('When')->options(['upcoming' => 'To come', 'past' => 'Past'])
                    ->query(fn (Builder $q, string $v) => $v === 'past' ? $q->whereDate('ends_on', '<', now()->toDateString()) : $q->whereDate('ends_on', '>=', now()->toDateString())),
                Filter::make('unit_id')->label('Unit')->options(fn () => Unit::query()->orderBy('code')->limit(500)->pluck('code', 'id')->all()),
            ])
            ->defaultSort('starts_on');
    }

    public static function title(Model $record): string
    {
        /** @var Booking $record */
        return ($record->unit->code ?? '').' '.$record->starts_on->format('j M').'–'.$record->ends_on->format('j M Y');
    }
}
