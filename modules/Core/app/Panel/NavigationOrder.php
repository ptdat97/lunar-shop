<?php

namespace Modules\Core\Panel;

use Lunar\Panel\Navigation\NavigationRegistry;
use Lunar\Panel\Sections\SectionExtension;

/**
 * Puts Lunar's own navigation groups in the order a shop works in.
 *
 * `NavigationRegistry::group()` creates a group the first time it sees the key
 * and ignores every later call, and `NavigationGroup`'s priority is readonly.
 * So a group's position is decided by whoever names it first — and Lunar's own
 * sections name `catalog` and `sales` before any of ours are processed. Both
 * land on the default priority 50, which leaves them tied and sorted by
 * registration order: Catalog above Sales.
 *
 * Sales belongs first. A shop opens the panel to look at today's orders; the
 * catalogue is periodic work. Nothing else in the panel is touched as often as
 * the order list, and it was sitting third.
 *
 * The seam used here is Lunar's documented one — `Panel::extendSection()`. An
 * extension is processed immediately after the section it extends, and
 * `dashboard` is processed before `catalog` and `sales`, so naming the groups
 * from here gets in first without forking anything.
 *
 * If a future Lunar release reorders its sections, `group()` finds the groups
 * already created and these calls become no-ops: the sidebar falls back to
 * Lunar's own order and nothing breaks. That is a quiet regression rather than
 * a loud one, which is exactly why PanelNavigationOrderTest asserts the
 * resulting order instead of trusting this to keep working.
 */
class NavigationOrder extends SectionExtension
{
    /**
     * Lunar's groups, in the order a shop uses them. Groups this project owns
     * carry their own priorities — see ShopSection::GROUPS.
     */
    private const GROUPS = [
        'sales' => ['label' => 'panel::nav.sales', 'priority' => 10],
        'catalog' => ['label' => 'panel::nav.catalog', 'priority' => 20],
    ];

    public function extends(): string
    {
        // Not an arbitrary choice: this must extend a section Lunar processes
        // BEFORE catalog and sales, and `dashboard` is the first one.
        return 'dashboard';
    }

    public function navigation(NavigationRegistry $registry): void
    {
        foreach (self::GROUPS as $key => $group) {
            $registry->group($key, __($group['label']), priority: $group['priority']);
        }
    }
}
