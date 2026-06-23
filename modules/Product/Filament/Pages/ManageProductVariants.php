<?php

namespace Modules\Product\Filament\Pages;

use Filament\Support\Facades\FilamentIcon;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\ManageProductVariants as BaseManageProductVariants;
use Modules\Theme\Filament\Resources\ProductVariantResource;

/**
 * Lunar's variants tab only exposes an inline "Edit" modal (variant name), with
 * no way to reach a variant's media. We add a row action that links straight to
 * the variant's Media page so staff can upload per-variant images (which the
 * storefront gallery + option swatches consume). Wired in via
 * ProductSizeExtension::extendPages() — no vendor fork.
 */
class ManageProductVariants extends BaseManageProductVariants
{
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('media')
                    ->label(__('lunarpanel::productvariant.pages.media.title'))
                    ->icon(FilamentIcon::resolve('lunar::media') ?? 'heroicon-o-photo')
                    ->url(fn ($record): string => ProductVariantResource::getUrl('media', [
                        'record' => $record,
                    ])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
