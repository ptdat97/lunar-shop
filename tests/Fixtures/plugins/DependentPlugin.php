<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Foundation\Application;
use Modules\Platform\Plugin\BasePlugin;
use Modules\Platform\Services\HookManager;

/** Test fixture: depends on acme/demo — must register AFTER it. */
class DependentPlugin extends BasePlugin
{
    public function id(): string
    {
        return 'acme/addon';
    }

    public function version(): string
    {
        return '0.1.0';
    }

    public function requires(): array
    {
        return ['core' => '^1.0', 'acme/demo' => '^1.0'];
    }

    public function register(Application $app): void
    {
        DemoPlugin::$trace[] = 'register:acme/addon';
    }

    public function boot(HookManager $hooks): void
    {
        DemoPlugin::$trace[] = 'boot:acme/addon';
    }
}
