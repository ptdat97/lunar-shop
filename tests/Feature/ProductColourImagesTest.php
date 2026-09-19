<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Lunar\Core\Models\Asset;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOption;
use Lunar\Core\Models\ProductOptionValue;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\Staff;
use Lunar\Core\Models\TaxClass;
use Modules\Assets\Services\LibraryLinks;
use Modules\Assets\Services\MediaLibraryService;
use Modules\Catalog\Services\VariantImages;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * "Photos by colour" on the product editor (ProductColourImagesController,
 * VariantImages).
 *
 * Photos belong to a colour, and every size of that colour shows the same
 * set. They are stored where Lunar keeps variant images — its own pivot — so
 * the storefront, Lunar's per-variant picker and `getThumbnail()` all see the
 * same thing.
 */
class ProductColourImagesTest extends TestCase
{
    use CreatesStorefrontData;

    private Product $product;

    /** @var array<string, ProductOptionValue> */
    private array $colours = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');

        // Black/S from the helper, then Black/M, White/S, White/M.
        $this->product = $this->createProduct(['options' => ['Color' => 'Black', 'Size' => 'S']]);
        ProductOption::whereJsonContains('name->en', 'Color')->update(['type' => 'colour']);

        $this->colours['Black'] = $this->optionValue($this->product, 'Color', 'Black');
        $this->colours['White'] = $this->optionValue($this->product, 'Color', 'White');

