<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Modules\Assets\Jobs\RegenerateConversionsJob;
use Modules\Assets\Services\ConversionGenerator;
use Modules\Assets\Services\MediaRegenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

class MediaRegenerateTest extends TestCase
{
    use CreatesStorefrontData;

    protected function mediaWithImage(): Media
    {
        $this->seedBaseData();
        $product = $this->createProduct();

        $tmp = sys_get_temp_dir().'/regen-'.uniqid().'.jpg';
        imagejpeg(imagecreatetruecolor(1200, 1500), $tmp, 90);

        return $product->addMedia($tmp)->toMediaCollection('images');
    }

    public function test_exists_check_is_cached_after_generation(): void
    {
        $media = $this->mediaWithImage();
        $generator = app(ConversionGenerator::class);

        $generator->ensure($media->fresh(), 'medium');

        // The positive result is cached (one set per media), so a hot read
        // doesn't stat the disk.
        $this->assertContains('medium', (array) Cache::get("media.exists.{$media->id}"));

        // forgetExists clears it (used after regenerate/delete).
        $generator->forgetExists($media, 'medium');
        $this->assertNotContains('medium', (array) Cache::get("media.exists.{$media->id}"));
    }

    public function test_dispatch_queues_a_batch_of_regenerate_jobs(): void
    {
        Bus::fake();
        $this->mediaWithImage();

        $batchId = app(MediaRegenerator::class)->dispatch(onlyMissing: false);

        $this->assertNotNull($batchId);
        Bus::assertBatched(fn ($batch) => $batch->name === 'media:regenerate'
            && $batch->jobs->isNotEmpty());
    }

    public function test_dispatch_returns_null_when_no_media(): void
    {
        Bus::fake();
        $this->seedBaseData();

        $this->assertNull(app(MediaRegenerator::class)->dispatch());
        Bus::assertNothingBatched();
    }

    public function test_regenerate_job_rebuilds_conversion_and_busts_cache(): void
    {
        $media = $this->mediaWithImage();
        $generator = app(ConversionGenerator::class);

        // Prime: generate + cache, then delete the file out-of-band.
        $generator->ensure($media->fresh(), 'medium');
        Storage::disk($media->conversions_disk)->delete($media->getPathRelativeToRoot('medium'));
        $this->assertContains('medium', (array) Cache::get("media.exists.{$media->id}")); // stale

        // The job rebuilds the conversion and clears the stale cache entry.
        (new RegenerateConversionsJob([$media->id], onlyMissing: false))->handle($generator);

        $this->assertNotContains('medium', (array) Cache::get("media.exists.{$media->id}"));
        $this->assertTrue($generator->fileExists($media->fresh(), 'medium'));
    }
}
