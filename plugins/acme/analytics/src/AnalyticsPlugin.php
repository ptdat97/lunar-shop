<?php

namespace Acme\Analytics;

use Acme\Analytics\Filament\AnalyticsDashboard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\View;
use Modules\Platform\Plugin\BasePlugin;
use Modules\Platform\Services\HookManager;
use Modules\Platform\Support\AdminPages;

/**
 * Sales analytics as a first-party plugin (Phase 4: extracted from the Analytics
 * module — ZERO behaviour change). It reads Lunar's order data (no own tables)
 * and contributes a Filament dashboard to the admin panel. Admin-only; enabled
 * by default in config/plugins.php so the dashboard shows out of the box.
 *
 * AdminPages::add must run in register() — the panel collects contributed pages
 * during the register phase (PluginManager::load runs before the Lunar panel is
 * built), exactly like a core module.
 */
class AnalyticsPlugin extends BasePlugin
{
    protected string $dir;

    public function __construct()
    {
        $this->dir = dirname(__DIR__);
    }

    public function id(): string
    {
        return 'acme/analytics';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function register(Application $app): void
    {
        $app['config']->set('analytics', array_merge(
            require $this->dir . '/config/analytics.php',
            (array) $app['config']->get('analytics', []),
        ));

        AdminPages::add(AnalyticsDashboard::class);
    }

    public function boot(HookManager $hooks): void
    {
        View::addNamespace('analytics', $this->dir . '/resources/views');
    }
}
