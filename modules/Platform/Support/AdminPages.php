<?php

namespace Modules\Platform\Support;

/**
 * Platform extension registry for the admin panel: collects Filament pages and
 * resources contributed by modules AND plugins, so they're registered into
 * Lunar's panel in one place (after everything has registered). Callers do
 * AdminPages::add(...) / addResource(...) in their register().
 *
 * Lives in Platform (core) rather than a business module — it's an extension
 * point, not presentation. Knows nothing about any specific page/resource.
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
