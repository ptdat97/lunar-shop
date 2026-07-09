<?php

namespace Modules\Assets\Services;

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
 *
 * Lock strategy:
 *  - Uses Cache::lock() when the cache driver supports atomic locks (Redis,
 *    database cache_locks table, etc.).
 *  - Falls back to a file-based flock() lock when no atomic cache lock is
 *    available, so conversion generation always works even without Redis.
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
     * Per-request memo of conversion names, keyed by media id. Building a
     * ConversionCollection re-runs every registered conversion closure (which
     * also re-reads MediaSettings), so doing it once per media per request
     * instead of once per URL resolution matters on image-heavy pages.
     * Registered scoped() in AssetsServiceProvider.
     *
     * @var array<int, list<string>>
     */
    private array $namesMemo = [];

    /**
     * Per-request memo of POSITIVE existence results ("mediaId:conversion"),
     * layered over the cross-request cache so a page resolving the same
     * media+conversion many times makes at most one cache/disk round trip —
     * with a database cache store each Cache::get is a DB query.
     *
     * @var array<string, true>
     */
    private array $existsMemo = [];

    /**
     * The conversion names registered for a media item.
     *
     * @return list<string>
     */
    public function conversionNames(Media $media): array
    {
        return $this->namesMemo[$media->id] ??= ConversionCollection::createForMedia($media)
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

        // Try atomic cache lock first (Redis, database cache_locks table).
        // Falls back to file-based flock() when the cache driver doesn't
        // support locks (e.g. no cache_locks table, file driver without locks).
        if ($this->supportsAtomicLocks()) {
            return $this->ensureWithCacheLock($media, $conversion);
        }

        return $this->ensureWithFileLock($media, $conversion);
    }

    /**
     * Check whether the current cache driver supports atomic locks.
     */
    protected function supportsAtomicLocks(): bool
    {
        // The 'array' and 'file' drivers do not support locks.
        // The 'database' driver supports locks only if the cache_locks table exists.
        // We probe by attempting to create a test lock and immediately releasing it.
        try {
            $lock = Cache::lock('__lock_probe__', 5);
            $lock->get(function () {
                // no-op
            });

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Ensure conversion exists using Cache::lock() (Redis/database).
     */
    protected function ensureWithCacheLock(Media $media, string $conversion): bool
    {
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
     * Ensure conversion exists using file-based flock() locking.
     * Used as fallback when no atomic cache lock is available.
     */
    protected function ensureWithFileLock(Media $media, string $conversion): bool
    {
        $lockFile = $this->fileLockPath($media, $conversion);
        $lockDir = dirname($lockFile);

        if (! is_dir($lockDir)) {
            @mkdir($lockDir, 0755, true);
        }

        $fp = @fopen($lockFile, 'c');
        if (! $fp) {
            // Cannot open lock file — generate directly (best effort).
            return $this->generate($media, $conversion);
        }

        // Attempt to acquire an exclusive (write) lock with non-blocking probe.
        $locked = false;
        $start = time();

        while (! ($locked = flock($fp, LOCK_EX | LOCK_NB, $wouldBlock))) {
            if ((time() - $start) >= self::LOCK_WAIT) {
                break; // Timed out — serve whatever exists.
            }
            usleep(100_000); // 100ms between retries
        }

        if (! $locked) {
            fclose($fp);

            // Lock acquisition timed out: serve existing file or fallback.
            return $this->fileExists($media, $conversion);
        }

        // Exclusive lock acquired.
        try {
            // Re-check after acquiring: the lock holder may have generated it.
            if ($this->fileExists($media, $conversion)) {
                $this->rememberExists($media, $conversion);

                return true;
            }

            return $this->generate($media, $conversion);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
            // Clean up the lock file — it's ephemeral.
            @unlink($lockFile);
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
     * Public, non-generating existence check (cached). Serves the fast path in
     * {@see MediaUrl::conversion()} without ever producing a file.
     */
    public function exists(Media $media, string $conversion): bool
    {
        return $this->existsCached($media, $conversion);
    }

    /**
     * Cached existence check. Caches only POSITIVE results (a generated file is
     * immutable until sizes change + a regenerate, which busts the cache). A
     * negative result is never cached, so a missing file is retried/generated.
     */
    protected function existsCached(Media $media, string $conversion): bool
    {
        $memoKey = $media->id . ':' . $conversion;

        if (isset($this->existsMemo[$memoKey])) {
            return true;
        }

        if (Cache::get($this->existsKey($media, $conversion))) {
            $this->existsMemo[$memoKey] = true;

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
        $this->existsMemo[$media->id . ':' . $conversion] = true;
        Cache::put($this->existsKey($media, $conversion), true, self::EXISTS_TTL);
    }

    /**
     * Forget the cached "exists" flag for a conversion (call after regenerating
     * or deleting so a stale positive doesn't hide a rebuilt/removed file).
     * Also reopens the pre-warm dedupe window ({@see MediaUrl::queueWarm()}) so
     * the wiped size can be re-queued immediately, not after the window expires.
     */
    public function forgetExists(Media $media, string $conversion): void
    {
        unset($this->existsMemo[$media->id . ':' . $conversion]);
        Cache::forget($this->existsKey($media, $conversion));
        Cache::forget($this->warmDedupeKey($media, $conversion));
    }

    /**
     * Cache key that collapses repeated pre-warm dispatches for one conversion.
     * Owned here (next to the other conversion-state keys) so forgetExists()
     * can invalidate it together with the "exists" flag.
     */
    public function warmDedupeKey(Media $media, string $conversion): string
    {
        return "media.warm.{$media->id}.{$conversion}";
    }

    protected function existsKey(Media $media, string $conversion): string
    {
        return "media.exists.{$media->id}.{$conversion}";
    }

    protected function lockKey(Media $media, string $conversion): string
    {
        return "media.gen.{$media->id}.{$conversion}";
    }

    /**
     * Path to the file-based lock file for a media conversion.
     * Lock files are stored in storage/framework/cache/media-locks/.
     */
    protected function fileLockPath(Media $media, string $conversion): string
    {
        $hash = md5("{$media->id}_{$conversion}");

        return storage_path("framework/cache/media-locks/{$hash}.lock");
    }
}