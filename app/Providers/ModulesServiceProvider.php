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

        \Lunar\Admin\Support\Facades\LunarPanel::panel(
            fn ($panel) => $pages ? $panel->pages($pages) : $panel
        )->register();
    }
}
