<?php

namespace Modules\Inventory\Panel;

use Lunar\Panel\Sections\Section;

/**
 * The Inventory module's presence on the panel dashboard.
 *
 * One card, and only because Lunar has no equivalent: its own LowStockWidget
 * answers "what is running out", while this answers "what is being held and
 * never shipped" — a different failure, and the quieter one.
 */
class InventorySection extends Section
{
    public function key(): string
    {
        return 'shop-inventory';
    }

    public function label(): string
    {
        return __('admin.stale_commitments.title');
    }

    public function widgets(): array
    {
        return [StaleCommitmentsWidget::class];
    }
}
