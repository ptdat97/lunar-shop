<?php

namespace Modules\Assets\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin-configurable image conversion sizes (small/medium/large/zoom).
 * Stored in the media_settings table, cached for hot reads. The media
 * definition reads from here so admins can change sizes without code.
 */
class MediaSettings
{
    protected const KEY = 'image_sizes';
    protected const CACHE_KEY = 'media.image_sizes';

    /**
     * Built-in defaults (used until an admin overrides them).
     *
     * @return array<string, array{width:int, height:int}>
     */
    /**
     * Default aspect ratio (width:height) used to derive default heights.
     * 2:3 portrait suits fashion product imagery.
     */
    public const DEFAULT_RATIO = [2, 3];

    public static function defaults(): array
    {
        // Heights derived from width using the 2:3 portrait ratio.
        return [
            'small' => static::ratioSize(200),   // 200x300
            'medium' => static::ratioSize(400),  // 400x600
            'large' => static::ratioSize(1000),  // 1000x1500
            'zoom' => static::ratioSize(1600),   // 1600x2400 (high-res zoom)
        ];
    }

    /**
     * Build a width/height pair for the given width using DEFAULT_RATIO.
     *
     * @return array{width:int, height:int}
     */
    protected static function ratioSize(int $width): array
    {
        [$rw, $rh] = static::DEFAULT_RATIO;

        return [
            'width' => $width,
            'height' => (int) round($width * $rh / $rw),
        ];
    }

    /**
     * Conversion keys that are admin-configurable.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(static::defaults());
    }

    /**
     * Current sizes (admin overrides merged over defaults).
     *
     * @return array<string, array{width:int, height:int}>
     */
    public function sizes(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            $row = DB::table('media_settings')->where('key', self::KEY)->value('value');

            return $row ? json_decode($row, true) : [];
        });

        $sizes = static::defaults();

        foreach ($sizes as $key => $default) {
            if (isset($stored[$key]['width'], $stored[$key]['height'])) {
                $sizes[$key] = [
                    'width' => (int) $stored[$key]['width'],
                    'height' => (int) $stored[$key]['height'],
                ];
            }
        }

        return $sizes;
    }

    /**
     * Persist new sizes and bust the cache.
     *
     * @param  array<string, array{width:int, height:int}>  $sizes
     */
    public function save(array $sizes): void
    {
        $clean = [];

        foreach (static::keys() as $key) {
            $clean[$key] = [
                'width' => max(1, (int) ($sizes[$key]['width'] ?? static::defaults()[$key]['width'])),
                'height' => max(1, (int) ($sizes[$key]['height'] ?? static::defaults()[$key]['height'])),
            ];
        }

        DB::table('media_settings')->updateOrInsert(
            ['key' => self::KEY],
            ['value' => json_encode($clean), 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::forget(self::CACHE_KEY);
    }
}
