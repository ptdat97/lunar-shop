<?php

namespace Acme\ScoutSearch;

use Modules\Platform\Plugin\BasePlugin;
use Modules\Platform\Services\HookManager;
use Modules\Search\Services\SearchManager;

/**
 * Third reference plugin (driver-as-plugin): registers a Scout-backed search
 * driver via SearchManager::extend — no edit to the Search module or config.
 * Enable it, then set SEARCH_DRIVER=scout (and a SCOUT_DRIVER) to switch the
 * storefront/API search engine; the SearchEngine contract callers don't change.
 */
class ScoutSearchPlugin extends BasePlugin
{
    public function id(): string
    {
        return 'acme/scout-search';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function boot(HookManager $hooks): void
    {
        app(SearchManager::class)->extend('scout', ScoutSearchEngine::class);
    }
}
