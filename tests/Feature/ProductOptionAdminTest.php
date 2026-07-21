<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\Models\ProductOption;
use Modules\Theme\Filament\Resources\ProductOptionResource\Pages\EditProductOption;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Product options carry a display_type (text | color | image), configured on
 * the Product Options admin page and stored in the option's meta.
 */
class ProductOptionAdminTest extends TestCase
{
    use CreatesStorefrontData;

    protected function actingAsAdmin(): void
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');
        Filament::setCurrentPanel(Filament::getPanel('lunar'));
    }

    protected function makeOption(string $handle, string $displayType): ProductOption
    {
        return ProductOption::create([
            'name' => ['en' => ucfirst($handle)],
            'label' => ['en' => ucfirst($handle)],
            'handle' => $handle,
            'shared' => true,
            'display_type' => $displayType,
        ]);
    }

    public function test_display_type_round_trips_through_meta(): void
    {
        $option = $this->makeOption('finish', 'image');

        $this->assertSame('image', $option->fresh()->display_type);

        // Unknown / absent values normalise to text.
        $option->meta = ['display_type' => 'bogus'];
        $option->save();
        $this->assertSame('text', $option->fresh()->display_type);

        $this->assertSame('text', $this->makeOption('plain', 'text')->fresh()->display_type);
    }

    public function test_edit_product_option_page_renders_and_fills_display_type(): void
    {
        $this->seedBaseData();
        $this->actingAsAdmin();

        $option = $this->makeOption('finish', 'image');

        Livewire::test(EditProductOption::class, ['record' => $option->getRouteKey()])
            ->assertOk()
            ->assertFormSet(['display_type' => 'image']);
    }

    public function test_display_type_saves_from_the_edit_page(): void
    {
        $this->seedBaseData();
        $this->actingAsAdmin();

        $option = $this->makeOption('finish', 'text');

        Livewire::test(EditProductOption::class, ['record' => $option->getRouteKey()])
            ->fillForm(['display_type' => 'color'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('color', $option->fresh()->display_type);
    }
}
