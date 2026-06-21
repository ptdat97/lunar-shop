<?php

namespace Modules\Media\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Modules\Media\Services\ConversionGenerator;
use Modules\Media\Services\MediaSettings;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Regenerates conversions for a chunk of media items. Dispatched in a batch by
 * {@see \Modules\Media\Services\MediaRegenerator} so a large library is rebuilt
 * across many small queued jobs (progress is tracked by the batch) instead of
 * one giant synchronous request that would time out / run out of memory.
 *
 * After regenerating, the on-demand "exists" cache is busted for each touched
 * conversion so the storefront serves the freshly rebuilt files immediately.
 */
class RegenerateConversionsJob implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * @param  list<int>  $mediaIds  the chunk of media ids to (re)generate
     * @param  bool  $onlyMissing  skip conversions whose file already exists
     */
    public function __construct(
        public array $mediaIds,
        public bool $onlyMissing = false,
    ) {}

    public function handle(ConversionGenerator $generator): void
    {
        // If the batch was cancelled from the UI, stop early.
        if ($this->batch()?->cancelled()) {
            return;
        }

        // --ids accepts multiple values (repeated flag); pass as an array so the
        // command scopes to exactly this chunk rather than treating a CSV string
        // as one id.
        $args = [
            '--ids' => array_map('strval', $this->mediaIds),
            '--force' => true,
        ];

        if ($this->onlyMissing) {
            $args['--only-missing'] = true;
        }

        // Reuse Spatie's regenerate command for the actual work (it handles
        // every conversion + responsive variants); scope it to this chunk's ids.
        Artisan::call('media-library:regenerate', $args);

        // Bust the on-demand exists-cache so rebuilt files are picked up.
        $conversionKeys = MediaSettings::keys();

        Media::whereIn('id', $this->mediaIds)->get()->each(
            fn (Media $media) => collect($conversionKeys)->each(
                fn (string $conversion) => $generator->forgetExists($media, $conversion)
            )
        );
    }
}
