<?php

namespace Modules\Theme\Filament\Resources\ProductOptionResource\Pages;

use Lunar\Admin\Filament\Resources\ProductOptionResource\Pages\EditProductOption as BaseEditProductOption;
use Modules\Theme\Filament\Resources\ProductOptionResource;

/**
 * Rebinds the page to our ProductOptionResource.
 *
 * Filament resolves a page's form from its own `$resource`, which the vendor
 * page hardcodes to Lunar's resource — so without this subclass the extra
 * `display_type` field would render but never save. Behaviour is otherwise
 * inherited unchanged.
 */
class EditProductOption extends BaseEditProductOption
{
    protected static string $resource = ProductOptionResource::class;
}
