<?php

namespace Modules\Assets\Jobs;

use Modules\Core\Support\Queues;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Modules\Assets\Services\ConversionGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Regenerates conversions for a chunk of media items. Dispatched in a batch by
 * {@see \Modules\Assets\Services\MediaRegenerator} so a large library is rebuilt
 * across many small queued jobs (progress is tracked by the batch) instead of
 * one giant synchronous request that would time out / run out of memory.
 *
 * The chunk's conversions are generated INLINE inside this job (not re-queued):
 * media-library's regenerate command would otherwise honour
 * `queue_conversions_by_default` and dispatch a second wave of jobs onto the
 * `media` queue, so the batch would finish instantly while the real work piled
 * up untracked. Forcing inline generation keeps batch progress accurate.
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
    ) {
        $this->onQueue(Queues::MEDIA);
    }

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
        // Force INLINE generation for the duration: otherwise the command honours
        // queue_conversions_by_default and re-queues the work, so this batch job
        // would return before anything is generated (progress bar lies + queue
        // storm). Restore the flag afterwards.
        $previous = Config::get('media-library.queue_conversions_by_default');
        Config::set('media-library.queue_conversions_by_default', false);

        try {
            Artisan::call('media-library:regenerate', $args);
        } finally {
            Config::set('media-library.queue_conversions_by_default', $previous);
        }

        // Bust the on-demand exists-cache so rebuilt files are picked up.
        // forgetAllExists drops the media's whole exists-set (regenerate rebuilt
        // every conversion incl. thumb / webp / any StandardMediaDefinitions
        // size) plus each conversion's pre-warm dedupe window.
        Media::whereIn('id', $this->mediaIds)->get()->each(
            fn (Media $media) => $generator->forgetAllExists($media)
        );
    }
}
