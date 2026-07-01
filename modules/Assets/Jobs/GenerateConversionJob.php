<?php

namespace Modules\Assets\Jobs;

use App\Support\Queues;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Assets\Services\ConversionGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Generates a single media conversion off the request path, on the `media`
 * queue. Used to PRE-WARM a size (e.g. right after upload, or ahead of a
 * campaign) so the first storefront visitor never pays the synchronous
 * generation cost in {@see ConversionGenerator::ensure()}.
 *
 * Idempotent: ensure() re-checks existence under a lock and no-ops when the
 * file is already present, so re-running (or racing the on-demand path) is safe.
 */
class GenerateConversionJob implements ShouldQueue
{
    use Queueable;

    /** Retry transient IO errors; generation itself is idempotent. */
    public int $tries = 2;

    /** @var list<int> seconds between retries. */
    public array $backoff = [15, 60];

    public function __construct(
        public int $mediaId,
        public string $conversion,
    ) {
        $this->onQueue(Queues::MEDIA);
    }

    public function handle(ConversionGenerator $generator): void
    {
        $media = Media::find($this->mediaId);

        if (! $media) {
            return; // media deleted between dispatch and run — nothing to do.
        }

        $generator->ensure($media, $this->conversion);
    }

    /**
     * Dedupe key so a burst of pre-warm requests for the same size collapses to
     * one queued job (with ShouldBeUnique, if enabled) / for log correlation.
     */
    public function uniqueId(): string
    {
        return "media-gen.{$this->mediaId}.{$this->conversion}";
    }
}
