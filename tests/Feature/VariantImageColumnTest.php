<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Lunar\Core\Models\ProductVariant;
use Modules\Assets\Services\LibraryLinks;
use Modules\Assets\Services\MediaLibraryService;
use Modules\Catalog\Support\VariantImageColumn;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Moving the shop's old `image_asset_ids` column into Lunar's variant images.
 *
 * The column is already gone on a migrated database, so these drive the mover
 * with the id lists the column held — which is the part with decisions in it:
 * the column held two kinds of id, and they must each land correctly.
 */
class VariantImageColumnTest extends TestCase
{
    use CreatesStorefrontData;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
    }

    public function test_the_migration_dropped_the_column(): void
    {
        $this->assertFalse(Schema::hasColumn((new ProductVariant)->getTable(), VariantImageColumn::COLUMN));
    }

    /** What the demo seeder wrote: the product's own gallery media ids. */
    public function test_ids_of_the_products_own_gallery_are_taken_as_those_images(): void
    {
        $product = $this->createProduct();
        $a = $product->addMedia(UploadedFile::fake()->image('a.jpg', 20, 20))->toMediaCollection('images');
        $b = $product->addMedia(UploadedFile::fake()->image('b.jpg', 20, 20))->toMediaCollection('images');
        $variant = $product->variants->first();

        $totals = app(VariantImageColumn::class)->import([$variant->id => [$b->id, $a->id]]);

        $this->assertSame([$b->id, $a->id], $variant->fresh()->images->pluck('id')->all());
        $this->assertSame(['variants' => 1, 'images' => 2, 'dropped' => 0], $totals);
        $this->assertCount(2, $product->fresh()->getMedia('images'), 'no new gallery rows');
    }

    /** What the pre-2.0 picker wrote: library Asset ids — linked into the gallery. */
    public function test_other_ids_are_taken_as_library_files_and_linked_in(): void
    {
        $product = $this->createProduct();
        $asset = app(MediaLibraryService::class)->store(UploadedFile::fake()->image('lib.jpg', 20, 20));
        $variant = $product->variants->first();

        app(VariantImageColumn::class)->import([$variant->id => [['id' => $asset->id], 999999]]);

        $gallery = $product->fresh()->getMedia('images');

        $this->assertCount(1, $gallery);
        $this->assertTrue(LibraryLinks::isLink($gallery->first()));
        $this->assertSame($asset->id, $gallery->first()->getCustomProperty(LibraryLinks::ASSET));
        $this->assertSame([$gallery->first()->id], $variant->fresh()->images->pluck('id')->all());
    }

    /**
     * A linked image remembers its library id — which is what the migration's
     * rollback writes back into the column.
     */
    public function test_a_linked_image_keeps_the_library_id_a_rollback_needs(): void
    {
        $product = $this->createProduct();
        $asset = app(MediaLibraryService::class)->store(UploadedFile::fake()->image('lib.jpg', 20, 20));
        $variant = $product->variants->first();
        $mover = app(VariantImageColumn::class);
        $mover->import([$variant->id => [$asset->id]]);

        $link = $product->fresh()->getMedia('images')->first();

        $this->assertSame($asset->id, $link->getCustomProperty(LibraryLinks::ASSET));
    }
}
