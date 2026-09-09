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

    public function navigation(NavigationRegistry $registry): void
    {
        $registry->group('shop-content', __('panel.section'), priority: 30);

        foreach ($this->registry->forSection($this->key()) as $resource) {
            $registry->addItem('shop-content', new NavigationItem(
                key: $resource->key(),
                label: $resource->label(),
                icon: $resource->icon(),
                route: $resource->routeName('index'),
                permission: $resource->permission(),
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
