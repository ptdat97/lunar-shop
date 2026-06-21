<?php

namespace Modules\Theme\Filament\Resources;

use Lunar\Admin\Filament\Resources\TaxClassResource as BaseTaxClassResource;

/**
 * Tax data is managed through the consolidated "Taxes" settings page, so keep
 * this resource in Settings but hidden from the sidebar to avoid duplicate menu
 * entries. Still reachable via its routes. No vendor edits.
 */
class TaxClassResource extends BaseTaxClassResource
{
    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.settings');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
