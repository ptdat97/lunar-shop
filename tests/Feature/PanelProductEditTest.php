<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\Staff;
use Modules\Catalog\Services\VariantImages;
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

    /** The shape that once broke it: a variant with images of its own. */
    public function test_the_editor_renders_for_a_variant_with_images(): void
    {
        Storage::fake('media');

        $product = $this->createProduct();
        $photo = $product->addMedia(UploadedFile::fake()->image('front.png', 40, 40))->toMediaCollection('images');
        app(VariantImages::class)->syncVariants([$product->variants->first()], collect([$photo]));

        $this->get("/panel/products/{$product->id}/edit")->assertOk();
    }

    /** And for one with none, which is the other half of the same code path. */
    public function test_the_editor_renders_for_a_variant_without_them(): void
    {
        $product = $this->createProduct();

        $this->get("/panel/products/{$product->id}/edit")->assertOk();
    }

    /**
     * The shop's old `image_asset_ids` column shadowed Lunar's `images()` once
     * (a 500 on every product editor) and then became a second store for the
     * same thing. It is gone: `images` is Lunar's relation, and nothing else.
     */
    public function test_variant_images_are_lunars_relation_and_the_old_column_is_gone(): void
    {
        $variant = $this->createProduct()->variants->first();

        $this->assertFalse(Schema::hasColumn($variant->getTable(), 'image_asset_ids'));
        $this->assertInstanceOf(Collection::class, $variant->fresh()->images);

        // getThumbnail() is what the panel calls, and what died on the array.
        $this->assertNull($variant->fresh()->getThumbnail());
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
            Schema::getColumnListing($table),
            fn (string $column) => method_exists(ProductVariant::class, $column),
        ));

        $this->assertSame(
            [],
            $shadowed,
            'Cột trùng tên method của ProductVariant sẽ che mất quan hệ: '.implode(', ', $shadowed),
        );
    }

    /**
     * `enabled` is Lunar's answer to "is this variant live", and it must be the
     * only one. The SKU builder left a `status` column beside it saying
     * 'published' on every row while 54 variants were disabled — a second,
     * contradicting answer next to the real one.
     */
    public function test_only_lunars_enabled_flag_decides_whether_a_variant_is_live(): void
    {
        $table = config('lunar.database.table_prefix').'product_variants';

        $this->assertTrue(Schema::hasColumn($table, 'enabled'));
        $this->assertFalse(
            Schema::hasColumn($table, 'status'),
            'Cột status thời SKU đã quay lại — lại có hai câu trả lời cho cùng một câu hỏi.',
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
