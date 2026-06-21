<?php

namespace Modules\Theme\Filament\Resources;

use Lunar\Admin\Filament\Resources\CollectionResource as BaseCollectionResource;

/**
 * Collections are catalog data. Base resource ships with no navigation group
 * (it fell under "(none)"); place it in Catalog. No vendor edits.
 */
class CollectionResource extends BaseCollectionResource
{
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.catalog');
    }
}
