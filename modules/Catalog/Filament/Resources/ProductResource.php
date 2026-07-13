<?php

namespace Modules\Catalog\Filament\Resources;

use Filament\Pages\SubNavigationPosition;
use Lunar\Admin\Filament\Resources\ProductResource as BaseProductResource;
use Lunar\Admin\Filament\Resources\ProductResource\Pages as LunarPages;
use Modules\Catalog\Filament\Pages\ManageProductImages;
use Modules\Catalog\Filament\Pages\ManageProductSizing;
use Modules\Catalog\Filament\Pages\ProductEditor;

/**
 * Swapped in for Lunar's ProductResource (ModulesServiceProvider $swaps).
 *
 * Products are managed on a single-page editor (ProductEditor) instead of
 * Lunar's eleven sub-pages: gallery, pricing, stock, variants matrix, URLs,
 * collections, associations and SEO all live inline. Only "Size & Fit"
 * (size charts + material) remains a separate tab. Advanced per-variant
 * detail (identifiers, price breaks, variant media) stays on the separate
 * ProductVariantResource, which the variants matrix links into.
 */
class ProductResource extends BaseProductResource
{
    // "Chi tiết" / "Size & Dáng" as top tabs instead of a right-hand rail —
    // frees the full width for the editor's two-column layout.
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getDefaultPages(): array
    {
        return [
            'index' => LunarPages\ListProducts::route('/'),
            'edit' => ProductEditor::route('/{record}/edit'),
            'images' => ManageProductImages::route('/{record}/images'),
            'sizing' => ManageProductSizing::route('/{record}/sizing'),
        ];
    }

    public static function getDefaultSubNavigation(): array
    {
        return [
            ProductEditor::class,
            ManageProductImages::class,
            ManageProductSizing::class,
        ];
    }
}
