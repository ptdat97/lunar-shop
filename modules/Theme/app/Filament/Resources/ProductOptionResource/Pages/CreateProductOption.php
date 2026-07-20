<?php

namespace Modules\Theme\Filament\Resources\ProductOptionResource\Pages;

use Lunar\Admin\Filament\Resources\ProductOptionResource\Pages\CreateProductOption as BaseCreateProductOption;
use Modules\Theme\Filament\Resources\ProductOptionResource;

/**
 * Rebinds the create page to our ProductOptionResource so `display_type` is
 * part of the saved form. The vendor page's mutateFormDataBeforeCreate() —
 * which forces `shared` — is inherited.
 */
class CreateProductOption extends BaseCreateProductOption
{
    protected static string $resource = ProductOptionResource::class;
}
