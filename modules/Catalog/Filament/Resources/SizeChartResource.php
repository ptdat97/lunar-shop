<?php

namespace Modules\Catalog\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Catalog\Filament\Resources\SizeChartResource\Pages\CreateSizeChart;
use Modules\Catalog\Filament\Resources\SizeChartResource\Pages\EditSizeChart;
use Modules\Catalog\Filament\Resources\SizeChartResource\Pages\ListSizeCharts;
use Modules\Catalog\Models\SizeChart;

/**
 * Standalone admin resource for reusable size charts. Defined once here, then
 * picked by products on their "Size & Fit" tab. Being a top-level resource
 * (not a Lunar product sub-page) it avoids the relation-manager lazy-load 419.
 */
class SizeChartResource extends Resource
{
    protected static ?string $model = SizeChart::class;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    public static function getNavigationLabel(): string
    {
        return __('admin.size_chart.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.size_chart.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.size_chart.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.catalog');
    }

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin.size_chart.section'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('admin.common.name'))
                        ->placeholder("e.g. Women's Tops")
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('category')
                        ->label(__('admin.size_chart.category'))
                        ->options([
                            'tops' => __('admin.size_chart.cat_tops'),
                            'bottoms' => __('admin.size_chart.cat_bottoms'),
                            'dresses' => __('admin.size_chart.cat_dresses'),
                            'outerwear' => __('admin.size_chart.cat_outerwear'),
                            'accessories' => __('admin.size_chart.cat_accessories'),
                        ])
                        ->native(false),
                    Forms\Components\Toggle::make('active')->label(__('admin.common.active'))->default(true),
                ])
                ->columns(3),

            Forms\Components\Section::make(__('admin.size_chart.sizes'))
                ->description(__('admin.size_chart.sizes_desc'))
                ->schema([
                    Forms\Components\Repeater::make('rows')
                        ->relationship()
                        ->schema([
                            Forms\Components\TextInput::make('size')->label(__('admin.size_chart.size'))->required()->placeholder('S')->columnSpan(1),
                            Forms\Components\Select::make('fit')
                                ->label(__('admin.size_chart.fit'))
                                ->options([
                                    'slim' => __('admin.size_chart.fit_slim'),
                                    'regular' => __('admin.size_chart.fit_regular'),
                                    'relaxed' => __('admin.size_chart.fit_relaxed'),
                                    'oversized' => __('admin.size_chart.fit_oversized'),
                                ])
                                ->native(false)->columnSpan(1),
                            Forms\Components\TextInput::make('bust')->label(__('admin.size_chart.bust'))->columnSpan(1),
                            Forms\Components\TextInput::make('waist')->label(__('admin.size_chart.waist'))->columnSpan(1),
                            Forms\Components\TextInput::make('hip')->label(__('admin.size_chart.hip'))->columnSpan(1),
                            Forms\Components\TextInput::make('shoulder')->label(__('admin.size_chart.shoulder'))->columnSpan(1),
                            Forms\Components\TextInput::make('length')->label(__('admin.size_chart.length'))->columnSpan(1),
                            Forms\Components\TextInput::make('inseam')->label(__('admin.size_chart.inseam'))->columnSpan(1),
                        ])
                        ->columns(4)
                        ->orderColumn('sort')
                        ->reorderable()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => $state['size'] ?? null)
                        ->addActionLabel(__('admin.size_chart.add_size')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('admin.common.name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category')->label(__('admin.size_chart.category'))->badge()->placeholder('—'),
                Tables\Columns\TextColumn::make('rows_count')->counts('rows')->label(__('admin.size_chart.count')),
                Tables\Columns\IconColumn::make('active')->label(__('admin.common.active'))->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label(__('admin.common.updated_at'))->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
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