        foreach ([['Black', 'M'], ['White', 'S'], ['White', 'M']] as [$colour, $size]) {
            $variant = ProductVariant::create([
                'product_id' => $this->product->id,
                'sku' => "CI-{$colour}-{$size}",
                'tax_class_id' => TaxClass::getDefault()?->id,
                'enabled' => true,
            ]);
            $variant->values()->sync([
                $this->colours[$colour]->id,
                $this->optionValue($this->product, 'Size', $size)->id,
            ]);
        }
    }

    private function galleryPhoto(string $name): Media
    {
        return $this->product->addMedia(UploadedFile::fake()->image($name, 60, 90))->toMediaCollection('images');
    }

    private function url(?ProductOptionValue $value = null): string
    {
        return $value
            ? route('panel.shop.products.colour-images.update', [$this->product, $value])
            : route('panel.shop.products.colour-images.show', $this->product);
    }

    /** @return array<int, array<int, int>> sku => media ids */
    private function sets(): array
    {
        return $this->product->variants()->with('images')->get()
            ->mapWithKeys(fn (ProductVariant $v) => [$v->sku => $v->images->pluck('id')->all()])
            ->all();
    }

    public function test_the_card_is_one_group_per_colour_with_the_gallery_to_pick_from(): void
    {
        $photo = $this->galleryPhoto('front.jpg');

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('axis.name', 'Color')
            ->assertJsonCount(2, 'groups')
            ->assertJsonPath('groups.0.name', 'Black')
            ->assertJsonPath('groups.0.variants', 2)
            ->assertJsonPath('groups.1.name', 'White')
            ->assertJsonPath('gallery.0.media_id', $photo->id);
    }

    public function test_setting_a_colour_gives_every_size_the_same_set_in_order(): void
    {
        $front = $this->galleryPhoto('front.jpg');
        $back = $this->galleryPhoto('back.jpg');

        $this->putJson($this->url($this->colours['Black']), [
            'items' => [['media_id' => $back->id], ['media_id' => $front->id]],
        ])->assertOk()->assertJsonPath('groups.0.images.0.media_id', $back->id);

        $sets = $this->sets();
        $black = collect($sets)->filter(fn ($ids, $sku) => str_contains($sku, 'Black') || ! str_starts_with($sku, 'CI-'));

        foreach ($black as $sku => $ids) {
            $this->assertSame([$back->id, $front->id], $ids, "{$sku} lệch bộ ảnh của màu Đen.");
        }

        $this->assertSame([], $sets['CI-White-S']);
        $this->assertSame([], $sets['CI-White-M']);

        // The first photo is each variant's primary — what Lunar's getThumbnail() reads.
        $this->assertSame($back->id, ProductVariant::firstWhere('sku', 'CI-Black-M')->getThumbnail()?->id);
    }

    /**
     * Choosing a library file brings it into the product's gallery once — as a
     * link — and a second colour choosing it reuses that same gallery row.
     */
    public function test_a_library_pick_joins_the_gallery_once(): void
    {
        $asset = app(MediaLibraryService::class)->store(UploadedFile::fake()->image('lookbook.jpg', 60, 90), 'lookbooks');

        $this->putJson($this->url($this->colours['Black']), ['items' => [['asset_id' => $asset->id]]])->assertOk();
        $this->putJson($this->url($this->colours['White']), ['items' => [['asset_id' => $asset->id]]])->assertOk();

        $gallery = $this->product->fresh()->getMedia('images');

        $this->assertCount(1, $gallery);
        $this->assertTrue(LibraryLinks::isLink($gallery->first()));
        $this->assertSame(1, Asset::count());
        $this->assertSame([$gallery->first()->id], $this->sets()['CI-White-M']);
    }

    /** A gallery that still owns the same bytes is reused, not joined by a duplicate. */
    public function test_a_library_pick_reuses_an_identical_photo_the_gallery_already_owns(): void
    {
        $image = imagecreatetruecolor(60, 90);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 30, 30));
        ob_start();
        imagejpeg($image);
        $bytes = (string) ob_get_clean();
        $owned = $this->product->addMedia(UploadedFile::fake()->createWithContent('owned.jpg', $bytes))->toMediaCollection('images');
        $asset = app(MediaLibraryService::class)->store(UploadedFile::fake()->createWithContent('library.jpg', $bytes));

        $this->putJson($this->url($this->colours['Black']), ['items' => [['asset_id' => $asset->id]]])->assertOk();

        $this->assertCount(1, $this->product->fresh()->getMedia('images'));
        $this->assertSame([$owned->id], $this->sets()['CI-Black-M']);
    }

    public function test_an_empty_set_clears_the_colour_back_to_the_gallery(): void
    {
        $photo = $this->galleryPhoto('front.jpg');
        $images = app(VariantImages::class);
        $images->assign($this->product, $this->colours['White'], [['media_id' => $photo->id]]);

        $this->putJson($this->url($this->colours['White']), ['items' => []])->assertOk();

        $this->assertSame([], $this->sets()['CI-White-S']);
        $this->assertNotNull(Media::find($photo->id), 'clearing a colour keeps the photo in the gallery');
    }

    /** The mixed flag: sizes of one colour set differently on Lunar's variant screen. */
    public function test_a_colour_whose_sizes_disagree_is_flagged(): void
    {
        $photo = $this->galleryPhoto('front.jpg');
        app(VariantImages::class)->syncVariants([ProductVariant::firstWhere('sku', 'CI-White-S')], collect([$photo]));

        $this->getJson($this->url())
            ->assertJsonPath('groups.1.name', 'White')
            ->assertJsonPath('groups.1.mixed', true)
            ->assertJsonPath('groups.0.mixed', false);
    }

    public function test_only_this_products_gallery_can_be_assigned(): void
    {
        $other = $this->createProduct();
        $foreign = $other->addMedia(UploadedFile::fake()->image('x.jpg', 20, 20))->toMediaCollection('images');

        $this->putJson($this->url($this->colours['Black']), ['items' => [['media_id' => $foreign->id]]])
            ->assertJsonValidationErrors('items.0.media_id');
    }

    public function test_a_value_of_another_option_is_not_a_colour(): void
    {
        $size = $this->optionValue($this->product, 'Size', 'S');

        $this->putJson($this->url($size), ['items' => []])->assertNotFound();
    }

    public function test_a_product_without_a_colour_option_has_no_groups(): void
    {
        $plain = $this->createProduct();

        $this->getJson(route('panel.shop.products.colour-images.show', $plain))
            ->assertOk()
            ->assertJsonPath('axis', null)
            ->assertJsonPath('groups', []);
    }

    public function test_the_card_needs_the_catalogue_permission(): void
    {
        $this->actingAs(Staff::factory()->create(['admin' => false]), 'staff');

        $this->getJson($this->url())->assertForbidden();
    }

    /** It rides on Lunar's product editor as a slot, not a screen of its own. */
    public function test_the_card_is_a_slot_on_lunars_product_editor(): void
    {
        $this->get("/panel/products/{$this->product->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'slots',
                fn ($slots) => collect($slots)->flatten(1)->contains(fn ($slot) => ($slot['component'] ?? null) === 'shop::ColourImages'),
            ));
    }
}
