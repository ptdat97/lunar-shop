<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin-configurable feature settings, stored as key → JSON in `app_settings`
 * and cached. A "group" is a top-level key (e.g. 'payment', 'shipping'); each
 * group holds an associative array the admin edits via a Filament settings page.
 *
 * Reads fall back to the matching config value when a group/path isn't stored,
 * so shipping code can keep calling this and works out-of-the-box from
 * config/env, then honours admin overrides once saved.
 */
class Settings
{
    protected const CACHE_KEY = 'app.settings';

    /**
     * All stored groups (raw), cached. @return array<string, mixed>
     */
    protected function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return DB::table('app_settings')->pluck('value', 'key')
                ->map(fn ($v) => json_decode($v, true))
                ->all();
        });
    }

    /**
     * A whole group merged over a config fallback, e.g. group('shipping',
     * config('shipping')). Stored keys win; missing keys fall back to config.
     *
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    public function group(string $group, array $fallback = []): array
    {
        $stored = $this->stored()[$group] ?? [];

        return array_replace($fallback, is_array($stored) ? $stored : []);
    }

    /**
     * Read a dot-path value ("payment.vnpay.tmn_code"), falling back to the
     * matching config path when unset.
     */
    public function get(string $path, mixed $default = null): mixed
    {
        [$group, $rest] = array_pad(explode('.', $path, 2), 2, null);

        $stored = $this->stored()[$group] ?? null;

        if ($rest === null) {
            return $stored ?? config($path, $default);
        }

        $value = is_array($stored) ? Arr::get($stored, $rest, null) : null;

        // Fall back to config for any key the admin hasn't set.
        return $value ?? config($path, $default);
    }

    /**
     * Persist a whole group (associative array) and bust the cache.
     *
     * @param  array<string, mixed>  $value
     */
    public function put(string $group, array $value): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['key' => $group],
            ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::forget(self::CACHE_KEY);
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
