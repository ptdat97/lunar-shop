<?php

namespace Modules\Theme\Filament\Resources;

use Lunar\Admin\Filament\Resources\TagResource as BaseTagResource;

/**
 * Tags label products — catalog data, not a system Setting. Move from Settings
 * into Catalog (consistent with Product Options / Attribute Groups). No vendor edits.
 */
class TagResource extends BaseTagResource
{
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.catalog');
    }
}
