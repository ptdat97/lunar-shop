<?php

namespace Modules\Catalog\Filament\Resources;

use Filament\Pages\SubNavigationPosition;
use Lunar\Admin\Filament\Resources\ProductResource as BaseProductResource;
use Modules\Catalog\Filament\Pages\ManageProductAvailability;
use Modules\Catalog\Filament\Pages\ManageProductLogistics;
use Modules\Catalog\Filament\Pages\ManageProductPricing;
use Modules\Catalog\Filament\Pages\ManageProductSizing;
use Modules\Catalog\Filament\Pages\ProductEditor;
use Modules\Catalog\Filament\Resources\ProductResource\Pages\ListProducts;

/**
 * Swapped in for Lunar's ProductResource (ModulesServiceProvider $swaps).
 *
 * Products are managed on a compact set of top tabs instead of Lunar's eleven
 * sub-pages. "Chi tiết" (ProductEditor) inlines the drag-drop gallery, simple
 * pricing/stock, status, brand/type/tags, collections, associations, URLs and
 * SEO plus the variants matrix. The remaining Lunar features surface as tabs:
 * "Kho & Vận chuyển" (identifiers + inventory + shipping), "Giá & Thuế"
 * (customer-group pricing + price breaks), "Phạm vi bán" (channels + customer
 * groups) and "Size & Dáng". The logistics/pricing tabs operate on the single
 * variant and hide once a product has real variants — those are then edited
 * per-variant on the ProductVariantResource, which the matrix links into.
 */
class ProductResource extends BaseProductResource
{
    // Top tabs instead of a right-hand rail — frees the full width for the
    // editor's two-column layout.
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getDefaultPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'edit' => ProductEditor::route('/{record}/edit'),
            'logistics' => ManageProductLogistics::route('/{record}/logistics'),
            'pricing' => ManageProductPricing::route('/{record}/pricing'),
            'availability' => ManageProductAvailability::route('/{record}/availability'),
            'sizing' => ManageProductSizing::route('/{record}/sizing'),
        ];
    }

    public static function getDefaultSubNavigation(): array
    {
        return [
            ProductEditor::class,
            ManageProductLogistics::class,
            ManageProductPricing::class,
            ManageProductAvailability::class,
            ManageProductSizing::class,
        ];
    }
}
