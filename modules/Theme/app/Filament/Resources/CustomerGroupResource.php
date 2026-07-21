<?php

namespace Modules\Theme\Filament\Resources;

use Lunar\Admin\Filament\Resources\CustomerGroupResource as BaseCustomerGroupResource;

/**
 * Customer Groups belong with the rest of the customer/sales management, next to
 * Customers — move from Settings into Sales. No vendor edits.
 */
class CustomerGroupResource extends BaseCustomerGroupResource
{
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.sales');
    }
}
