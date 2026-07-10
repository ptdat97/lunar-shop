<?php

namespace App\Providers;

use Filament\Navigation\NavigationGroup;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Resources as Lunar;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\LunarPanelManager;
use Lunar\Admin\Support\Facades\LunarPanel;
use Modules\Catalog\Filament\Extensions\ProductSizeExtension;
use Modules\Core\Support\AdminPages;
use Modules\Theme\Filament\Resources as Custom;

class ModulesServiceProvider extends ServiceProvider
{
    /**
     * Modules registered in the application.
     *
     * Each module lives in modules/<Name> with namespace Modules\<Name>
     * and a Providers\<Name>ServiceProvider. Order matters when one module
     * depends on bindings from another.
     *
     * @var list<string>
     */
    protected array $modules = [
        'Core',
        'Theme',
        'Catalog',
        'Inventory',
        'Checkout',
        'Customer',
        'Order',
        'Notification',   // listens to Order's domain events
        'Content',
        'Assets',
        'Promotion',
        'Shipping',
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
        $pages = AdminPages::all();
        $extraResources = AdminPages::resources();

        // Fashion sizing: add Size Chart + Material managers to the product editor.
        // Extensions are read while resources build, so register before panel().
        LunarPanel::extensions([
            ProductResource::class => ProductSizeExtension::class,
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

        LunarPanel::panel(function ($panel) use ($pages, $extraResources, $swaps) {
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

            // Lunar's defaultPanel() already registered its group order with
            // hardcoded English labels ('Catalog', 'Sales', NavigationGroup
            // 'Settings'), but resources report their group via the translated
            // lunarpanel::global.sections.* keys. Under a non-English locale
            // (e.g. vi) those labels no longer match, so Filament can't place
            // the groups and they fall to the end of the sidebar.
            //
            // Panel::navigationGroups() MERGES, so calling it would just append
            // our groups after Lunar's stale string entries — and because the
            // first entry is then a plain string, Filament's matcher skips the
            // label lookup entirely. Reset the array via reflection (same trick
            // used for resources above) so only our translated groups remain.
            //
            // Labels are Closures, not __() calls: this closure runs inside
            // register() where the translator isn't bound yet, while Filament
            // resolves a group's label lazily at render time (request scope) and
            // matches it against the resources' getNavigationGroup() output.
            $section = fn (string $key) => __("lunarpanel::global.sections.{$key}");
            $navigationGroups = [
                NavigationGroup::make()->label(fn () => $section('catalog')),
                NavigationGroup::make()->label(fn () => $section('sales')),
                NavigationGroup::make()->label(fn () => $section('content')),
                NavigationGroup::make()->label(fn () => $section('settings'))->collapsed(),
            ];

            (function () use ($navigationGroups) {
                $this->navigationGroups = $navigationGroups;
            })->call($panel);

            if ($pages) {
                $panel->pages($pages);
            }

            return $panel;
        })->register();
    }
}
