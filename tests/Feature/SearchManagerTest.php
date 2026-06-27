<?php

namespace Tests\Feature;

use Acme\ScoutSearch\ScoutSearchEngine;
use Modules\Search\Contracts\SearchEngine;
use Modules\Search\Drivers\DatabaseSearchEngine;
use Modules\Search\Services\SearchManager;
use Tests\TestCase;

/**
 * E.2 — the SearchManager driver registry. A plugin contributes a driver via
 * extend() (the acme/scout-search plugin ships the scout driver, no longer in
 * config); the active driver resolves through the manager. Backward-compatible:
 * config-declared drivers (database) still resolve.
 */
class SearchManagerTest extends TestCase
{
    public function test_it_resolves_the_config_database_driver(): void
    {
        $engine = app(SearchManager::class)->driver('database');

        $this->assertInstanceOf(DatabaseSearchEngine::class, $engine);
        $this->assertInstanceOf(SearchEngine::class, $engine);
    }

    public function test_the_search_engine_binding_goes_through_the_manager(): void
    {
        // Default driver is database → SearchEngine resolves to it.
        $this->assertInstanceOf(DatabaseSearchEngine::class, app(SearchEngine::class));
    }

    public function test_scout_is_not_a_builtin_config_driver(): void
    {
        // Moved out of config into the plugin — unknown until the plugin extends it.
        $this->assertFalse(app(SearchManager::class)->has('scout'));
    }

    public function test_a_plugin_can_register_a_driver_via_extend(): void
    {
        $manager = app(SearchManager::class);
        $manager->extend('scout', ScoutSearchEngine::class);

        $this->assertTrue($manager->has('scout'));
        $this->assertInstanceOf(ScoutSearchEngine::class, $manager->driver('scout'));
    }

    public function test_runtime_driver_overrides_config(): void
    {
        $manager = app(SearchManager::class);
        $manager->extend('database', fn () => new ScoutSearchEngine);

        // Runtime registry wins over the config class string.
        $this->assertInstanceOf(ScoutSearchEngine::class, $manager->driver('database'));
    }

    public function test_unknown_driver_fails_loudly(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(SearchManager::class)->driver('does-not-exist');
    }
}
