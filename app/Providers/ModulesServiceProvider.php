<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Resources as Lunar;
use Lunar\Admin\LunarPanelManager;
use Modules\Theme\Filament\Resources as Custom;

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
        'Location',
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
        'FileManager',
        'Search',
        'Recommend',
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

        // Fashion sizing: add Size Chart + Material managers to the product editor.
        // Extensions are read while resources build, so register before panel().
        \Lunar\Admin\Support\Facades\LunarPanel::extensions([
            \Lunar\Admin\Filament\Resources\ProductResource::class => \Modules\Product\Filament\Extensions\ProductSizeExtension::class,
        ]);

        // Swap selected Lunar resources for our subclasses (custom navigation /
        // grouping). Keyed by the base class we drop → the replacement we add.
        // Re-grouped so the sidebar mirrors how the data is actually organised:
        //  - Catalog: ProductType (standalone), ProductOption, AttributeGroup,
        //    Tag, Collection, ProductVariant (the last two had no group at all).
        //  - Sales: CustomerGroup (was under Settings, next to Customers now).
        //  - Settings: Tax Class/Rate/Zone kept here but hidden from the menu
        //    (managed via the consolidated "Taxes" page).
        $swaps = [
            Lunar\ProductTypeResource::class => Custom\ProductTypeResource::class,
            Lunar\ProductOptionResource::class => Custom\ProductOptionResource::class,
            Lunar\AttributeGroupResource::class => Custom\AttributeGroupResource::class,
            Lunar\TagResource::class => Custom\TagResource::class,
            Lunar\CollectionResource::class => Custom\CollectionResource::class,
            Lunar\ProductVariantResource::class => Custom\ProductVariantResource::class,
            Lunar\CustomerGroupResource::class => Custom\CustomerGroupResource::class,
            Lunar\TaxClassResource::class => Custom\TaxClassResource::class,
            Lunar\TaxRateResource::class => Custom\TaxRateResource::class,
            Lunar\TaxZoneResource::class => Custom\TaxZoneResource::class,
        ];

        \Lunar\Admin\Support\Facades\LunarPanel::panel(function ($panel) use ($pages, $extraResources, $swaps) {
            // Filament Panel::resources() merges, so we use reflection to reset the array.
            $replacement = collect(LunarPanelManager::getResources())
                ->reject(fn ($r) => isset($swaps[$r]))
                ->merge(array_values($swaps))
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
