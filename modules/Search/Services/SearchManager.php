<?php

namespace Modules\Search\Services;

use Illuminate\Contracts\Foundation\Application;
use Modules\Search\Contracts\SearchEngine;

/**
 * Runtime registry of search drivers. A driver is a SearchEngine factory keyed
 * by name; the active one (config('search.driver')) is resolved on demand.
 *
 * Built so a PLUGIN can contribute a driver at boot via extend() — without
 * editing config/search.php. Backward-compatible: drivers declared in
 * config('search.drivers') as class strings are still honoured as a fallback,
 * so existing setups keep working.
 */
class SearchManager
{
    /**
     * Registered drivers: name => (Closure(Application): SearchEngine | class-string).
     *
     * @var array<string, \Closure|string>
     */
    protected array $drivers = [];

    public function __construct(
        protected Application $app,
    ) {}

    /**
     * Register (or override) a driver. $factory is a class-string or a closure
     * receiving the container and returning a SearchEngine.
     *
     * @param  \Closure(Application): SearchEngine|class-string  $factory
     */
    public function extend(string $name, \Closure|string $factory): void
    {
        $this->drivers[$name] = $factory;
    }

    /** Whether a driver name is resolvable (registered at runtime or in config). */
    public function has(string $name): bool
    {
        return isset($this->drivers[$name]) || filled(config("search.drivers.{$name}"));
    }

    /**
     * Resolve a driver to a SearchEngine instance. Runtime registry wins over the
     * config map; unknown names fail loudly.
     */
    public function driver(?string $name = null): SearchEngine
    {
        $name ??= (string) config('search.driver', 'database');

        $factory = $this->drivers[$name] ?? config("search.drivers.{$name}");

        abort_unless($factory, 500, "Unknown search driver [{$name}].");

        return $factory instanceof \Closure
            ? $factory($this->app)
            : $this->app->make($factory);
    }
}
