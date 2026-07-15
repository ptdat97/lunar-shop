<?php

namespace Modules\Catalog\Filament\Pages;

use Lunar\Admin\Filament\Resources\ProductResource\Pages\ManageProductAvailability as LunarManageProductAvailability;
use Modules\Catalog\Filament\Resources\ProductResource;

/**
 * "Phạm vi bán" tab of the Catalog product editor.
 *
 * Reuses Lunar's availability page (channel + customer-group relation groups on
 * the product's `channels` relationship). Repointed at the Catalog resource
 * with a Vietnamese label. Unlike the logistics/pricing tabs this applies to
 * every product, single- or multi-variant, since availability is per-product.
 */
class ManageProductAvailability extends LunarManageProductAvailability
{
    protected static string $resource = ProductResource::class;

    public function getTitle(): string
    {
        return __('admin.editor.availability');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.editor.availability');
    }
}
