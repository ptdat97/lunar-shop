<?php

namespace Modules\Core\Panel;

use Closure;
use Lunar\Panel\Navigation\NavigationItem;
use Lunar\Panel\Navigation\NavigationRegistry;
use Lunar\Panel\Sections\Section;

/**
 * The shop's own section on the Lunar panel.
 *
 * It owns two things no per-module section should duplicate: the single Vue
 * bundle every declared resource renders through, and the routes for those
 * resources. Modules still declare *what* their screens are (see
 * ResourceRegistry::add in each provider) — this only carries them.
 *
 * One bundle, not one per module: each registered Vite module is a separate
 * <script> tag and manifest in the panel's layout, and the pages share the same
 * two components anyway.
 */
class ShopSection extends Section
{
    public function __construct(
        protected ResourceRegistry $registry,
        protected SettingsRegistry $settings,
    ) {}

    public function key(): string
    {
        return 'shop';
    }

    public function label(): string
    {
        return __('panel.section');
    }

    /**
     * Groups this section owns, with the order they appear in the sidebar.
     *
     * Sales (10) and Catalog (20) are Lunar's, placed by NavigationOrder before
     * Lunar creates them; these sit under both because that is the shape of the
     * work. A shop opens the panel to look at orders, then at products —
     * content and configuration are the occasional visit.
     *
     * @var array<string, array{label: string, priority: int}>
     */
    private const GROUPS = [
        'shop-operations' => ['label' => 'panel.nav.operations', 'priority' => 30],
        'shop-content' => ['label' => 'panel.nav.content', 'priority' => 40],
        // Deliberately far down and alone: read-only diagnostics nobody opens
        // unless something is already wrong.
        'shop-system' => ['label' => 'panel.nav.system', 'priority' => 90],
    ];

    public function navigation(NavigationRegistry $registry): void
    {
        foreach (self::GROUPS as $key => $group) {
            $registry->group($key, __($group['label']), priority: $group['priority']);
        }

        foreach ($this->registry->forSection($this->key()) as $resource) {
            // A resource declares its own group, and `sales` / `catalog` are
            // valid answers: returns are order work, reviews are product work.
            // Everything used to land in one flat group regardless, which put
            // the returns queue next to the redirect table and the scheduler
            // log at the very top of the sidebar.
            $registry->addItem($resource->navigationGroup(), new NavigationItem(
                key: $resource->key(),
                label: $resource->label(),
                icon: $resource->icon(),
                route: $resource->routeName('index'),
                permission: $resource->permission(),
                priority: $resource->navigationPriority(),
                badge: $resource->navigationBadge(),
            ));
        }
    }

    /**
     * The shop's feature settings, in the panel's own settings sidebar rather
     * than the main navigation — an admin looking for "where do I change the
     * payment keys" looks there, next to Lunar's own settings.
     */
    public function settingsNavigation(NavigationRegistry $registry): void
    {
        $registry->group('shop-settings', __('panel.section'), priority: 60);

        foreach ($this->settings->all() as $group) {
            $registry->addItem('shop-settings', new NavigationItem(
                key: 'shop-settings-'.$group->key(),
                label: $group->label(),
                icon: $group->icon(),
                route: 'panel.shop.settings.'.$group->key().'.edit',
                permission: $group->permission(),
                priority: $group->priority(),
            ));
        }
    }

    public function routes(): ?Closure
    {
        $resources = $this->registry->routesFor($this->key());
        $settings = $this->settings->routes();

        return function () use ($resources, $settings): void {
            $resources();
            $settings();
        };
    }

    /**
     * Built by `npm run build:panel` (vite.panel.config.js) straight into
     * public/vendor/lunar-panel/shop/build, so no symlink step is needed and
     * `__buildSourcePath` stays unset.
     */
    public function vite(): array|string|null
    {
        return [
            'input' => 'resources/js/panel/index.js',
            'buildDirectory' => 'vendor/lunar-panel/shop/build',
        ];
    }
}
