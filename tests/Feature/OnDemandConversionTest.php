<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Assets\Jobs\GenerateConversionJob;
use Modules\Assets\Services\ConversionGenerator;
use Modules\Assets\Services\MediaUrl;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

class OnDemandConversionTest extends TestCase
{
    use CreatesStorefrontData;

    /**
     * Attach a real (generated) image to a product so conversions can be made.
     */
    protected function mediaWithImage(): Media
    {
        $this->seedBaseData();
        $product = $this->createProduct();

        $tmp = sys_get_temp_dir().'/conv-test-'.uniqid().'.jpg';
        $img = imagecreatetruecolor(1200, 1500);
        imagejpeg($img, $tmp, 90);

        return $product->addMedia($tmp)->toMediaCollection('images');
    }

    public function test_generates_missing_conversion_synchronously(): void
    {
        $media = $this->mediaWithImage();
        $generator = app(ConversionGenerator::class);

        // Simulate a not-yet-generated conversion (tests run the sync queue, so
        // the upload — and the post-upload warm listener — may have produced it;
        // remove the file AND bust the "exists" cache to set the scenario).
        Storage::disk($media->conversions_disk)->delete($media->getPathRelativeToRoot('medium'));
        $generator->forgetExists($media, 'medium');
        $this->assertFalse($generator->fileExists($media->fresh(), 'medium'));

        $this->assertTrue($generator->ensure($media->fresh(), 'medium'));
        $this->assertTrue($generator->fileExists($media->fresh(), 'medium'));
    }

    public function test_self_heals_when_flag_set_but_file_missing(): void
    {
        $media = $this->mediaWithImage();
        $generator = app(ConversionGenerator::class);

        $generator->ensure($media->fresh(), 'medium');
        Storage::disk($media->conversions_disk)->delete($media->getPathRelativeToRoot('medium'));

        // The "exists" result is cached for hot reads, so an out-of-band file
        // delete is intentionally not detected until the cache is busted (which
        // regeneration/deletion does). Mirror that here.
        $generator->forgetExists($media, 'medium');

        // Flag still says generated, but the file is gone → must regenerate.
        $this->assertTrue($media->fresh()->hasGeneratedConversion('medium'));
        $this->assertTrue($generator->ensure($media->fresh(), 'medium'));
        $this->assertTrue($generator->fileExists($media->fresh(), 'medium'));
    }

    public function test_media_url_returns_conversion_and_null_for_unknown(): void
    {
        $media = $this->mediaWithImage();
        $urls = app(MediaUrl::class);

        $this->assertStringContainsString('conversions/', $urls->conversion($media->fresh(), 'medium'));
        // Unknown conversion → null (never the original full-size image).
        $this->assertNull($urls->conversion($media->fresh(), 'nope'));
        $this->assertNull($urls->conversion(null, 'medium'));
    }

    public function test_async_mode_is_a_pure_url_build(): void
    {
        Config::set('lunar.media.on_demand.sync', false);

        $media = $this->mediaWithImage();
        $generator = app(ConversionGenerator::class);

        Storage::disk($media->conversions_disk)->delete($media->getPathRelativeToRoot('zoom'));
        $generator->forgetExists($media, 'zoom');

        Queue::fake();
        $url = app(MediaUrl::class)->conversion($media->fresh(), 'zoom');

        // The EXACT conversion URL is returned even though the file is missing:
        // the browser's own image request self-heals it via the media.conversion
        // fallback route. The render itself does no image work at all — nothing
        // generated inline, nothing queued (uploads pre-warm via warm()).
        $this->assertStringContainsString('-zoom', $url);
        $this->assertFalse($generator->fileExists($media->fresh(), 'zoom'));
        Queue::assertNothingPushed();
    }

    public function test_upload_warm_queues_each_missing_conversion_once(): void
    {
        $media = $this->mediaWithImage();
        $generator = app(ConversionGenerator::class);

        Storage::disk($media->conversions_disk)->delete($media->getPathRelativeToRoot('zoom'));
        $generator->forgetExists($media, 'zoom');

        Queue::fake();
        app(MediaUrl::class)->warm($media->fresh());

        Queue::assertPushed(
            GenerateConversionJob::class,
            fn (GenerateConversionJob $job) => $job->conversion === 'zoom' && $job->queue === 'media',
        );

        // Re-warming inside the dedupe window must not queue duplicates.
        $pushed = Queue::pushed(GenerateConversionJob::class)->count();
        app(MediaUrl::class)->warm($media->fresh());
        $this->assertSame($pushed, Queue::pushed(GenerateConversionJob::class)->count());
    }

    public function test_missing_conversion_is_generated_by_fallback_route(): void
    {
        $media = $this->mediaWithImage();
        $generator = app(ConversionGenerator::class);

        Storage::disk($media->conversions_disk)->delete($media->getPathRelativeToRoot('medium'));
        $generator->forgetExists($media, 'medium');

        // Request the conversion's public URL. The physical file is missing, so
        // (as in prod, where the web server only serves existing files) the
        // request hits the media.conversion route, which generates + streams it.
        $response = $this->get($media->getUrl('medium'));

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
        $this->assertTrue($generator->fileExists($media->fresh(), 'medium'));
    }

    public function test_fallback_route_404s_for_unknown_targets(): void
    {
        $media = $this->mediaWithImage();

        // A filename that maps to no registered conversion of this media.
        $this->get("/media/{$media->id}/conversions/not-a-real-file.jpg")->assertNotFound();

        // A media id that doesn't exist.
        $this->get('/media/999999/conversions/whatever-medium.jpg')->assertNotFound();
    }
}
