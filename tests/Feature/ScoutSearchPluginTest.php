<?php

namespace Tests\Feature;

use Acme\ScoutSearch\ScoutSearchEngine;
use Modules\Platform\Plugin\PluginManager;
use Modules\Search\Contracts\SearchEngine;
use Modules\Search\Services\SearchManager;
use Tests\TestCase;

/**
 * E.2 dogfood — the acme/scout-search plugin registers a search driver purely
 * through SearchManager::extend, ZERO edits to the Search module or config. With
 * the plugin enabled + SEARCH_DRIVER=scout, the SearchEngine contract resolves
 * to the plugin's driver — proving driver-as-plugin works end-to-end.
 */
class ScoutSearchPluginTest extends TestCase
{
    protected function bootScoutPlugin(): void
    {
        config(['plugins.enabled' => ['acme/scout-search']]);

        $manager = new PluginManager($this->app);
        $manager->load();
        $manager->boot();
    }

    public function test_plugin_registers_the_scout_driver(): void
    {
        $this->assertFalse(app(SearchManager::class)->has('scout'));

        $this->bootScoutPlugin();

        $this->assertTrue(app(SearchManager::class)->has('scout'));
        $this->assertInstanceOf(ScoutSearchEngine::class, app(SearchManager::class)->driver('scout'));
    }

    public function test_search_engine_resolves_to_scout_when_selected(): void
    {
        config(['search.driver' => 'scout']);
        $this->bootScoutPlugin();

        // Re-resolve through the manager (the binding picks the active driver).
        $this->assertInstanceOf(ScoutSearchEngine::class, app(SearchManager::class)->driver());
        $this->assertInstanceOf(SearchEngine::class, app(SearchManager::class)->driver());
    }
}
