<?php

namespace Modules\Theme\Support;

/**
 * Collects custom Filament page classes contributed by modules, so they can be
 * registered into Lunar's admin panel in one place (after all modules have
 * registered). Modules call AdminPages::add(...) in their register().
 */
class AdminPages
{
    /** @var array<int, class-string> */
    protected static array $pages = [];

    public static function add(string ...$pages): void
    {
        foreach ($pages as $page) {
            if (! in_array($page, static::$pages, true)) {
                static::$pages[] = $page;
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
}
