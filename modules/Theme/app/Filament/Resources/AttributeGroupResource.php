<?php

namespace Modules\Theme\Filament\Resources;

use Lunar\Admin\Filament\Resources\AttributeGroupResource as BaseAttributeGroupResource;

/**
 * Attribute Groups define product/collection attributes — catalog data, not a
 * system Setting. Subclass only overrides the navigation group; everything else
 * is inherited from Lunar's resource (no vendor edits).
 */
class AttributeGroupResource extends BaseAttributeGroupResource
{
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.catalog');
    }
}
