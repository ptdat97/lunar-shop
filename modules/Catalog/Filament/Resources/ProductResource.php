<?php

namespace Modules\Catalog\Filament\Resources;

use Lunar\Admin\Filament\Resources\ProductResource as BaseProductResource;
use Modules\Catalog\Filament\Pages\ManageProductSizing;
use Modules\Catalog\Filament\Resources\ProductResource\Pages\ListProducts;

/**
 * Swapped in for Lunar's ProductResource (ModulesServiceProvider $swaps).
 *
 * Products are edited on Lunar's stock pages (EditProduct + its manage
 * sub-pages: media, pricing, inventory, shipping, variants, URLs, collections,
 * associations, …). Local additions on top of stock:
 *   - ListProducts: simplified create-modal flow (name/type/SKU, optional
 *     price, publish choice) instead of Lunar's draft-only modal.
 *   - "Size & Dáng" (ManageProductSizing): fashion size charts — no Lunar
 *     equivalent.
 */
class ProductResource extends BaseProductResource
{
    public static function getDefaultPages(): array
    {
        return array_merge(parent::getDefaultPages(), [
            'index' => ListProducts::route('/'),
            'sizing' => ManageProductSizing::route('/{record}/sizing'),
        ]);
    }

    public static function getDefaultSubNavigation(): array
    {
        return array_merge(parent::getDefaultSubNavigation(), [
            ManageProductSizing::class,
        ]);
    }
}
