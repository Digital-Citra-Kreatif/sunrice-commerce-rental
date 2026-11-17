<?php

declare(strict_types=1);

namespace Sunrice\CommerceRental\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\NumberInput;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\Textarea;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Resources\Tables\ActionFailed;
use Sunrice\Admin\Resources\Tables\Column;
use Sunrice\Admin\Resources\Tables\Table;
use Sunrice\CommerceRental\Models\Branch;

/** Rentals → Branches: where dresses are picked up and returned. */
final class BranchResource extends Resource
{
    protected static string $model = Branch::class;

    public static function key(): string
    {
        return 'branches';
    }

    public static function label(): string
    {
        return 'Branches';
    }

    public static function singularLabel(): string
    {
        return 'Branch';
    }

    public static function navigationGroup(): string
    {
        return 'Rentals';
    }

    public static function navigationIcon(): string
    {
        return 'store';
    }

    public static function navigationSort(): int
    {
        return 30;
    }

    /** @return Builder<Model> */
    public static function query(): Builder
    {
        return parent::query()->withCount('units');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Branch')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('code')->required()->maxLength(10)->unique()->rules(['regex:/^[A-Z0-9]+$/'])
                    ->help('Short and in capitals (JKT): it starts the unit codes.'),
                TextInput::make('whatsapp')->label('WhatsApp number')->maxLength(20)->rules(['nullable', 'regex:/^\+?[0-9 ()-]{8,20}$/'])
                    ->help('Customers renting from this branch are sent here.'),
                NumberInput::make('sort_order')->label('Order')->integer()->min(0)->default(0),
                Textarea::make('address')->rows(3)->maxLength(500)->span(2)->help('Where customers pick up and return, and where couriers collect deliveries.'),
                TextInput::make('postal_code')->label('Postal code')->maxLength(5)->rules(['nullable', 'digits:5'])->help('Needed for courier rates from this branch.'),
                TextInput::make('phone')->label('Phone for couriers')->maxLength(30)->help('Empty: the WhatsApp number.'),
                TextInput::make('latitude')->maxLength(20)->rules(['nullable', 'numeric', 'between:-90,90'])->help('From Google Maps (right-click the branch). Needed for GoSend and Grab.'),
                TextInput::make('longitude')->maxLength(20)->rules(['nullable', 'numeric', 'between:-180,180']),
                Toggle::make('active')->default(true)->help('Inactive branches are not offered to customers.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Column::make('name')->searchable()->sortable(),
                Column::make('code')->searchable()->sortable(),
                Column::make('whatsapp')->label('WhatsApp'),
                Column::make('units_count')->label('Units'),
                Column::make('active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->exportable();
    }

    public static function deleteRecord(Model $record): void
    {
        if ($record instanceof Branch && $record->units()->exists()) {
            throw new ActionFailed(__('commerce-rental::rental.branch.has_units'));
        }
        parent::deleteRecord($record);
    }
}
