<?php

namespace Modules\Theme\Support;

/**
 * Collects custom Filament pages and resources contributed by modules, so they
 * can be registered into Lunar's admin panel in one place (after all modules
 * have registered). Modules call AdminPages::add(...) / addResource(...) in
 * their register().
 */
class AdminPages
{
    /** @var array<int, class-string> */
    protected static array $pages = [];

    /** @var array<int, class-string> */
    protected static array $resources = [];

    public static function add(string ...$pages): void
    {
        foreach ($pages as $page) {
            if (! in_array($page, static::$pages, true)) {
                static::$pages[] = $page;
            }
        }
    }

    public static function addResource(string ...$resources): void
    {
        foreach ($resources as $resource) {
            if (! in_array($resource, static::$resources, true)) {
                static::$resources[] = $resource;
            }
        }
    }

    /**
     * @return array<int, class-string>
     */
    public static function all(): array
    {
        return static::$pages;
    }

    /**
     * @return array<int, class-string>
     */
    public static function resources(): array
    {
        return static::$resources;
    }
}
