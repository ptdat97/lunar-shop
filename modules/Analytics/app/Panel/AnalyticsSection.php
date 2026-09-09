<?php

namespace Modules\Analytics\Panel;

use Lunar\Panel\Sections\Section;

/**
 * The Analytics module's presence on the panel: one dashboard card, no screens.
 *
 * A section of its own rather than a widget bolted onto the shop's section —
 * Core has no business importing from a feature module, and `widgets()` is the
 * documented seam for exactly this.
 *
 * Its Vue component ships in the shop's single add-on bundle (registered in
 * resources/js/panel/index.js); one bundle serving several sections is the
 * arrangement described in docs/architecture/panel-addon.md.
 */
class AnalyticsSection extends Section
{
    public function key(): string
    {
        return 'analytics';
    }

    public function label(): string
    {
        return __('admin.analytics.title');
    }

    public function widgets(): array
    {
        return [LifetimeWidget::class];
    }
}
