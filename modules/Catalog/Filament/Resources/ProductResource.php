<?php

namespace Modules\Catalog\Filament\Resources;

use Lunar\Admin\Filament\Resources\ProductResource as BaseProductResource;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\ManageProductVariants as LunarManageProductVariants;
use Modules\Catalog\Filament\Pages\ManageProductSizing;
use Modules\Catalog\Filament\Pages\ManageProductVariants;
use Modules\Catalog\Filament\Resources\ProductResource\Pages\ListProducts;

/**
 * Swapped in for Lunar's ProductResource (ModulesServiceProvider $swaps).
 *
 * Products are edited on Lunar's stock pages (EditProduct + its manage
 * sub-pages: media, pricing, inventory, shipping, URLs, collections,
 * associations, …). Local additions / overrides on top of stock:
 *   - ListProducts: simplified create-modal flow (name/type/SKU, optional
 *     price, publish choice) instead of Lunar's draft-only modal.
 *   - "Biến thể" (ManageProductVariants): the VaniCommerce-style flexible SKU
 *     builder, REPLACING Lunar's options → variant-matrix widget page.
 *   - "Size & Dáng" (ManageProductSizing): fashion size charts — no Lunar
 *     equivalent.
 */
class ProductResource extends BaseProductResource
{
    public static function getDefaultPages(): array
    {
        $pages = array_merge(parent::getDefaultPages(), [
            'index' => ListProducts::route('/'),
            // Override Lunar's variants page with our SKU builder (same slug so
            // existing links keep working).
            'variants' => ManageProductVariants::route('/{record}/variants'),
            'sizing' => ManageProductSizing::route('/{record}/sizing'),
        ]);

        return $pages;
    }

    public static function getDefaultSubNavigation(): array
    {
        // Drop Lunar's variant-matrix page from the sub-nav and slot ours in its
        // place; keep everything else (media, pricing, inventory, …).
        $nav = collect(parent::getDefaultSubNavigation())
            ->reject(fn ($item) => $item === LunarManageProductVariants::class)
            ->push(ManageProductVariants::class)
            ->push(ManageProductSizing::class)
            ->all();

        return $nav;
    }
}
