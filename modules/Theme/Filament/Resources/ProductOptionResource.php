<?php

namespace Modules\Theme\Filament\Resources;

use Lunar\Admin\Filament\Resources\ProductOptionResource as BaseProductOptionResource;

/**
 * Product Options belong with the catalog (sizes, colours…), not Settings.
 * Subclass only overrides the navigation group; everything else is inherited
 * from Lunar's resource (no vendor edits).
 */
class ProductOptionResource extends BaseProductOptionResource
{
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.catalog');
    }
}
