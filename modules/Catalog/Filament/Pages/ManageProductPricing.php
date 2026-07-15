<?php

namespace Modules\Catalog\Filament\Pages;

use Illuminate\Contracts\Support\Htmlable;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\ManageProductPricing as LunarManageProductPricing;
use Modules\Catalog\Filament\Resources\ProductResource;

/**
 * "Giá & Thuế" tab of the Catalog product editor.
 *
 * Reuses Lunar's pricing page wholesale (tax class/ref form + base-price
 * section + customer-group pricing / price-break relation managers, all keyed
 * to the product's single variant). We only repoint it at the Catalog resource
 * and give it a Vietnamese label. Its inherited shouldRegisterNavigation()
 * hides the tab for products that already have real variants — pricing then
 * lives per-variant on the ProductVariantResource.
 */
class ManageProductPricing extends LunarManageProductPricing
{
    protected static string $resource = ProductResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.editor.pricing');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.editor.pricing');
    }
}
