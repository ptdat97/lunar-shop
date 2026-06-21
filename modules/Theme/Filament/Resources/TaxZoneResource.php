<?php

namespace Modules\Theme\Filament\Resources;

use Lunar\Admin\Filament\Resources\TaxZoneResource as BaseTaxZoneResource;

/**
 * Managed via the consolidated "Taxes" settings page — kept in Settings but
 * hidden from the sidebar to avoid duplicate menu entries. No vendor edits.
 */
class TaxZoneResource extends BaseTaxZoneResource
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
