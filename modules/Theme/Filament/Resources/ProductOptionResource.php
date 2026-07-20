<?php

namespace Modules\Theme\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Admin\Filament\Resources\ProductOptionResource as BaseProductOptionResource;
use Modules\Catalog\Models\ProductOption;
use Modules\Theme\Filament\Resources\ProductOptionResource\Pages;

/**
 * Product Options belong with the catalog (sizes, colours…), not Settings.
 *
 * Also surfaces `display_type` — added by Modules\Catalog\Models\ProductOption
 * and stored in the option's meta — in both the form and the table. This lives
 * here rather than in Lunar's own resource so the vendor package stays
 * unforked (extend, don't fork).
 */
class ProductOptionResource extends BaseProductOptionResource
{
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.catalog');
    }

    /**
     * Point at our own pages: a Filament page resolves its form and table from
     * its `$resource`, and the vendor pages hardcode Lunar's resource — so
     * without these the display_type field would render but never save.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductOptions::route('/'),
            'create' => Pages\CreateProductOption::route('/create'),
            'edit' => Pages\EditProductOption::route('/{record}/edit'),
        ];
    }

    /**
     * Options for the display_type select, labelled from the project lang
     * files: text chips, colour picker (hex + optional swatch image), or
     * image swatch. Drives both the storefront picker and the product
     * editor's variant builder.
     *
     * @return array<string, string>
     */
    public static function displayTypeOptions(): array
    {
        return collect(ProductOption::DISPLAY_TYPES)
            ->mapWithKeys(fn (string $type) => [$type => __("admin.options.display_types.{$type}")])
            ->all();
    }

    protected static function getMainFormComponents(): array
    {
        return [
            ...parent::getMainFormComponents(),
            static::getDisplayTypeFormComponent(),
        ];
    }

    protected static function getDisplayTypeFormComponent(): Component
    {
        return Forms\Components\Select::make('display_type')
            ->label(__('admin.options.display_type'))
            ->options(static::displayTypeOptions())
            ->default('text')
            ->required()
            ->native(false)
            ->helperText(__('admin.options.display_type_hint'));
    }

    /**
     * Append the display_type badge to Lunar's default columns. pushColumns()
     * appends where columns() would reset the whole layout.
     */
    public static function getDefaultTable(Table $table): Table
    {
        return parent::getDefaultTable($table)->pushColumns([
            Tables\Columns\TextColumn::make('display_type')
                ->label(__('admin.options.display_type'))
                ->badge()
                ->formatStateUsing(fn (string $state) => static::displayTypeOptions()[$state] ?? $state),
        ]);
    }
}
