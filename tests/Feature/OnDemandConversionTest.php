<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
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
        // the upload may have produced it — remove the file to set the scenario).
        Storage::disk($media->conversions_disk)->delete($media->getPathRelativeToRoot('medium'));
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
}
