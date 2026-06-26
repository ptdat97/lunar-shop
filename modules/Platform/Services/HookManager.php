<?php

namespace Modules\Platform\Services;

/**
 * Lightweight WordPress/Eventy-style action & filter hooks for cross-module
 * extension — without pulling in a vendor dependency.
 *
 * Two primitives:
 *  - ACTIONS (`doAction`): fire-and-forget side effects. Listeners receive the
 *    args and return nothing; useful for "something happened" notifications
 *    (e.g. order paid → award loyalty points).
 *  - FILTERS (`applyFilters`): transform a value through a chain. Each listener
 *    receives the running value (plus extra args) and returns the next value;
 *    useful for "let other modules adjust this" (e.g. product price, search
 *    facets, whether a variant is purchasable).
 *
 * Listeners are ordered by integer priority (lower runs first; default 50),
 * mirroring Eventy/WordPress so modules can position themselves deterministically.
 *
 * This keeps the plan's boundary rule — modules talk via published services or
 * hooks, never each other's models — with one shared, testable seam.
 */
class HookManager
{
    /**
     * Registered listeners, grouped by hook name.
     *
     * @var array<string, array<int, array{priority:int, callback:callable}>>
     */
    protected array $listeners = [];

    /** Default priority when a caller doesn't specify one. */
    public const DEFAULT_PRIORITY = 50;

    /**
     * Register a filter listener: `fn($value, ...$args): mixed`.
     */
    public function addFilter(string $hook, callable $callback, int $priority = self::DEFAULT_PRIORITY): void
    {
        $this->listeners[$hook][] = ['priority' => $priority, 'callback' => $callback];
    }

    /**
     * Register an action listener: `fn(...$args): void`.
     */
    public function addAction(string $hook, callable $callback, int $priority = self::DEFAULT_PRIORITY): void
    {
        $this->addFilter($hook, $callback, $priority);
    }

    /**
     * Run $value through every filter registered for $hook (priority order) and
     * return the final value. Extra args are passed to each listener after the
     * value but are not themselves transformed.
     *
     * @param  array<int, mixed>  $args
     */
    public function applyFilters(string $hook, mixed $value, array $args = []): mixed
    {
        foreach ($this->sorted($hook) as $listener) {
            $value = ($listener['callback'])($value, ...$args);
        }

        return $value;
    }

    /**
     * Fire an action: every listener runs for its side effects; return values
     * are ignored.
     *
     * @param  array<int, mixed>  $args
     */
    public function doAction(string $hook, array $args = []): void
    {
        foreach ($this->sorted($hook) as $listener) {
            ($listener['callback'])(...$args);
        }
    }

    /**
     * Whether any listener is registered for a hook.
     */
    public function has(string $hook): bool
    {
        return ! empty($this->listeners[$hook]);
    }

    /**
     * Remove all listeners for a hook (mostly for tests / re-registration).
     */
    public function forget(string $hook): void
    {
        unset($this->listeners[$hook]);
    }

    /**
     * Listeners for a hook, sorted by ascending priority (stable for equal
     * priorities → registration order is preserved).
     *
     * @return array<int, array{priority:int, callback:callable}>
     */
    protected function sorted(string $hook): array
    {
        $listeners = $this->listeners[$hook] ?? [];

        usort($listeners, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return $listeners;
    }
}
