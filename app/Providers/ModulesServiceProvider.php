<?php

namespace App\Providers;

use Filament\Navigation\NavigationGroup;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Resources as Lunar;
use Lunar\Admin\LunarPanelManager;
use Lunar\Admin\Support\Facades\LunarPanel;
use Modules\Catalog\Filament\Resources as Catalog;
use Modules\Core\Support\AdminPages;
use Modules\Theme\Filament\Resources as Custom;

/**
 * Wires Lunar's admin panel together from what the modules contribute.
 *
 * The modules themselves are discovered and registered by nwidart/laravel-modules
 * from each modules/<Name>/module.json, in ascending `priority` order (that file
 * is the single source of truth for load order — Notification must come after
 * Order, whose domain events it listens for). nwidart's provider is package
 * auto-discovered, so every module is already registered by the time this
 * provider's register() runs.
 */
class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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

        // Swap selected Lunar resources for our subclasses (custom navigation /
        // grouping). Keyed by the base class we drop → the replacement we add.
        // Re-grouped so the sidebar mirrors how the data is actually organised:
        //  - Catalog: ProductType (standalone), ProductOption, AttributeGroup,
        //    Tag, Collection, ProductVariant (the last two had no group at all).
        //  - Sales: CustomerGroup (was under Settings, next to Customers now).
        //  - Settings: Tax Class/Rate/Zone kept here but hidden from the menu
        //    (managed via the consolidated "Taxes" page).
        $swaps = [
            // Single-page product editor (BeikeShop-style) — see Catalog module.
            Lunar\ProductResource::class => Catalog\ProductResource::class,
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
            // With `filament:cache-components` the panel already restored the
            // cached component arrays back in Panel::id(), and every registration
            // call below is deliberately a no-op. That cache was built by a
            // console run — where hasCachedComponents() is false — so it already
            // holds the swapped list. Touching the arrays here would wipe it on
            // every web request.
            if (! $panel->hasCachedComponents()) {
                $replacement = collect(LunarPanelManager::getResources())
                    ->reject(fn ($r) => isset($swaps[$r]))
                    ->merge(array_values($swaps))
                    ->merge($extraResources)
                    ->values()
                    ->toArray();

                // Panel::resources() MERGES, so the resources we swap out have to
                // be cleared first. Under Filament v4 that is no longer one array:
                // resources() also feeds $resourceConfigurations and, via
                // registerToCluster(), $clusteredComponents. Resetting only
                // $resources leaves those pointing at the classes we just
                // replaced — Lunar 1.5's new Taxes cluster hit exactly that and
                // kept serving the vendor TaxClass/TaxZone/TaxRate resources.
                //
                // So clear what is purely resource-derived, prune only OUR swapped
                // classes out of the cluster map (it holds clustered *pages* too,
                // which must survive), then register through the public API and
                // let v4 rebuild its own bookkeeping: resources() resets
                // $modelResources itself and re-runs registerToCluster().
                (function () use ($swaps) {
                    $this->resources = [];
                    $this->resourceConfigurations = [];

                    foreach ($this->clusteredComponents as $cluster => $components) {
                        $this->clusteredComponents[$cluster] = array_values(
                            array_filter($components, fn ($c) => ! isset($swaps[$c]))
                        );
                    }
                })->call($panel);

                $panel->resources($replacement);
            }

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

            // Filament v4 stopped shipping a build that contains every Tailwind
            // class, so the utility classes in our own admin Blade views are only
            // compiled if a custom theme lists them as @source. Without this the
            // module settings pages render unstyled. Lunar's own panel CSS is a
            // separate, pre-compiled asset and is unaffected.
            $panel->viteTheme('resources/css/filament/lunar/theme.css');

            return $panel;
        })->register();
    }
}
