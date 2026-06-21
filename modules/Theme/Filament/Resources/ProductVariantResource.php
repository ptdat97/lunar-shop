<?php

namespace Modules\Theme\Filament\Resources;

use Lunar\Admin\Filament\Resources\ProductVariantResource as BaseProductVariantResource;

/**
 * Product Variants are catalog data. Base resource ships with no navigation
 * group; place it in Catalog. No vendor edits.
 */
class ProductVariantResource extends BaseProductVariantResource
{
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.catalog');
    }
}
