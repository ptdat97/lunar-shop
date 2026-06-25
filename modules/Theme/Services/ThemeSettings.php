<?php

namespace Modules\Theme\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin-configurable theme content (logo, topbar, social, contact, payment).
 * Stored as key → JSON value in theme_settings, cached, with dot-path reads.
 */
class ThemeSettings
{
    protected const CACHE_KEY = 'theme.settings';

    /**
     * All settings merged over defaults, keyed by top-level group.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            return DB::table('theme_settings')->pluck('value', 'key')
                ->map(fn ($v) => json_decode($v, true))
                ->all();
        });

        // Shallow replace: any group the admin has saved (present in DB) wins
        // entirely. Recursive merge would resurrect deleted list items
        // (e.g. clearing all social links would still show the defaults).
        return array_replace(static::defaults(), $stored);
    }

    /**
     * Read a setting by dot path, e.g. theme_setting('contact.email').
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    /**
     * Resolve an image path to a URL: uploaded files are stored as a relative
     * path on the "media" disk; template defaults are absolute "/themes/..."
     * paths or full URLs.
     */
    public function url(?string $path): string
    {
        if (! $path) {
            return '';
        }

        return \Illuminate\Support\Str::startsWith($path, ['/', 'http'])
            ? $path
            : \Illuminate\Support\Facades\Storage::disk('media')->url($path);
    }

    /**
     * Resolve an image setting (by dot path) to a URL.
     */
    public function image(string $key, ?string $default = null): string
    {
        return $this->url($this->get($key, $default));
    }

    /**
     * Persist a group (top-level key) and bust the cache.
     *
     * @param  array<string, mixed>  $value
     */
    public function set(string $key, array $value): void
    {
        DB::table('theme_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Persist a scalar value (e.g. copyright string).
     */
    public function setScalar(string $key, string|int|float|null $value): void
    {
        DB::table('theme_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::forget(self::CACHE_KEY);
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Built-in defaults for the storefront theme (overridable in admin).
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'general' => [
                // No bundled logo/favicon by default — the theme falls back to the
                // site name as text. Admins upload brand assets via Theme settings.
                'logo' => '',
                'logo_footer' => '',
                'favicon' => '',
            ],
            'topbar' => [
                ['text' => 'Free shipping on all orders over $20.00'],
                ['text' => 'Midseason Sale: 20% Off - Auto Applied at Checkout - Limited Time Only.'],
            ],
            'social' => [
                ['icon' => 'icon-fb', 'url' => '#'],
                ['icon' => 'icon-x', 'url' => '#'],
                ['icon' => 'icon-instagram', 'url' => '#'],
                ['icon' => 'icon-tiktok', 'url' => '#'],
                ['icon' => 'icon-amazon', 'url' => '#'],
                ['icon' => 'icon-pinterest', 'url' => '#'],
            ],
            'contact' => [
                'address' => '549 Oak St.Crystal Lake, IL 60014',
                'email' => 'info@fashionshop.com',
                'phone' => '(123) 456-7890',
            ],
            'newsletter' => [
                'heading' => 'Sign up for our newsletter and get 10% off your first purchase',
            ],
            // Payment badge images are admin-uploaded; none bundled by default.
            'payment' => [],
            // Storefront language. For a single-market shop, enable one locale and
            // turn the switcher off. `enabled` is a list of locale codes (subset of
            // config('theme.locales')); `default` must be one of them.
            'language' => [
                'enabled' => array_keys((array) config('theme.locales', ['en' => 'English'])),
                'default' => (string) (array_key_first((array) config('theme.locales', ['en' => 'English'])) ?? 'en'),
                'show_switcher' => true,
            ],
            'copyright' => '© '.date('Y').' Fashion Store. All Rights Reserved.',
        ];
    }
}
