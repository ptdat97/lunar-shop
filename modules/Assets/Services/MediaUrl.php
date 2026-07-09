<?php

namespace Modules\Assets\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Assets\Jobs\GenerateConversionJob;
use Modules\Assets\Services\MediaSettings;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Resolves a media conversion URL, generating the conversion on demand when its
 * file is missing. Single entry point used by API Resources + Blade so every
 * image URL self-heals: a not-yet-generated (or wiped) size is produced the
 * first time it's requested, then served as a static file thereafter.
 */
class MediaUrl
{
    /**
     * Per-request memo of resolved conversion URLs, keyed by "mediaId:conversion".
     * The view composer calls conversion() once per product card per conversion
     * name; without memoization a 24-card grid would re-resolve the same media
     * item 24+ times, each triggering a filesystem stat in ensure().
     *
     * @var array<string, string|null>
     */
    private array $conversionMemo = [];

    /**
     * Per-request memo of responsive payloads, keyed by "mediaId:implode(widths):base".
     *
     * @var array<string, array|null>
     */
    private array $responsiveMemo = [];

    public function __construct(
        protected ConversionGenerator $generator,
    ) {}

    /**
     * URL for a conversion. Two modes (config `lunar.media.on_demand.sync`):
     *
     *  - ASYNC (default): never block the page render — return the exact
     *    conversion URL even when its file is missing. The file is produced by
     *    whichever comes first: the pre-warm job on the `media` queue, or the
     *    browser's own image request falling through to the media.conversion
     *    route (missing file → Laravel), which generates it inline. So images
     *    work with or without Horizon running.
     *  - SYNC: generate the missing conversion inline during the page render,
     *    then serve it (always-correct, but a render with many missing sizes
     *    generates them serially).
     *
     * Either way the returned URL is always usable. Results are memoized
     * per-request so the same media+conversion pair resolves in O(1) after the
     * first call (a 24-card grid re-asks the same URLs many times).
     */
    public function conversion(?Media $media, string $conversion): ?string
    {
        if (! $media) {
            return null;
        }

        $key = $media->id . ':' . $conversion;

        if (array_key_exists($key, $this->conversionMemo)) {
            return $this->conversionMemo[$key];
        }

        // Unknown conversion name → original (no generation possible).
        if (! in_array($conversion, $this->generator->conversionNames($media), true)) {
            return $this->conversionMemo[$key] = $media->getUrl();
        }

        // Already on disk (cheap cached check) → serve it directly.
        if ($this->generator->exists($media, $conversion)) {
            return $this->conversionMemo[$key] = $media->getUrl($conversion);
        }

        if ($this->syncOnDemand()) {
            if ($this->generator->ensure($media, $conversion)) {
                return $this->conversionMemo[$key] = $media->getUrl($conversion);
            }

            return $this->conversionMemo[$key] = $media->getUrl();
        }

        // ASYNC: return the exact URL — the browser's request self-heals it via
        // the media.conversion route, and the queued job pre-warms the rest.
        $this->queueWarm($media, $conversion);

        return $this->conversionMemo[$key] = $media->getUrl($conversion);
    }

    /**
     * Dispatch a pre-warm job for one conversion, collapsed across requests: a
     * hot page re-resolving the same missing size on every view (e.g. while no
     * worker is running) queues it once per window instead of flooding the
     * `media` queue with duplicates. Cache::add is atomic on every driver.
     */
    protected function queueWarm(Media $media, string $conversion): void
    {
        if (Cache::add($this->generator->warmDedupeKey($media, $conversion), true, 600)) {
            GenerateConversionJob::dispatch($media->id, $conversion);
        }
    }

    /** Whether on-demand generation runs inline (sync) or defers to the queue. */
    protected function syncOnDemand(): bool
    {
        return (bool) app(\Modules\Core\Support\Settings::class)
            ->get('media.on_demand_sync', config('lunar.media.on_demand.sync', false));
    }

    /**
     * Build a responsive image payload for a <picture>/srcset in the theme.
     *
     * Returns the same self-healing conversion URLs (each ensured on demand) as
     * a width-descriptor srcset plus a WebP source, so the browser can pick the
     * smallest sufficient file (mobile data + Core Web Vitals). `$base` is the
     * conversion used for the <img src> fallback (no-srcset / oldest browsers).
     *
     * Shape (null when no media):
     *   ['src','srcset','webp','width','height']
     *
     * @param  list<string>  $widths  conversion names to include, small→large
     * @return array{src:string, srcset:string, webp:?string, width:int, height:int}|null
     */
    public function responsive(?Media $media, array $widths = ['small', 'medium', 'large'], string $base = 'medium'): ?array
    {
        if (! $media) {
            return null;
        }

        $memoKey = $media->id . ':' . implode(',', $widths) . ':' . $base;

        if (array_key_exists($memoKey, $this->responsiveMemo)) {
            return $this->responsiveMemo[$memoKey];
        }

        $sizes = app(MediaSettings::class)->sizes();

        // Build "<url> <w>w" entries for each requested conversion that resolves.
        $entries = [];
        foreach ($widths as $name) {
            $w = $sizes[$name]['width'] ?? null;
            $url = $this->conversion($media, $name);
            if ($w && $url) {
                $entries[$w] = "{$url} {$w}w";
            }
        }

        if (empty($entries)) {
            // No conversions available — fall back to the original as a 1x src.
            $src = $media->getUrl();

            return $this->responsiveMemo[$memoKey] = ['src' => $src, 'srcset' => '', 'webp' => null, 'width' => 0, 'height' => 0];
        }

        ksort($entries);

        $src = $this->conversion($media, $base) ?? reset($entries);
        $baseSize = $sizes[$base] ?? $sizes['large'] ?? ['width' => 0, 'height' => 0];

        // Single WebP source at the largest configured size (the `webp`
        // conversion is registered at the `large` box in FashionMediaDefinitions).
        $webp = $this->conversion($media, 'webp');

        return $this->responsiveMemo[$memoKey] = [
            'src' => $src,
            'srcset' => implode(', ', $entries),
            'webp' => $webp,
            'width' => (int) ($baseSize['width'] ?? 0),
            'height' => (int) ($baseSize['height'] ?? 0),
        ];
    }

    /**
     * Pre-warm all conversions for a media item on the `media` queue, so the
     * first storefront visitor doesn't pay the synchronous generation cost.
     * Call after an upload (or ahead of a campaign). No-op / idempotent: each
     * job re-checks existence under a lock. Skips conversions already on disk.
     */
    public function warm(?Media $media): void
    {
        if (! $media) {
            return;
        }

        foreach ($this->generator->conversionNames($media) as $conversion) {
            if ($this->generator->fileExists($media, $conversion)) {
                continue;
            }

            $this->queueWarm($media, $conversion);
        }
    }
}
