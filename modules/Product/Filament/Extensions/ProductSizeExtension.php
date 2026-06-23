<?php

namespace Modules\Product\Filament\Extensions;

use Lunar\Admin\Filament\Resources\ProductResource\Pages\ManageProductVariants as BaseManageProductVariants;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Modules\Product\Filament\Pages\ManageProductSizing;
use Modules\Product\Filament\Pages\ManageProductVariants;

/**
 * Extends Lunar's ProductResource (without forking it) to add a "Size & Fit"
 * sub-page where staff pick a reusable size chart and edit material/care, and
 * to swap the variants tab for one that links each variant to its Media page
 * (per-variant images — consumed by the storefront gallery + option swatches).
 * No relation managers are used, so there is no deferred Livewire load.
 */
class ProductSizeExtension extends ResourceExtension
{
    /**
     * @param  array<string, mixed>  $pages
     * @return array<string, mixed>
     */
    public function extendPages(array $pages): array
    {
        $pages['sizing'] = ManageProductSizing::route('/{record}/sizing');

        // Override Lunar's variants tab so each row can reach variant Media.
        $pages['variants'] = ManageProductVariants::route('/{record}/variants');

        return $pages;
    }

    /**
     * @param  array<int, class-string>  $pages
     * @return array<int, class-string>
     */
    public function extendSubNavigation(array $pages): array
    {
        // The sub-navigation still points at Lunar's base variants page, but
        // getPages() now registers ours under the same 'variants' key — so the
        // base class is no longer a registered page. Swap it here too, keeping
        // its position, or Filament throws "page is not registered".
        $pages = array_map(
            fn ($page) => $page === BaseManageProductVariants::class ? ManageProductVariants::class : $page,
            $pages,
        );

        $pages[] = ManageProductSizing::class;

        return $pages;
    }
}
