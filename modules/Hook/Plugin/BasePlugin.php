<?php

namespace Modules\Hook\Plugin;

use Illuminate\Contracts\Foundation\Application;
use Modules\Hook\Services\HookManager;

/**
 * Convenience base so a plugin only overrides what it needs. Sensible defaults:
 * no requirements, no-op register/boot, no-op lifecycle. Concrete plugins must
 * still define id() + version().
 */
abstract class BasePlugin implements Plugin
{
    public function requires(): array
    {
        return ['core' => '^1.0'];
    }

    public function register(Application $app): void
    {
        //
    }

    public function boot(HookManager $hooks): void
    {
        //
    }

    public function install(): void
    {
        //
    }

    public function activate(): void
    {
        //
    }

    public function deactivate(): void
    {
        //
    }

    public function uninstall(): void
    {
        //
    }
}
