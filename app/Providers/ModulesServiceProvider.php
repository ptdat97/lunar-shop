<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Resources\ProductTypeResource as BaseProductTypeResource;
use Lunar\Admin\LunarPanelManager;
use Modules\Theme\Filament\Resources\ProductTypeResource;

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
        $extraResources = \Modules\Theme\Support\AdminPages::resources();

        \Lunar\Admin\Support\Facades\LunarPanel::panel(function ($panel) use ($pages, $extraResources) {
            // Replace the default ProductTypeResource with our custom version
            // so that Product Types appears as a standalone menu item (not nested under Products).
            // Filament Panel::resources() merges, so we must use reflection to reset the array.
            $replacement = collect(LunarPanelManager::getResources())
                ->reject(fn ($r) => $r === BaseProductTypeResource::class)
                ->push(ProductTypeResource::class)
                ->merge($extraResources)
                ->values()
                ->toArray();

            (function () use ($replacement) {
                $this->resources = $replacement;
            })->call($panel);

            if ($pages) {
                $panel->pages($pages);
            }

            return $panel;
        })->register();
    }
}
