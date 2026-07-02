<?php

namespace Modules\Core\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;

/**
 * Re-applies module-local config overrides on top of Lunar's published config.
 *
 * Lunar publishes its defaults to config/lunar/*.php, and `vendor:publish
 * --tag=lunar --force` overwrites those files. To keep our customisations
 * (media definitions, payment drivers, cart flags, …) safe, modules store the
 * changed keys in modules/<Name>/Config/*.php and call this from their boot()
 * method — which runs after Lunar has merged its own config, so our values win.
 *
 * Unlike Laravel's mergeConfigFrom (which only fills MISSING keys), this does a
 * recursive merge where the override value REPLACES the existing one, so we can
 * change a Lunar default (e.g. swap a media definition class), not just add to it.
 */
class LunarConfigOverride
{
    /**
     * Deep-merge $overrides onto the config at $key (dot notation).
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function apply(string $key, array $overrides): void
    {
        $current = Config::get($key, []);

        Config::set($key, static::merge(is_array($current) ? $current : [], $overrides));
    }

    /**
     * Load an override array from a module config file and apply it.
     */
    public static function applyFrom(string $key, string $path): void
    {
        if (is_file($path)) {
            static::apply($key, require $path);
        }
    }

    /**
     * Recursive array merge where scalar/list override values replace the base,
     * and associative arrays merge key-by-key.
     *
     * @param  array<mixed>  $base
     * @param  array<mixed>  $overrides
     * @return array<mixed>
     */
    protected static function merge(array $base, array $overrides): array
    {
        foreach ($overrides as $k => $value) {
            if (is_array($value) && Arr::isAssoc($value) && isset($base[$k]) && is_array($base[$k])) {
                $base[$k] = static::merge($base[$k], $value);
            } else {
                $base[$k] = $value;
            }
        }

        return $base;
    }
}
