<?php

namespace Tests\Feature;

use Lunar\Core\Models\Staff;
use Lunar\Panel\PanelManager;
use Modules\Catalog\Models\ProductMaterial;
use Modules\Catalog\Models\SizeChart;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * "Size & Fit" on Lunar's own product editor.
 *
 * The panel's product screen is first-party and stays untouched; this rides in
 * through the slot registry — a component in a named zone of that page. Nothing
 * here overrides a panel screen, and removing this section would leave the
 * editor working, minus one sidebar card.
 */
class PanelProductSizingTest extends TestCase
{
    use CreatesStorefrontData;

    private function actingAsAdmin(): static
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');

        return $this;
    }

    /** The slot has to land on the page it names, or it renders nowhere. */
    public function test_the_slot_is_registered_on_the_product_edit_page(): void
    {
        $staff = Staff::factory()->create(['admin' => true]);

        $slots = app(PanelManager::class)->slots()->forPage('products.edit', $staff);

        $components = collect($slots)->flatten(1)->pluck('component')->all();

        $this->assertContains('shop::ProductSizing', $components);

        // …and nowhere else, so it does not turn up on the orders screen.
        $this->assertEmpty(app(PanelManager::class)->slots()->forPage('orders.edit', $staff));
    }

    public function test_assigning_a_chart_and_material_persists_both(): void
    {
        $product = $this->createProduct();
        $chart = SizeChart::create(['name' => 'Áo nữ', 'category' => 'tops', 'active' => true]);

        $this->actingAsAdmin()
            ->put(route('panel.shop.products.sizing.update', $product->id), [
                'size_chart_id' => $chart->id,
                'material' => [
                    'material' => 'Cotton',
                    'composition' => '95% Cotton, 5% Elastane',
                    'stretch' => 'slight',
                    'care_instruction' => 'Giặt máy nước lạnh',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($chart->id, $product->fresh()->sizeChart()->first()->id);
        $this->assertSame('Cotton', $product->fresh()->material->material);
        $this->assertSame('slight', $product->fresh()->material->stretch);
    }

    /** One chart per product: assigning again replaces, never accumulates. */
    public function test_reassigning_replaces_rather_than_accumulates(): void
    {
        $product = $this->createProduct();
        $first = SizeChart::create(['name' => 'Áo', 'active' => true]);
        $second = SizeChart::create(['name' => 'Quần', 'active' => true]);

        foreach ([$first, $second] as $chart) {
            $this->actingAsAdmin()
                ->put(route('panel.shop.products.sizing.update', $product->id), [
                    'size_chart_id' => $chart->id,
                ])->assertSessionHasNoErrors();
        }

        $this->assertSame(1, $product->fresh()->sizeChart()->count());
        $this->assertSame($second->id, $product->fresh()->sizeChart()->first()->id);

        // And blank clears it.
        $this->actingAsAdmin()
            ->put(route('panel.shop.products.sizing.update', $product->id), ['size_chart_id' => null])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $product->fresh()->sizeChart()->count());
    }

    /**
     * Clearing every material field removes the row. A row of nulls would keep
     * the storefront rendering an empty "Material & care" block.
     */
    public function test_clearing_every_material_field_removes_the_row(): void
    {
        $product = $this->createProduct();

        ProductMaterial::create(['product_id' => $product->id, 'material' => 'Linen']);

        $this->actingAsAdmin()
            ->put(route('panel.shop.products.sizing.update', $product->id), [
                'material' => ['material' => '', 'composition' => ''],
            ])->assertSessionHasNoErrors();

        $this->assertNull($product->fresh()->material);
    }

    /** The slot fetches its own state, because slot props cannot know the record. */
    public function test_the_slot_endpoint_returns_this_products_current_sizing(): void
    {
        $product = $this->createProduct();
        $chart = SizeChart::create(['name' => 'Áo nữ', 'active' => true]);

        $product->sizeChart()->sync([$chart->id]);
        ProductMaterial::create(['product_id' => $product->id, 'material' => 'Cotton', 'stretch' => 'none']);

        $this->actingAsAdmin()
            ->getJson(route('panel.shop.products.sizing.show', $product->id))
            ->assertOk()
            ->assertJson([
                'size_chart_id' => $chart->id,
                'material' => ['material' => 'Cotton', 'stretch' => 'none'],
            ]);
    }

    public function test_an_unknown_chart_is_rejected(): void
    {
        $product = $this->createProduct();

        $this->actingAsAdmin()
            ->put(route('panel.shop.products.sizing.update', $product->id), ['size_chart_id' => 99999])
            ->assertSessionHasErrors('size_chart_id');
    }

    public function test_sizing_requires_the_products_permission(): void
    {
        $product = $this->createProduct();
        $staff = Staff::factory()->create(['admin' => false]);

        $this->actingAs($staff, 'staff')
            ->put(route('panel.shop.products.sizing.update', $product->id), [])
            ->assertForbidden();
    }
}
