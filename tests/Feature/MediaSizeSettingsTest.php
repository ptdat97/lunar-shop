<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Lunar\Core\Models\Staff;
use Modules\Assets\Services\MediaSettings;
use Modules\Core\Panel\SettingsRegistry;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The sizes every image conversion is generated at.
 *
 * The conversion engine kept reading `MediaSettings::sizes()` through the whole
 * 2.0 upgrade, but the only thing that ever *wrote* them — the MediaImageSizes
 * admin page — went with the rest of Filament. Nothing failed: the shop simply
 * became pinned to the defaults, silently and permanently.
 *
 * `media-library:regenerate` never covered this. It regenerates at whatever
 * sizes are configured; it cannot change them.
 *
 * So what these pin is the loop end to end: a size saved through the panel has
 * to come back out of the pixels.
 */
class MediaSizeSettingsTest extends TestCase
{
    use CreatesStorefrontData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');
    }

    public function test_the_settings_screen_offers_every_size_the_service_knows(): void
    {
        $group = app(SettingsRegistry::class)->get('media');

        $this->assertNotNull($group, 'Không còn màn hình nào đổi được kích thước ảnh.');

        $names = collect($group->fields())->map(fn ($field) => $field->name)->all();

        foreach (MediaSettings::keys() as $key) {
            $this->assertContains("{$key}.width", $names);
            $this->assertContains("{$key}.height", $names);
        }
    }

    public function test_saving_sizes_persists_them_and_busts_the_cache(): void
    {
        $payload = [];

        foreach (MediaSettings::keys() as $i => $key) {
            $payload[$key] = ['width' => 120 + $i * 10, 'height' => 180 + $i * 10];
        }

        $this->put(route('panel.shop.settings.media.update'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // A fresh instance on purpose: `sizes()` memoises per request and
        // caches across them, so reading through the same object would pass
        // even if the save never reached the store the conversions read.
        app()->forgetInstance(MediaSettings::class);

        $sizes = app(MediaSettings::class)->sizes();

        $this->assertSame(120, $sizes['small']['width']);
        $this->assertSame(180, $sizes['small']['height']);
    }

    /**
     * The loop that matters: a size chosen in the panel has to come out of the
     * generated file's pixels. Anything less and the screen is decoration.
     */
    public function test_a_saved_size_changes_the_pixels_of_a_generated_conversion(): void
    {
        Storage::fake('media');

        $this->put(route('panel.shop.settings.media.update'), [
            'small' => ['width' => 150, 'height' => 225],
            'medium' => ['width' => 300, 'height' => 450],
            'large' => ['width' => 600, 'height' => 900],
            'zoom' => ['width' => 800, 'height' => 1200],
        ])->assertSessionHasNoErrors();

        $product = $this->createProduct();
        $media = $product
            ->addMedia(UploadedFile::fake()->image('front.jpg', 2000, 3000))
            ->toMediaCollection(config('lunar.media.collection', 'images'));

        // The definitions read MediaSettings when conversions are registered,
        // so the freshly saved numbers are what Spatie crops to.
        $path = $media->getPath('large');

        $this->assertFileExists($path, 'Conversion `large` không được sinh ra.');

        [$width, $height] = getimagesize($path);

        $this->assertSame(600, $width);
        $this->assertSame(900, $height);
    }

    /** A nonsense size must be refused rather than written. */
    public function test_absurd_sizes_are_rejected(): void
    {
        $this->put(route('panel.shop.settings.media.update'), [
            'small' => ['width' => 0, 'height' => 300],
            'medium' => ['width' => 400, 'height' => 600],
            'large' => ['width' => 1000, 'height' => 1500],
            'zoom' => ['width' => 1600, 'height' => 2400],
        ])->assertSessionHasErrors('small.width');

        $this->put(route('panel.shop.settings.media.update'), [
            'small' => ['width' => 200, 'height' => 300],
            'medium' => ['width' => 400, 'height' => 600],
            'large' => ['width' => 1000, 'height' => 1500],
            'zoom' => ['width' => 99999, 'height' => 2400],
        ])->assertSessionHasErrors('zoom.width');
    }

    /** Sizes decide what every shopper downloads, so not a loose gate. */
    public function test_changing_sizes_requires_the_core_settings_permission(): void
    {
        $staff = Staff::factory()->create(['admin' => false]);

        $this->actingAs($staff, 'staff')
            ->get(route('panel.shop.settings.media.edit'))
            ->assertForbidden();
    }

    /**
     * Changing a size only affects images generated from then on, so there has
     * to be a way to bring the existing library up to the new numbers. Losing
     * that is half of losing the feature.
     */
    public function test_a_command_exists_to_rebuild_existing_conversions(): void
    {
        \Illuminate\Support\Facades\Bus::fake();

        $product = $this->createProduct();
        $product->addMedia(UploadedFile::fake()->image('front.jpg', 800, 1200))
            ->toMediaCollection(config('lunar.media.collection', 'images'));

        $this->artisan('media:regenerate')->assertSuccessful();

        \Illuminate\Support\Facades\Bus::assertBatched(
            fn ($batch) => $batch->name === 'media:regenerate',
        );
    }

    /** With no media there is nothing to queue, and it says so. */
    public function test_the_rebuild_command_says_so_when_there_is_nothing_to_do(): void
    {
        \Illuminate\Support\Facades\Bus::fake();

        $this->artisan('media:regenerate')->assertSuccessful();

        \Illuminate\Support\Facades\Bus::assertNothingBatched();
    }
}
