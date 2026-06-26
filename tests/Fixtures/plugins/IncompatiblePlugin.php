<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Foundation\Application;
use Modules\Platform\Plugin\BasePlugin;

/** Test fixture: needs core ^2.0 — must be skipped on a 1.x app. */
class IncompatiblePlugin extends BasePlugin
{
    public function id(): string
    {
        return 'acme/future';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function requires(): array
    {
        return ['core' => '^2.0'];
    }

    public function register(Application $app): void
    {
        DemoPlugin::$trace[] = 'register:acme/future';
    }
}
