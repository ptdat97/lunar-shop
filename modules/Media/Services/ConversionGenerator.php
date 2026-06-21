<?php

namespace Modules\Media\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Generates a single media conversion on demand. Used at URL-resolution time
 * (MediaUrl) so the first request for a not-yet-generated size produces it
 * synchronously instead of 404ing or falling back to the full-size original.
 *
 * Built for high traffic:
 *  - the "file exists" result is cached, so hot pages don't hit the storage
 *    disk (a stat/HEAD call) for every image on every request;
 *  - generation is guarded by an atomic lock, so a burst of concurrent requests
 *    for the same missing size produces it once (no thundering herd) — the other
 *    requests wait briefly, then serve the freshly generated file.
 */
class ConversionGenerator
{
    /** Cache TTL (seconds) for a positive "file exists" result. */
    protected const EXISTS_TTL = 86400;

    /** Max seconds a concurrent request waits for the lock holder to generate. */
    protected const LOCK_WAIT = 8;

    /** Max seconds the lock is held (auto-released if a generation hangs). */
    protected const LOCK_TTL = 30;

    public function __construct(
        protected FileManipulator $fileManipulator,
    ) {}

    /**
     * The conversion names registered for a media item.
     *
     * @return list<string>
     */
    public function conversionNames(Media $media): array
    {
        return ConversionCollection::createForMedia($media)
            ->map(fn (Conversion $c) => $c->getName())
            ->values()
            ->all();
    }

    /**
     * Resolve which registered conversion a requested filename maps to.
     * Conversion files are named "{originalStem}-{conversion}.{ext}".
     */
    public function conversionForFile(Media $media, string $filename): ?string
    {
        foreach (ConversionCollection::createForMedia($media) as $conversion) {
            if ($conversion->getConversionFile($media) === $filename) {
                return $conversion->getName();
            }
        }

        return null;
    }

    /**
     * Ensure a conversion file exists on disk, generating it if missing.
     * Returns true when the file is present (already or after generating).
     */
    public function ensure(Media $media, string $conversion): bool
    {
        if (! in_array($conversion, $this->conversionNames($media), true)) {
            return false;
        }

        // Fast path: a cached positive result skips the disk stat entirely.
        if ($this->existsCached($media, $conversion)) {
            return true;
        }

        // Only one process generates a given conversion at a time. Concurrent
        // requests block on the lock, then re-check (the holder will have
        // produced the file), so we generate once under load.
        $lock = Cache::lock($this->lockKey($media, $conversion), self::LOCK_TTL);

        try {
            $lock->block(self::LOCK_WAIT);

            // Re-check after acquiring: a previous holder may have generated it.
            if ($this->fileExists($media, $conversion)) {
                $this->rememberExists($media, $conversion);

                return true;
            }

            return $this->generate($media, $conversion);
        } catch (LockTimeoutException $e) {
            // Couldn't get the lock in time — another worker is generating it.
            // Serve whatever is on disk now rather than piling on; the caller
            // falls back to the original if it's still missing.
            return $this->fileExists($media, $conversion);
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Generate the conversion SYNCHRONOUSLY. createDerivedFiles() respects
     * media-library's queue_conversions_by_default (true here), so it would only
     * dispatch a job — useless for request-time generation. performConversions()
     * runs the conversion inline instead.
     */
    protected function generate(Media $media, string $conversion): bool
    {
        $conversions = ConversionCollection::createForMedia($media)
            ->filter(fn (Conversion $c) => $c->getName() === $conversion);

        $this->fileManipulator->performConversions($conversions, $media);

        $exists = $this->fileExists($media->refresh(), $conversion);

        if ($exists) {
            $this->rememberExists($media, $conversion);
        }

        return $exists;
    }

    /**
     * Whether the conversion file physically exists on the conversions disk.
     */
    public function fileExists(Media $media, string $conversion): bool
    {
        return Storage::disk($media->conversions_disk)
            ->exists($media->getPathRelativeToRoot($conversion));
    }

    /**
     * Cached existence check. Caches only POSITIVE results (a generated file is
     * immutable until sizes change + a regenerate, which busts the cache). A
     * negative result is never cached, so a missing file is retried/generated.
     */
    protected function existsCached(Media $media, string $conversion): bool
    {
        if (Cache::get($this->existsKey($media, $conversion))) {
            return true;
        }

        if ($this->fileExists($media, $conversion)) {
            $this->rememberExists($media, $conversion);

            return true;
        }

        return false;
    }

    protected function rememberExists(Media $media, string $conversion): void
    {
        Cache::put($this->existsKey($media, $conversion), true, self::EXISTS_TTL);
    }

    /**
     * Forget the cached "exists" flag for a conversion (call after regenerating
     * or deleting so a stale positive doesn't hide a rebuilt/removed file).
     */
    public function forgetExists(Media $media, string $conversion): void
    {
        Cache::forget($this->existsKey($media, $conversion));
    }

    protected function existsKey(Media $media, string $conversion): string
    {
        return "media.exists.{$media->id}.{$conversion}";
    }

    protected function lockKey(Media $media, string $conversion): string
    {
        return "media.gen.{$media->id}.{$conversion}";
    }
}
