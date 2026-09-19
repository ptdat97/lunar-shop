<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Lunar\Core\Actions\Media\AddMedia;
use Lunar\Core\Contracts\Actions\Media\AddsMedia;
use Lunar\Core\Models\Asset;
use Lunar\Core\Models\Brand;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\Staff;
use Modules\Assets\Actions\AddMediaThroughLibrary;
use Modules\Assets\Services\ConversionGenerator;
use Modules\Assets\Services\LibraryLinks;
use Modules\Assets\Services\MediaLibraryService;
use Modules\Assets\Services\MediaUrl;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Lunar's galleries backed by the one library (modules/Assets, LibraryLinks).
 *
 * The invariant: an image FILE exists once, in the library. A product's —
 * collection's, brand's, swatch's — gallery image is a media row that links
 * to it, so everything Lunar reads off media rows keeps working while the
 * original is shared. These tests drive Lunar's own panel routes, because the
 * point is that the first-party screens get this without being changed.
 */
class LibraryGalleryLinksTest extends TestCase
{
    use CreatesStorefrontData;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');

        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');
    }

    /** One fixed set of bytes, so "the same file" is literally the same file. */
    private function photo(string $name = 'ao-khoac.jpg'): UploadedFile
    {
        static $bytes = null;

        if ($bytes === null) {
            $image = imagecreatetruecolor(120, 180);
            imagefill($image, 0, 0, imagecolorallocate($image, 120, 80, 60));
            ob_start();
            imagejpeg($image);
            $bytes = (string) ob_get_clean();
        }

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    private function uploadToGallery(Product $product, UploadedFile $file): Media
    {
        $this->post(route('panel.products.media.store', $product), [
            'collection' => 'images',
            'files' => [$file],
        ])->assertRedirect()->assertSessionHasNoErrors();

        return $product->fresh()->getMedia('images')->last();
    }

    private function disk()
    {
        return Storage::disk('media');
    }

    public function test_lunars_upload_action_is_the_librarys(): void
    {
        $this->assertInstanceOf(AddMediaThroughLibrary::class, app(AddsMedia::class));
    }

    public function test_a_gallery_upload_is_stored_in_the_library_and_linked(): void
    {
        $product = $this->createProduct();

        $link = $this->uploadToGallery($product, $this->photo());
        $asset = Asset::with('file')->sole();
        $source = $asset->file;

        $this->assertTrue(LibraryLinks::isLink($link));
        $this->assertSame($asset->id, $link->getCustomProperty(LibraryLinks::ASSET));
        $this->assertSame('products', $source->getCustomProperty('folder'));

        // One original, in the library; the gallery row reads it from there.
        $this->assertSame($source->getPathRelativeToRoot(), $link->getPathRelativeToRoot());
        $this->assertSame($source->getUrl(), $link->getUrl());
        $this->assertTrue($this->disk()->exists($source->getPathRelativeToRoot()));
        $this->assertSame([], $this->disk()->files((string) $link->id));

        // Still a real gallery row: Lunar's thumbnail relation finds it.
        $this->assertSame($link->id, $product->fresh()->thumbnail?->id);
    }

    /**
     * The file manager hands a picked library file to Lunar's uploader as
     * bytes. Those bytes must find the file they came from, not become a copy.
     */
    public function test_uploading_bytes_the_library_already_holds_reuses_that_file(): void
    {
        $existing = app(MediaLibraryService::class)->store($this->photo('tu-thu-vien.jpg'), 'lookbooks');

        $first = $this->uploadToGallery($this->createProduct(), $this->photo('chon-lai.jpg'));
        $second = $this->uploadToGallery($this->createProduct(), $this->photo('lan-nua.jpg'));

        $this->assertSame(1, Asset::count());
        $this->assertSame($existing->id, $first->getCustomProperty(LibraryLinks::ASSET));
        $this->assertSame($existing->id, $second->getCustomProperty(LibraryLinks::ASSET));
        // Reused in place, not refiled.
        $this->assertSame('lookbooks', $existing->fresh('file')->file->getCustomProperty('folder'));
    }

    public function test_removing_an_image_from_a_gallery_keeps_the_library_file(): void
    {
        $product = $this->createProduct();
        $link = $this->uploadToGallery($product, $this->photo());
        $original = Asset::with('file')->sole()->file->getPathRelativeToRoot();

        $this->delete(route('panel.products.media.destroy', [$product, $link]))->assertRedirect();

        $this->assertNull(Media::find($link->id));
        $this->assertSame(1, Asset::count());
        $this->assertTrue($this->disk()->exists($original), 'Gỡ ảnh khỏi gallery đã xoá luôn file gốc của thư viện.');
    }

    public function test_deleting_a_library_file_takes_it_out_of_every_gallery(): void
    {
        $a = $this->createProduct();
        $b = $this->createProduct();
        $this->uploadToGallery($a, $this->photo());
        $this->uploadToGallery($b, $this->photo());
        $asset = Asset::sole();

        $this->getJson(route('panel.shop.media.files'))
            ->assertJsonPath('items.0.used', 2);

        $this->deleteJson(route('panel.shop.media.destroy'), ['ids' => [$asset->id]])
            ->assertOk()
            ->assertJsonPath('deleted', 1)
            ->assertJsonPath('unlinked', 2);

        $this->assertCount(0, $a->fresh()->getMedia('images'));
        $this->assertCount(0, $b->fresh()->getMedia('images'));
        $this->assertSame(0, Media::count());
    }

    public function test_replacing_a_library_file_updates_every_gallery(): void
    {
        $product = $this->createProduct();
        $link = $this->uploadToGallery($product, $this->photo());
        $asset = Asset::sole();

        // A rendition made from the old picture, which must not survive.
        $this->disk()->put($link->getPathRelativeToRoot('small'), 'old pixels');

        $this->postJson(route('panel.shop.media.replace', $asset->id), [
            'file' => UploadedFile::fake()->image('ao-moi.png', 90, 90),
        ])->assertOk()->assertJsonPath('used', 1);

        $source = $asset->fresh('file')->file;
        $link = $link->fresh();

        $this->assertSame('ao-moi.png', $link->file_name);
        $this->assertSame($source->id, $link->getCustomProperty(LibraryLinks::SOURCE));
        $this->assertSame($source->getUrl(), $link->getUrl());

        // The old rendition is gone; whatever is there now was made from the
        // new file (the test queue is sync, so the warm-up already ran).
        $small = $link->getPathRelativeToRoot('small');
        $this->assertTrue(
            ! $this->disk()->exists($small) || $this->disk()->get($small) !== 'old pixels',
            'Ảnh cũ của gallery vẫn còn sau khi thay file trong thư viện.',
        );
    }

    /**
     * Renditions stay per record: a brand renders `small` with Lunar's
     * StandardDefinitions, the library with FashionMediaDefinitions — same
     * name, different pixels. Sharing a directory would let one overwrite the
     * other.
     */
    public function test_renditions_live_with_the_link_not_the_library(): void
    {
        $brand = Brand::create(['name' => 'Thương hiệu']);

        $this->post(route('panel.brands.media.store', $brand), ['collection' => 'images', 'files' => [$this->photo()]])
            ->assertSessionHasNoErrors();

        $link = $brand->fresh()->getMedia('images')->sole();
        $source = Asset::with('file')->sole()->file;

        $this->assertStringStartsWith($link->id.'/conversions/', $link->getPathRelativeToRoot('small'));
        $this->assertStringStartsWith($source->id.'/conversions/', $source->getPathRelativeToRoot('small'));
    }

    /** The storefront path: a rendition is generated for the link, from the library's original. */
    public function test_the_storefront_renders_a_link_from_the_library_original(): void
    {
        config(['lunar.media.on_demand.sync' => true]);

        $link = $this->uploadToGallery($this->createProduct(), $this->photo());

        // Start from nothing: no rendition on disk, none remembered.
        $this->disk()->deleteDirectory((string) $link->id);
        app(ConversionGenerator::class)->forgetAllExists($link);

        $url = app(MediaUrl::class)->conversion($link->fresh(), 'medium');

        $this->assertNotNull($url);
        $this->assertTrue($this->disk()->exists($link->fresh()->getPathRelativeToRoot('medium')));
    }

    public function test_a_swatch_upload_links_to_the_library(): void
    {
        $value = $this->optionValue($this->createProduct(), 'Họa tiết', 'Kẻ sọc');

        $this->post(route('panel.settings.product-options.values.swatch.store', [$value->product_option_id, $value->id]), [
            'file' => $this->photo('ke-soc.jpg'),
        ])->assertSessionHasNoErrors();

        $link = $value->fresh()->getMedia('images')->sole();

        $this->assertTrue(LibraryLinks::isLink($link));
        $this->assertSame('swatches', Asset::with('file')->sole()->file->getCustomProperty('folder'));
    }

    /** A format the library refuses stays on the record, exactly as Lunar ships it. */
    public function test_an_svg_stays_on_the_record(): void
    {
        $product = $this->createProduct();
        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>',
        );

        $this->post(route('panel.products.media.store', $product), ['collection' => 'images', 'files' => [$svg]]);

        $media = $product->fresh()->getMedia('images')->sole();

        $this->assertFalse(LibraryLinks::isLink($media));
        $this->assertSame(0, Asset::count());
    }

    public function test_the_manager_says_where_a_file_is_used(): void
    {
        $product = $this->createProduct();
        $this->uploadToGallery($product, $this->photo());

        $this->getJson(route('panel.shop.media.show', Asset::sole()->id))
            ->assertOk()
            ->assertJsonPath('used', 1)
            ->assertJsonPath('usages.0.type', 'products')
            ->assertJsonPath('usages.0.url', route('panel.products.edit', $product));
    }

    /* ------------------------------------------------ adopting old galleries */

    /** A gallery image from before the library: it owns its file. */
    private function ownedGalleryImage(Product $product, ?UploadedFile $file = null): Media
    {
        return app(AddMedia::class)->execute($product, $file ?? $this->photo('cu.jpg'));
    }

    public function test_adopting_moves_an_owned_original_into_the_library_and_keeps_the_row(): void
    {
        $product = $this->createProduct();
        $media = $this->ownedGalleryImage($product);
        $variant = $product->variants()->first();
        $variant->images()->attach($media->id, ['primary' => true, 'position' => 1]);
        $this->disk()->put($media->getPathRelativeToRoot('small'), 'rendition');
        $ownOriginal = $media->getPathRelativeToRoot();

        $this->artisan('assets:adopt-galleries', ['--dry-run' => true])->assertSuccessful();
        $this->assertFalse(LibraryLinks::isLink($media->fresh()));
        $this->assertSame(0, Asset::count());

        $this->artisan('assets:adopt-galleries')->assertSuccessful();

        $media = $media->fresh();
        $source = Asset::with('file')->sole()->file;

        $this->assertTrue(LibraryLinks::isLink($media));
        $this->assertSame('products', $source->getCustomProperty('folder'));
        $this->assertFalse($this->disk()->exists($ownOriginal));
        $this->assertTrue($this->disk()->exists($source->getPathRelativeToRoot()));
        // The row kept its id, so the variant still points at it…
        $this->assertSame([$media->id], $variant->fresh()->images->pluck('id')->all());
        // …and its renditions, made from the same picture, stayed.
        $this->assertTrue($this->disk()->exists($media->getPathRelativeToRoot('small')));

        // Idempotent.
        $this->artisan('assets:adopt-galleries')->assertSuccessful();
        $this->assertSame(1, Asset::count());
    }

    /**
     * A dry run writes nothing — not even the content hashes it computes — and
     * counts the second gallery holding the same photo as the reuse it will be.
     */
    public function test_a_dry_run_writes_nothing_and_counts_shared_photos_once(): void
    {
        $library = app(MediaLibraryService::class)->store(UploadedFile::fake()->image('khac.jpg', 40, 40));
        $library->file->forgetCustomProperty('sha1');
        $library->file->saveQuietly();

        $this->ownedGalleryImage($this->createProduct(), $this->photo('a.jpg'));
        $this->ownedGalleryImage($this->createProduct(), $this->photo('b.jpg'));

        $this->artisan('assets:adopt-galleries', ['--dry-run' => true])
            ->expectsTable(['', 'would'], [
                ['moved into the library', 1],
                ['linked to an identical library file', 1],
                ['left as is (not an image the library takes)', 0],
                ['original missing on disk', 0],
                ['failed', 0],
            ])
            ->assertSuccessful();

        $this->assertNull($library->fresh('file')->file->getCustomProperty('sha1'));
        $this->assertSame(0, Media::query()->whereNotNull('custom_properties->'.LibraryLinks::SOURCE)->count());

        // And the real run does what the dry run said.
        $this->artisan('assets:adopt-galleries')->assertSuccessful();
        $this->assertSame(2, Asset::count());
    }

    public function test_adopting_links_to_an_identical_library_file_instead_of_moving(): void
    {
        $existing = app(MediaLibraryService::class)->store($this->photo('da-co.jpg'));
        $media = $this->ownedGalleryImage($this->createProduct(), $this->photo('ban-sao.jpg'));
        $ownOriginal = $media->getPathRelativeToRoot();

        $this->artisan('assets:adopt-galleries')->assertSuccessful();

        $this->assertSame(1, Asset::count());
        $this->assertSame($existing->id, $media->fresh()->getCustomProperty(LibraryLinks::ASSET));
        $this->assertSame('da-co.jpg', $media->fresh()->file_name);
        $this->assertFalse($this->disk()->exists($ownOriginal));
    }
}
