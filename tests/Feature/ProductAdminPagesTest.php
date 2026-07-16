<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\EditProduct;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\ManageProductVariants;
use Lunar\Admin\Models\Staff;
use Modules\Catalog\Filament\Pages\ManageProductSizing;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Products are edited on Lunar's stock pages again (the custom single-page
 * ProductEditor was retired); the fashion "sizing" tab stays. Smoke-mounts
 * the key pages so a wiring regression in the swapped resource is caught.
 */
class ProductAdminPagesTest extends TestCase
{
    use CreatesStorefrontData;

    protected function actingAsAdmin(): void
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');
        Filament::setCurrentPanel(Filament::getPanel('lunar'));
    }

    public function test_lunar_edit_product_page_renders(): void
    {
        $this->seedBaseData();
        $this->actingAsAdmin();

        $product = $this->createProduct();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertOk();
    }

    public function test_lunar_variants_page_renders(): void
    {
        $this->seedBaseData();
        $this->actingAsAdmin();

        $product = $this->createProduct();

        Livewire::test(ManageProductVariants::class, ['record' => $product->getRouteKey()])
            ->assertOk();
    }

    public function test_sizing_tab_still_renders(): void
    {
        $this->seedBaseData();
        $this->actingAsAdmin();

        $product = $this->createProduct();

        Livewire::test(ManageProductSizing::class, ['record' => $product->getRouteKey()])
            ->assertOk();
    }
}
