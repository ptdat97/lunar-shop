<?php

namespace Modules\Theme\Filament\Resources;

use Lunar\Admin\Filament\Resources\ProductTypeResource as BaseProductTypeResource;

class ProductTypeResource extends BaseProductTypeResource
{
    /**
     * Do not nest under any parent item — Product Types becomes a standalone
     * menu entry within the Catalog navigation group, right after Products.
     */
    protected static ?int $navigationSort = 2;

    public static function getNavigationParentItem(): ?string
    {
        return null;
    }
}
