<?php

namespace Modules\Shipping\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Models\Country;
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

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Shipping Zones';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Zone')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Internal label, e.g. "Vietnam — Major cities".'),
                    Forms\Components\Select::make('country_code')
                        ->label('Country')
                        ->required()
                        ->searchable()
                        ->options(fn () => Country::query()
                            ->whereNotNull('iso2')
                            ->orderBy('name')
                            ->pluck('name', 'iso2')),
                    Forms\Components\TagsInput::make('states')
                        ->label('States / provinces')
                        ->placeholder('Add a state…')
                        ->helperText('Leave empty to cover the whole country. Names must match the address state exactly.')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Rate')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('rate')
                        ->label('Rate (minor units)')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(3000)
                        ->helperText('e.g. 3000 = 30.00 in the store currency.'),
                    Forms\Components\TextInput::make('free_threshold')
                        ->label('Free shipping over (minor units)')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('0 disables free shipping for this zone.'),
                    Forms\Components\TextInput::make('priority')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower wins when several zones match.'),
                    Forms\Components\Toggle::make('enabled')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('country_code')->label('Country')->sortable(),
                Tables\Columns\TextColumn::make('states')
                    ->label('States')
                    ->badge()
                    ->placeholder('Whole country'),
                Tables\Columns\TextColumn::make('rate')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('free_threshold')->label('Free over')->numeric()->sortable(),
                Tables\Columns\IconColumn::make('enabled')->boolean(),
                Tables\Columns\TextColumn::make('priority')->numeric()->sortable()->toggleable(),
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
            'index' => \Modules\Shipping\Filament\Resources\ShippingZoneResource\Pages\ListShippingZones::route('/'),
            'create' => \Modules\Shipping\Filament\Resources\ShippingZoneResource\Pages\CreateShippingZone::route('/create'),
            'edit' => \Modules\Shipping\Filament\Resources\ShippingZoneResource\Pages\EditShippingZone::route('/{record}/edit'),
        ];
    }
}
