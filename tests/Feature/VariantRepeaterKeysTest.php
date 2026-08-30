<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Asset;
use Modules\Catalog\Filament\Pages\ManageProductVariants;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Filament repeaters key their items by UUID, and the browser binds to those
 * keys: `data.skus.<uuid>.status`, `data.skus.<uuid>.images`, and so on.
 *
 * `generateCombinations()` writes `$this->data['skus']` itself rather than going
 * through the form, so it has to produce the same shape. Handing back a plain
 * list (keys 0, 1, 2…) leaves every Alpine binding pointing at a path that no
 * longer exists:
 *
 *   Livewire Entangle Error: Livewire property
 *   ['data.skus.4c648266-….status'] cannot be found on component
 *
 * The visible symptom is the media picker: choosing an image in the modal writes
 * to `data.skus.<uuid>.images`, and with the keys gone the write lands nowhere —
 * the picker looks like it simply does not work.
 */
class VariantRepeaterKeysTest extends TestCase
{
    use CreatesStorefrontData;

    private function actingAsAdmin(): void
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');
        Filament::setCurrentPanel(Filament::getPanel('lunar'));
    }

    /** @return array<int, array<string, mixed>> */
    private function variables(): array
    {
        return [
            ['name' => ['en' => 'Size'], 'display_type' => 'text', 'values' => [
                ['name' => ['en' => 'S']],
                ['name' => ['en' => 'M']],
            ]],
        ];
    }

    private function assertKeysAreUuids(array $skus, string $context): void
    {
        $this->assertNotEmpty($skus, "No SKU rows to check ({$context}).");

        foreach (array_keys($skus) as $key) {
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                (string) $key,
                "SKU rows are keyed by '{$key}' instead of a UUID ({$context}) — every "
                .'Alpine binding under data.skus.* breaks, and the media picker stops writing.',
            );
        }
    }

    public function test_generating_combinations_keys_rows_by_uuid(): void
    {
        $this->seedBaseData();
        $this->actingAsAdmin();

        $product = $this->createProduct();

        $component = Livewire::test(ManageProductVariants::class, ['record' => $product->getRouteKey()])
            ->set('data.variables', $this->variables())
            ->call('generateCombinations');

        $this->assertKeysAreUuids($component->get('data.skus'), 'after generateCombinations');
    }

    /** Regenerating over existing rows must not fall back to list keys either. */
    public function test_regenerating_keeps_uuid_keys(): void
    {
        $this->seedBaseData();
        $this->actingAsAdmin();

        $product = $this->createProduct();

        $component = Livewire::test(ManageProductVariants::class, ['record' => $product->getRouteKey()])
            ->set('data.variables', $this->variables())
            ->call('generateCombinations')
            ->call('generateCombinations');

        $this->assertKeysAreUuids($component->get('data.skus'), 'after a second generateCombinations');
    }

    /**
     * The reason the keys matter: edits in progress are matched by combination,
     * so regenerating must not lose what the user already typed.
     */
    public function test_regenerating_preserves_edits_in_progress(): void
    {
        $this->seedBaseData();
        $this->actingAsAdmin();

        $product = $this->createProduct();

        $component = Livewire::test(ManageProductVariants::class, ['record' => $product->getRouteKey()])
            ->set('data.variables', $this->variables())
            ->call('generateCombinations');

        $skus = $component->get('data.skus');
        $firstKey = array_key_first($skus);

        $component->set("data.skus.{$firstKey}.price", 12345)
            ->call('generateCombinations');

        $prices = collect($component->get('data.skus'))->pluck('price');

        $this->assertContains(12345, $prices, 'A price typed before regenerating was lost.');
    }

    /** Rows loaded from a saved product are keyed the same way. */
    public function test_rows_loaded_from_the_product_are_keyed_by_uuid(): void
    {
        $this->seedBaseData();
        $this->actingAsAdmin();

        $product = $this->createProduct();

        $component = Livewire::test(ManageProductVariants::class, ['record' => $product->getRouteKey()]);

        $this->assertKeysAreUuids($component->get('data.skus'), 'on initial fill');
    }

    /**
     * Picking an image on a generated row and saving it does reach the SKU.
     *
     * Note what this does NOT prove: it stays green even with the keys broken,
     * because `->set()` writes to whatever path the test names — it cannot
     * reproduce a browser holding a stale Alpine binding. The guard against the
     * entangle bug is the key-shape assertion above; this covers the save path
     * that the existing per-SKU image test skips by calling the builder service
     * directly.
     */
    public function test_an_image_picked_on_a_generated_row_is_saved(): void
    {
        $this->seedBaseData();
        $this->actingAsAdmin();

        $product = $this->createProduct();

        $asset = Asset::create([]);
        $asset->addMedia(UploadedFile::fake()->image('front.png', 400, 400))
            ->preservingOriginal()
            ->toMediaCollection(config('lunar.media.collection', 'images'));

        $component = Livewire::test(ManageProductVariants::class, ['record' => $product->getRouteKey()])
            ->set('data.variables', $this->variables())
            ->call('generateCombinations');

        $key = array_key_first($component->get('data.skus'));

        // Price is required and must be >= 1; fill every row so the save is
        // rejected for nothing but the thing under test.
        foreach (array_keys($component->get('data.skus')) as $row) {
            $component->set("data.skus.{$row}.price", 1000);
        }

        // The media modal writes to exactly this path.
        $component->set("data.skus.{$key}.images", [$asset->id])
            ->call('save')
            ->assertHasNoErrors();

        $saved = $product->fresh()->skus()
            ->get()
            ->first(fn ($sku) => $sku->images === [$asset->id]);

        $this->assertNotNull(
            $saved,
            'The image written at data.skus.<uuid>.images never reached the SKU.',
        );
    }
}
