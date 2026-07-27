<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\Asset;
use Modules\Content\Models\Banner;
use Modules\Content\Models\Menu;
use Modules\Content\Models\MenuItem;
use Modules\Content\Models\Page;
use Modules\Content\Models\PageSection;
use Modules\Theme\Services\ThemeSettings;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Every FileUpload-for-images field was migrated to MediaPicker (an Asset id
 * Select), so pre-migration data — bare `media`-disk paths, or (for product
 * variant swatches / SKU photos) a Spatie Media id attached straight to a
 * Product — is no longer valid input for those pickers. This command is what
 * makes it valid again: it ingests/re-owns each legacy value into a proper
 * library Asset and rewrites the column to the resulting Asset id.
 */
class MigrateLegacyImagesToLibraryTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_a_bare_banner_path_is_ingested_into_a_new_asset(): void
    {
        Storage::disk('media')->putFileAs('banners', UploadedFile::fake()->image('summer.jpg', 300, 300), 'summer.jpg');

        $banner = Banner::create([
            'title' => 'Summer', 'position' => 'home-hero',
            'image' => 'banners/summer.jpg', 'active' => true, 'sort' => 1,
        ]);

        $this->artisan('assets:migrate-legacy-images')->assertSuccessful();

        $banner->refresh();
        $this->assertIsNumeric($banner->image);
        $this->assertNotNull(Asset::find($banner->image));
    }

    public function test_it_is_idempotent_on_an_already_migrated_asset_id(): void
    {
        Storage::disk('media')->putFileAs('banners', UploadedFile::fake()->image('a.jpg', 200, 200), 'a.jpg');
        $banner = Banner::create([
            'title' => 'A', 'position' => 'home-hero',
            'image' => 'banners/a.jpg', 'active' => true, 'sort' => 1,
        ]);

        $this->artisan('assets:migrate-legacy-images')->assertSuccessful();
        $firstAssetId = $banner->refresh()->image;

        $this->artisan('assets:migrate-legacy-images')->assertSuccessful();

        $this->assertSame($firstAssetId, $banner->refresh()->image);
    }

    public function test_a_page_featured_image_path_is_migrated(): void
    {
        Storage::disk('media')->putFileAs('pages', UploadedFile::fake()->image('hero.jpg', 200, 200), 'hero.jpg');
        $page = Page::create(['title' => 'About', 'slug' => 'about', 'content' => 'x', 'published' => true, 'featured_image' => 'pages/hero.jpg']);

        $this->artisan('assets:migrate-legacy-images')->assertSuccessful();

        $this->assertIsNumeric($page->refresh()->featured_image);
        $this->assertNotNull(Asset::find($page->refresh()->featured_image));
    }

    public function test_a_menu_item_banner_image_path_is_migrated(): void
    {
        Storage::disk('media')->putFileAs('menus/banners', UploadedFile::fake()->image('b.jpg', 200, 200), 'b.jpg');
        $menu = Menu::create(['name' => 'Main', 'handle' => 'main-'.uniqid()]);
        $item = MenuItem::create(['menu_id' => $menu->id, 'type' => 'banner', 'label' => 'Sale', 'image' => 'menus/banners/b.jpg', 'sort' => 0]);

        $this->artisan('assets:migrate-legacy-images')->assertSuccessful();

        $this->assertIsNumeric($item->refresh()->image);
        $this->assertNotNull(Asset::find($item->refresh()->image));
    }

    public function test_hero_slider_section_slide_images_are_migrated_in_place(): void
    {
        Storage::disk('media')->putFileAs('sections/hero', UploadedFile::fake()->image('s1.jpg', 200, 200), 's1.jpg');
        $section = PageSection::create([
            'page_handle' => 'home', 'type' => 'hero-slider', 'sort' => 0, 'enabled' => true,
            'settings' => ['slides' => [['title' => 'Slide', 'image' => 'sections/hero/s1.jpg']]],
        ]);

        $this->artisan('assets:migrate-legacy-images')->assertSuccessful();

        $image = $section->refresh()->settings['slides'][0]['image'];
        $this->assertIsNumeric($image);
        $this->assertNotNull(Asset::find($image));
        // Non-image keys pass through untouched.
        $this->assertSame('Slide', $section->settings['slides'][0]['title']);
    }

    public function test_theme_settings_logo_and_payment_images_are_migrated(): void
    {
        Storage::disk('media')->putFileAs('theme/logo', UploadedFile::fake()->image('logo.png', 200, 200), 'logo.png');
        Storage::disk('media')->putFileAs('theme/payment', UploadedFile::fake()->image('visa.png', 100, 60), 'visa.png');

        $settings = app(ThemeSettings::class);
        $settings->set('general', ['logo' => 'theme/logo/logo.png', 'logo_footer' => '', 'favicon' => '']);
        $settings->set('payment', ['theme/payment/visa.png']);

        $this->artisan('assets:migrate-legacy-images')->assertSuccessful();

        $settings->forgetCache();
        $this->assertIsNumeric($settings->get('general.logo'));
        $this->assertNotNull(Asset::find($settings->get('general.logo')));
        $this->assertIsNumeric($settings->get('payment')[0]);
    }

    public function test_a_product_variable_image_swatch_media_id_is_re_owned_by_a_new_asset(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['variables' => [
            ['name' => ['en' => 'Pattern'], 'display_type' => 'image', 'values' => [['name' => ['en' => 'Stripe'], 'image' => null]]],
        ]]);
        $media = $product->addMedia(UploadedFile::fake()->image('swatch.png', 200, 200))->toMediaCollection('swatch');
        $product->variables = [
            ['name' => ['en' => 'Pattern'], 'display_type' => 'image', 'values' => [['name' => ['en' => 'Stripe'], 'image' => $media->id]]],
        ];
        $product->save();

        $this->artisan('assets:migrate-legacy-images')->assertSuccessful();

        $newValue = $product->refresh()->variables[0]['values'][0]['image'];
        $asset = Asset::find($newValue);
        $this->assertNotNull($asset);

        // The Media row was RE-OWNED (moved onto the new Asset), not duplicated:
        // exactly one Media row still exists for this id, and it now belongs to
        // the Asset (an Asset id and a Media id can coincidentally be equal —
        // both are separate auto-increment sequences — so morph ownership is
        // the real assertion here, not comparing the raw ids).
        $media->refresh();
        $this->assertSame($asset->getMorphClass(), $media->model_type);
        $this->assertSame($asset->id, $media->model_id);
        $this->assertSame(1, Media::where('id', $media->id)->count());
    }

    public function test_a_sku_image_media_id_is_re_owned_by_a_new_asset(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $media = $product->addMedia(UploadedFile::fake()->image('front.png', 300, 300))->toMediaCollection('images');

        $sku = $product->skus()->create([
            'variants' => [], 'position' => 0, 'images' => [$media->id],
            'sku' => 'MIG-1', 'price' => 1000, 'quantity' => 5, 'status' => 'published', 'is_default' => true,
        ]);

        $this->artisan('assets:migrate-legacy-images')->assertSuccessful();

        $newIds = $sku->refresh()->images;
        $this->assertCount(1, $newIds);
        $asset = Asset::find($newIds[0]);
        $this->assertNotNull($asset);

        // Re-owned (moved), not duplicated — see the swatch test above for why
        // comparing raw ids isn't the right assertion here.
        $media->refresh();
        $this->assertSame($asset->id, $media->model_id);
        $this->assertSame(1, Media::where('id', $media->id)->count());
    }

    public function test_dry_run_makes_no_changes(): void
    {
        Storage::disk('media')->putFileAs('banners', UploadedFile::fake()->image('dry.jpg', 200, 200), 'dry.jpg');
        $banner = Banner::create([
            'title' => 'Dry', 'position' => 'home-hero',
            'image' => 'banners/dry.jpg', 'active' => true, 'sort' => 1,
        ]);

        $this->artisan('assets:migrate-legacy-images', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('banners/dry.jpg', $banner->refresh()->image);
    }
}
