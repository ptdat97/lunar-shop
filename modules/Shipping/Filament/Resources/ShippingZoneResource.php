<?php

namespace Modules\Shipping\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Models\Country;
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

    protected static ?string $navigationIcon = 'heroicon-o-truck';

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

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin.shipping.section_zone'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('admin.common.name'))
                        ->required()
                        ->maxLength(255)
                        ->helperText(__('admin.shipping.name_help')),
                    Forms\Components\Select::make('country_code')
                        ->label(__('admin.shipping.country'))
                        ->required()
                        ->searchable()
                        ->options(fn () => Country::query()
                            ->whereNotNull('iso2')
                            ->orderBy('name')
                            ->pluck('name', 'iso2')),
                    Forms\Components\TagsInput::make('states')
                        ->label(__('admin.shipping.states'))
                        ->placeholder(__('admin.shipping.states_placeholder'))
                        ->helperText(__('admin.shipping.states_help'))
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('admin.shipping.section_rate'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('rate')
                        ->label(__('admin.shipping.rate'))
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(3000)
                        ->helperText(__('admin.shipping.rate_help')),
                    Forms\Components\TextInput::make('free_threshold')
                        ->label(__('admin.shipping.free_threshold'))
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->helperText(__('admin.shipping.free_threshold_help')),
                    Forms\Components\TextInput::make('priority')
                        ->label(__('admin.shipping.priority'))
                        ->numeric()
                        ->default(0)
                        ->helperText(__('admin.shipping.priority_help')),
                    Forms\Components\Toggle::make('enabled')
                        ->label(__('admin.common.enabled'))
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('admin.common.name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('country_code')->label(__('admin.shipping.country'))->sortable(),
                Tables\Columns\TextColumn::make('states')
                    ->label(__('admin.shipping.states'))
                    ->badge()
                    ->placeholder(__('admin.shipping.whole_country')),
                Tables\Columns\TextColumn::make('rate')->label(__('admin.shipping.rate'))->numeric()->sortable(),
                Tables\Columns\TextColumn::make('free_threshold')->label(__('admin.shipping.free_over'))->numeric()->sortable(),
                Tables\Columns\IconColumn::make('enabled')->label(__('admin.common.enabled'))->boolean(),
                Tables\Columns\TextColumn::make('priority')->label(__('admin.shipping.priority'))->numeric()->sortable()->toggleable(),
            ])
            ->defaultSort('country_code')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
