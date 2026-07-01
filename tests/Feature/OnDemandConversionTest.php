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

    public function test_media_url_returns_conversion_then_original_fallback(): void
    {
        $media = $this->mediaWithImage();
        $urls = app(MediaUrl::class);

        $this->assertStringContainsString('conversions/', $urls->conversion($media->fresh(), 'medium'));
        // Unknown conversion → original URL (no exception).
        $this->assertStringNotContainsString('conversions/', $urls->conversion($media->fresh(), 'nope'));
        $this->assertNull($urls->conversion(null, 'medium'));
    }

    public function test_async_mode_serves_nearest_existing_and_queues_exact_size(): void
    {
        Config::set('lunar.media.on_demand.sync', false);

        $media = $this->mediaWithImage();
        $generator = app(ConversionGenerator::class);

        // 'small' exists as a fallback candidate; 'zoom' is missing.
        $generator->ensure($media->fresh(), 'small');
        Storage::disk($media->conversions_disk)->delete($media->getPathRelativeToRoot('zoom'));
        $generator->forgetExists($media, 'zoom');

        Queue::fake();
        $url = app(MediaUrl::class)->conversion($media->fresh(), 'zoom');

        // Served a real conversion (the nearest existing), not the heavy original,
        // and never generated 'zoom' inline (it's still missing).
        $this->assertStringContainsString('conversions/', $url);
        $this->assertFalse($generator->fileExists($media->fresh(), 'zoom'));

        // The exact size was queued on the media queue for the next visitor.
        Queue::assertPushed(
            GenerateConversionJob::class,
            fn (GenerateConversionJob $job) => $job->conversion === 'zoom' && $job->queue === 'media',
        );
    }

    public function test_nearest_existing_prefers_smallest_that_is_wide_enough(): void
    {
        $media = $this->mediaWithImage();
        $generator = app(ConversionGenerator::class);

        // Generate large + zoom (both wider than medium), remove medium.
        $generator->ensure($media->fresh(), 'large');
        $generator->ensure($media->fresh(), 'zoom');
        Storage::disk($media->conversions_disk)->delete($media->getPathRelativeToRoot('medium'));
        $generator->forgetExists($media, 'medium');

        // 'large' (smallest >= medium's width) beats 'zoom' — no needless upscale.
        $this->assertSame('large', $generator->nearestExisting($media->fresh(), 'medium'));
    }
}
