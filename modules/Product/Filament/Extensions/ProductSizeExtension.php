<?php

namespace Modules\Product\Filament\Extensions;

use Lunar\Admin\Support\Extending\ResourceExtension;
use Modules\Product\Filament\Pages\ManageProductSizing;

/**
 * Extends Lunar's ProductResource (without forking it) to add a "Size & Fit"
 * sub-page where staff pick a reusable size chart and edit material/care.
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

        return $pages;
    }

    /**
     * @param  array<int, class-string>  $pages
     * @return array<int, class-string>
     */
    public function extendSubNavigation(array $pages): array
    {
        $pages[] = ManageProductSizing::class;

        return $pages;
    }
}
