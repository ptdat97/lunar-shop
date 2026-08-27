<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Filament\Panel;
use Lunar\Admin\Filament\Clusters\Taxes;
use Lunar\Admin\Filament\Resources\CollectionResource;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Filament\Resources\TaxClassResource;
use Lunar\Admin\Filament\Resources\TaxRateResource;
use Lunar\Admin\Filament\Resources\TaxZoneResource;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Product;
use Tests\TestCase;

/**
 * Guards the resource-swap + navigation-group reflection in
 * App\Providers\ModulesServiceProvider.
 *
 * That code reaches into Filament's Panel internals because the public API only
 * ever merges, and it broke silently on the v3 → v4 upgrade: v4 spreads a
 * resource registration across four arrays ($resources, $modelResources,
 * $resourceConfigurations and — new in Lunar 1.5, which ships a Taxes cluster —
 * $clusteredComponents), so resetting one of them left the others serving the
 * vendor classes we had just replaced. Nothing failed loudly; the admin simply
 * showed Lunar's own pages.
 *
 * These assertions are the tripwire for the next Filament upgrade.
 */
class AdminPanelWiringTest extends TestCase
{
    /** Base classes we replace, mapped to the subclass that must win. */
    private const SWAPS = [
        ProductResource::class => \Modules\Catalog\Filament\Resources\ProductResource::class,
        CollectionResource::class => \Modules\Theme\Filament\Resources\CollectionResource::class,
        TaxClassResource::class => \Modules\Theme\Filament\Resources\TaxClassResource::class,
        TaxRateResource::class => \Modules\Theme\Filament\Resources\TaxRateResource::class,
        TaxZoneResource::class => \Modules\Theme\Filament\Resources\TaxZoneResource::class,
    ];

    private function panel(): Panel
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');
        $panel = Filament::getPanel('lunar');
        Filament::setCurrentPanel($panel);

        return $panel;
    }

    public function test_swapped_resources_replace_the_lunar_originals(): void
    {
        $resources = $this->panel()->getResources();

        foreach (self::SWAPS as $base => $ours) {
            $this->assertContains($ours, $resources, "Subclass {$ours} is not registered.");
            $this->assertNotContains($base, $resources, "Lunar's {$base} survived the swap.");
        }
    }

    public function test_no_resource_is_registered_twice(): void
    {
        $resources = $this->panel()->getResources();

        $this->assertSame(
            array_values(array_unique($resources)),
            array_values($resources),
            'A resource is registered more than once — the sidebar will show duplicates.'
        );
    }

    public function test_a_swapped_model_resolves_to_our_subclass(): void
    {
        $panel = $this->panel();

        $this->assertSame(
            \Modules\Catalog\Filament\Resources\ProductResource::class,
            $panel->getModelResource(Product::class),
        );
    }

    /**
     * Lunar 1.5 groups the three tax resources into its own cluster. Because the
     * cluster map is built during registration, it has to follow the swap.
     */
    public function test_the_taxes_cluster_holds_our_subclasses_not_lunars(): void
    {
        $clustered = $this->panel()->getClusteredComponents(Taxes::class);

        foreach ([
            \Modules\Theme\Filament\Resources\TaxClassResource::class,
            \Modules\Theme\Filament\Resources\TaxRateResource::class,
            \Modules\Theme\Filament\Resources\TaxZoneResource::class,
        ] as $ours) {
            $this->assertContains($ours, $clustered);
        }

        foreach (array_keys(self::SWAPS) as $base) {
            $this->assertNotContains($base, $clustered);
        }
    }

    /**
     * Lunar registers its groups with hardcoded English labels, which stop
     * matching the resources' translated getNavigationGroup() under a non-English
     * locale. The provider replaces them wholesale; assert the replacement won,
     * in order, and that no plain-string leftover remains (a string entry makes
     * Filament skip the label lookup for every group after it).
     */
    public function test_navigation_groups_are_ours_translated_and_in_order(): void
    {
        $groups = $this->panel()->getNavigationGroups();

        $labels = array_map(function ($group) {
            $this->assertNotIsString($group, 'A raw string group leaked through from Lunar.');

            $label = $group->getLabel();

            return is_callable($label) ? $label() : $label;
        }, array_values($groups));

        $this->assertSame([
            __('lunarpanel::global.sections.catalog'),
            __('lunarpanel::global.sections.sales'),
            __('lunarpanel::global.sections.content'),
            __('lunarpanel::global.sections.settings'),
        ], $labels);
    }

    private function assertNotIsString(mixed $value, string $message): void
    {
        $this->assertFalse(is_string($value), $message);
    }
}
