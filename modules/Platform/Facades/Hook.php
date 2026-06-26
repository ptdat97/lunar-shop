<?php

namespace Modules\Platform\Facades;

use Illuminate\Support\Facades\Facade;
use Modules\Platform\Services\HookManager;

/**
 * @method static void addFilter(string $hook, callable $callback, int $priority = 50)
 * @method static void addAction(string $hook, callable $callback, int $priority = 50)
 * @method static mixed applyFilters(string $hook, mixed $value, array $args = [])
 * @method static void doAction(string $hook, array $args = [])
 * @method static bool has(string $hook)
 * @method static void forget(string $hook)
 *
 * @see \Modules\Platform\Services\HookManager
 */
class Hook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HookManager::class;
    }
}
