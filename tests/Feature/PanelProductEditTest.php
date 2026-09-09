<?php

namespace Tests\Feature;

use Lunar\Core\Models\Staff;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Lunar's own product editor, with this shop's extensions on it.
 *
 * It exists because of a real 500. The SKU → variant migration added an
 * `images` column to `lunar_product_variants` holding Media Library Asset ids.
 * That name shadowed `ProductVariant::images()`, Lunar's BelongsToMany to Media
 * — and in Eloquent a real column always wins over a relation of the same name.
 * So `getThumbnail()` called `->first()` on an array and every product editor
 * page died.
 *
 * Nothing caught it: the route takes a `{product}` parameter, and the smoke
 * sweep only walks parameterless ones. This is that gap, closed for the screen
 * the shop actually extends.
 */
class PanelProductEditTest extends TestCase
{
    use CreatesStorefrontData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');
    }

    /** The exact shape that broke: a variant carrying image asset ids. */
    public function test_the_editor_renders_for_a_variant_with_image_asset_ids(): void
    {
        $product = $this->createProduct();

        $product->variants->first()->update(['image_asset_ids' => [1, 3, 2]]);

        $this->get("/panel/products/{$product->id}/edit")->assertOk();
    }

    /** And for one with none, which is the other half of the same code path. */
    public function test_the_editor_renders_for_a_variant_without_them(): void
    {
        $product = $this->createProduct();

        $this->get("/panel/products/{$product->id}/edit")->assertOk();
    }

    /**
     * The column must not shadow the relation again. Asserting on the types is
     * what pins the bug: `images` has to be Lunar's media relation, and the
     * shop's asset ids have to live under their own name.
     */
    public function test_the_shops_column_does_not_shadow_lunars_media_relation(): void
    {
        $variant = $this->createProduct()->variants->first();

        $variant->update(['image_asset_ids' => [7]]);

        $fresh = $variant->fresh();

        $this->assertSame([7], $fresh->image_asset_ids);
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $fresh->images);

        // getThumbnail() is what the panel calls, and what died on the array.
        $this->assertNull($fresh->getThumbnail());
    }

    /**
     * The schema-level guard. A column called `images` on the variants table can
     * only ever shadow Lunar's relation again, so its absence is asserted
     * directly rather than left to whoever next adds a column.
     */
    public function test_no_column_shadows_the_media_relation_on_variants(): void
    {
        $table = config('lunar.database.table_prefix').'product_variants';

        $shadowed = array_values(array_filter(
            \Illuminate\Support\Facades\Schema::getColumnListing($table),
            fn (string $column) => method_exists(\Lunar\Core\Models\ProductVariant::class, $column),
        ));

        $this->assertSame(
            [],
            $shadowed,
            'Cột trùng tên method của ProductVariant sẽ che mất quan hệ: '.implode(', ', $shadowed),
        );
    }

    /** The Size & Fit card the shop injects must not break the page either. */
    public function test_the_editor_renders_with_the_shops_sizing_slot(): void
    {
        $product = $this->createProduct();

        $this->get("/panel/products/{$product->id}/edit")
            ->assertOk()
            ->assertSee('shop::ProductSizing', false);
    }
}
