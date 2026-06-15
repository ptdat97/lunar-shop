<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModulesServiceProvider extends ServiceProvider
{
    /**
     * Modules registered in the application.
     *
     * Each module lives in modules/<Name> with namespace Modules\<Name>
     * and a Providers\<Name>ServiceProvider. Order matters when one module
     * depends on bindings from another (e.g. Hook before others).
     *
     * @var list<string>
     */
    protected array $modules = [
        'Hook',
        'Theme',
        'Catalog',
        'Product',
        'Collection',
        'Inventory',
        'Pricing',
        'Cart',
        'Checkout',
        'Customer',
        'Order',
        'CMS',
        'SectionBuilder',
        'Menu',
        'Media',
        'Search',
        'Promotion',
        'Shipping',
        'Payment',
        'Analytics',
    ];

    public function register(): void
    {
        foreach ($this->modules as $module) {
            $provider = "Modules\\{$module}\\Providers\\{$module}ServiceProvider";

            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }

        $this->registerLunarPanel();
    }

    /**
     * Register Lunar's admin panel after all modules — so module-contributed
     * Filament pages (collected in AdminPages) are included. The panel closure
     * runs immediately inside register(), hence why this must come last.
     */
    protected function registerLunarPanel(): void
    {
        $pages = \Modules\Theme\Support\AdminPages::all();
        $resources = \Modules\Theme\Support\AdminPages::resources();

        \Lunar\Admin\Support\Facades\LunarPanel::panel(function ($panel) use ($pages, $resources) {
            if ($pages) {
                $panel->pages($pages);
            }
            if ($resources) {
                $panel->resources($resources);
            }

            return $panel;
        })->register();
    }
}
