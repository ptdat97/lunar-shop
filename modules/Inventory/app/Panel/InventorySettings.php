<?php

namespace Modules\Inventory\Panel;

use Modules\Core\Panel\Field;
use Modules\Core\Panel\StoredSettingsGroup;
use Modules\Inventory\Services\InventoryService;

/** When to warn about low stock, and how long a checkout holds it. */
class InventorySettings extends StoredSettingsGroup
{
    public function key(): string
    {
        return 'inventory';
    }

    public function label(): string
    {
        return __('admin.inventory_settings.title');
    }

    public function icon(): string
    {
        return 'boxes';
    }

    public function priority(): int
    {
        return 20;
    }

    public function fields(): array
    {
        return [
            Field::number('low_stock_threshold', __('admin.inventory_settings.low_stock_threshold'))
                ->required()->rules('min:1')
                ->default(InventoryService::DEFAULT_LOW_THRESHOLD)->width(6),
            // The bounds are the service's own constants, not numbers picked
            // here: holdMinutes() clamps what it reads, so a form that accepted
            // 1 would show "saved" while the system quietly used 10.
            Field::number('hold_minutes', __('admin.inventory_settings.hold_minutes'))
                ->required()
                ->rules('min:'.InventoryService::MIN_HOLD_MINUTES, 'max:'.InventoryService::MAX_HOLD_MINUTES)
                ->help(__('admin.inventory_settings.hold_minutes_help'))
                ->default(InventoryService::DEFAULT_HOLD_MINUTES)->width(6),
        ];
    }
}
