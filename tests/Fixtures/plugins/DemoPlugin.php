<?php

namespace Tests\Fixtures\Plugins;

use Illuminate\Contracts\Foundation\Application;
use Modules\Platform\Plugin\BasePlugin;
use Modules\Platform\Services\HookManager;

/**
 * Test fixture: a minimal plugin that records when it registers/boots and adds
 * a hook listener, to prove the PluginManager pipeline end-to-end.
 */
class DemoPlugin extends BasePlugin
{
    /** @var list<string> shared trace of lifecycle calls across the test */
    public static array $trace = [];

    public function id(): string
    {
        return 'acme/demo';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function requires(): array
    {
        return ['core' => '^1.0'];
    }

    public function register(Application $app): void
    {
        self::$trace[] = 'register:acme/demo';
    }

    public function boot(HookManager $hooks): void
    {
        self::$trace[] = 'boot:acme/demo';
        $hooks->addAction('demo.ping', function () {
            self::$trace[] = 'ping:acme/demo';
        });
    }

    public function install(): void
    {
        self::$trace[] = 'install:acme/demo';
    }

    public function activate(): void
    {
        self::$trace[] = 'activate:acme/demo';
    }

    public function deactivate(): void
    {
        self::$trace[] = 'deactivate:acme/demo';
    }

    public function uninstall(): void
    {
        self::$trace[] = 'uninstall:acme/demo';
    }
}
