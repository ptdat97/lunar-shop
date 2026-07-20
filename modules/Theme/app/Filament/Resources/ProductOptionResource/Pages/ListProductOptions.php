<?php

namespace Modules\Theme\Filament\Resources\ProductOptionResource\Pages;

use Lunar\Admin\Filament\Resources\ProductOptionResource\Pages\ListProductOptions as BaseListProductOptions;
use Modules\Theme\Filament\Resources\ProductOptionResource;

/**
 * Rebinds the list page to our ProductOptionResource so the table shows the
 * extra `display_type` column.
 */
class ListProductOptions extends BaseListProductOptions
{
    protected static string $resource = ProductOptionResource::class;
}
