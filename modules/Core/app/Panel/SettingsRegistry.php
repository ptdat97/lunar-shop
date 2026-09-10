<?php

namespace Modules\Core\Panel;

use Closure;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Panel\SettingsController;

/**
 * Every declared SettingsGroup, in tab order. Each module registers its own
 * from its provider; the panel gets one settings screen out of them.
 */
class SettingsRegistry
{
    /** @var array<string, SettingsGroup> */
    protected array $groups = [];

    public function add(SettingsGroup $group): static
    {
        $this->groups[$group->key()] = $group;

        return $this;
    }

    public function get(string $key): ?SettingsGroup
    {
        return $this->groups[$key] ?? null;
    }

    /** @return array<int, SettingsGroup> */
    public function all(): array
    {
        $groups = array_values($this->groups);

        usort($groups, fn (SettingsGroup $a, SettingsGroup $b) => $a->priority() <=> $b->priority());

        return $groups;
    }

    public function routes(): Closure
    {
        return function (): void {
            // A named route per group, not one route with a {group} parameter:
            // NavigationItem builds its URL with `route($name)` and no
            // arguments, so a parameterised route would silently resolve to
            // null and the sidebar entry would go nowhere.
            //
            // Each also carries its own `can:`, so a tab a user cannot see is
            // not reachable by editing the URL either.
            foreach ($this->all() as $group) {
                $key = $group->key();

                Route::prefix("settings/shop/{$key}")
                    ->name("panel.shop.settings.{$key}.")
                    ->middleware('can:'.$group->permission())
                    ->group(function () use ($key): void {
                        Route::get('/', [SettingsController::class, 'edit'])
                            ->defaults('settingsKey', $key)->name('edit');
                        Route::put('/', [SettingsController::class, 'update'])
                            ->defaults('settingsKey', $key)->name('update');
                    });
            }
        };
    }
}
