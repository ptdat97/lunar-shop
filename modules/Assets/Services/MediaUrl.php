<?php

namespace Modules\Assets\Services;

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
     * URL for a conversion, generating it if the file is missing. Falls back to
     * the original when the conversion can't be produced (e.g. unknown name).
     *
     * Results are memoized per-request so the same media+conversion pair called
     * across multiple view composers resolves in O(1) after the first call.
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

        if ($this->generator->ensure($media, $conversion)) {
            return $this->conversionMemo[$key] = $media->getUrl($conversion);
        }

        return $this->conversionMemo[$key] = $media->getUrl();
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

            return ['src' => $src, 'srcset' => '', 'webp' => null, 'width' => 0, 'height' => 0];
        }

        ksort($entries);

        $src = $this->conversion($media, $base) ?? reset($entries);
        $baseSize = $sizes[$base] ?? $sizes['large'] ?? ['width' => 0, 'height' => 0];

        // Single WebP source at the largest configured size (the `webp`
        // conversion is registered at the `large` box in FashionMediaDefinitions).
        $webp = $this->conversion($media, 'webp');

        return [
            'src' => $src,
            'srcset' => implode(', ', $entries),
            'webp' => $webp,
            'width' => (int) ($baseSize['width'] ?? 0),
            'height' => (int) ($baseSize['height'] ?? 0),
        ];
    }
}
