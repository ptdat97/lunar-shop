<?php

namespace Modules\Product\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Product\Filament\Resources\SizeChartResource\Pages\CreateSizeChart;
use Modules\Product\Filament\Resources\SizeChartResource\Pages\EditSizeChart;
use Modules\Product\Filament\Resources\SizeChartResource\Pages\ListSizeCharts;
use Modules\Product\Models\SizeChart;

/**
 * Standalone admin resource for reusable size charts. Defined once here, then
 * picked by products on their "Size & Fit" tab. Being a top-level resource
 * (not a Lunar product sub-page) it avoids the relation-manager lazy-load 419.
 */
class SizeChartResource extends Resource
{
    protected static ?string $model = SizeChart::class;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'Size Charts';

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.catalog');
    }

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Chart')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->placeholder("e.g. Women's Tops")
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('category')
                        ->options([
                            'tops' => 'Tops',
                            'bottoms' => 'Bottoms',
                            'dresses' => 'Dresses',
                            'outerwear' => 'Outerwear',
                            'accessories' => 'Accessories',
                        ])
                        ->native(false),
                    Forms\Components\Toggle::make('active')->default(true),
                ])
                ->columns(3),

            Forms\Components\Section::make('Sizes')
                ->description('Add a row per size with garment measurements (cm). Ranges like "88-92" are supported.')
                ->schema([
                    Forms\Components\Repeater::make('rows')
                        ->relationship()
                        ->schema([
                            Forms\Components\TextInput::make('size')->required()->placeholder('S')->columnSpan(1),
                            Forms\Components\Select::make('fit')
                                ->options(['slim' => 'Slim', 'regular' => 'Regular', 'relaxed' => 'Relaxed', 'oversized' => 'Oversized'])
                                ->native(false)->columnSpan(1),
                            Forms\Components\TextInput::make('bust')->label('Bust/Chest')->columnSpan(1),
                            Forms\Components\TextInput::make('waist')->columnSpan(1),
                            Forms\Components\TextInput::make('hip')->columnSpan(1),
                            Forms\Components\TextInput::make('shoulder')->columnSpan(1),
                            Forms\Components\TextInput::make('length')->columnSpan(1),
                            Forms\Components\TextInput::make('inseam')->columnSpan(1),
                        ])
                        ->columns(4)
                        ->orderColumn('sort')
                        ->reorderable()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => $state['size'] ?? null)
                        ->addActionLabel('Add size'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category')->badge()->placeholder('—'),
                Tables\Columns\TextColumn::make('rows_count')->counts('rows')->label('Sizes'),
                Tables\Columns\IconColumn::make('active')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSizeCharts::route('/'),
            'create' => CreateSizeChart::route('/create'),
            'edit' => EditSizeChart::route('/{record}/edit'),
        ];
    }
}
