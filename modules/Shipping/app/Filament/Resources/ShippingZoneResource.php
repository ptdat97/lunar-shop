<?php

namespace Modules\Shipping\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lunar\Core\Models\Country;
use Modules\Shipping\Filament\Resources\ShippingZoneResource\Pages\CreateShippingZone;
use Modules\Shipping\Filament\Resources\ShippingZoneResource\Pages\EditShippingZone;
use Modules\Shipping\Filament\Resources\ShippingZoneResource\Pages\ListShippingZones;
use Modules\Shipping\Models\ShippingZone;

/**
 * Admin CRUD for shipping zones (country + optional states → flat rate, free
 * threshold). The storefront's shipping option is computed from these by
 * FlatRateShippingModifier.
 */
class ShippingZoneResource extends Resource
{
    protected static ?string $model = ShippingZone::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    public static function getNavigationLabel(): string
    {
        return __('admin.shipping.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.shipping.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.shipping.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.settings');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.shipping.section_zone'))
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label(__('admin.common.name'))
                        ->required()
                        ->maxLength(255)
                        ->helperText(__('admin.shipping.name_help')),
                    Select::make('country_code')
                        ->label(__('admin.shipping.country'))
                        ->required()
                        ->searchable()
                        ->options(fn () => Country::query()
                            ->whereNotNull('iso2')
                            ->orderBy('name')
                            ->pluck('name', 'iso2')),
                    TagsInput::make('states')
                        ->label(__('admin.shipping.states'))
                        ->placeholder(__('admin.shipping.states_placeholder'))
                        ->helperText(__('admin.shipping.states_help'))
                        ->columnSpanFull(),
                ]),

            Section::make(__('admin.shipping.section_rate'))
                ->columns(2)
                ->schema([
                    TextInput::make('rate')
                        ->label(__('admin.shipping.rate'))
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(3000)
                        ->helperText(__('admin.shipping.rate_help')),
                    TextInput::make('free_threshold')
                        ->label(__('admin.shipping.free_threshold'))
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->helperText(__('admin.shipping.free_threshold_help')),
                    TextInput::make('priority')
                        ->label(__('admin.shipping.priority'))
                        ->numeric()
                        ->default(0)
                        ->helperText(__('admin.shipping.priority_help')),
                    Toggle::make('enabled')
                        ->label(__('admin.common.enabled'))
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('admin.common.name'))->searchable()->sortable(),
                TextColumn::make('country_code')->label(__('admin.shipping.country'))->sortable(),
                TextColumn::make('states')
                    ->label(__('admin.shipping.states'))
                    ->badge()
                    ->placeholder(__('admin.shipping.whole_country')),
                TextColumn::make('rate')->label(__('admin.shipping.rate'))->numeric()->sortable(),
                TextColumn::make('free_threshold')->label(__('admin.shipping.free_over'))->numeric()->sortable(),
                IconColumn::make('enabled')->label(__('admin.common.enabled'))->boolean(),
                TextColumn::make('priority')->label(__('admin.shipping.priority'))->numeric()->sortable()->toggleable(),
            ])
            ->defaultSort('country_code')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingZones::route('/'),
            'create' => CreateShippingZone::route('/create'),
            'edit' => EditShippingZone::route('/{record}/edit'),
        ];
    }
}
