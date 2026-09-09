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
                ->rules('min:0')->default(InventoryService::DEFAULT_LOW_THRESHOLD)->width(6),
            Field::number('hold_minutes', __('admin.inventory_settings.hold_minutes'))
                ->rules('min:1')->default(InventoryService::DEFAULT_HOLD_MINUTES)->width(6),
        ];
    }
}
