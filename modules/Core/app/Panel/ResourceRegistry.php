<?php

namespace Modules\Core\Panel;

use Closure;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Panel\ResourceController;

/**
 * Holds every declared PanelResource and turns each into panel routes.
 *
 * Registered from each module's service provider (Content owns its banners and
 * pages, Order its returns, and so on) so a module still declares its own admin
 * surface; only the plumbing is shared.
 */
class ResourceRegistry
{
    /** @var array<string, PanelResource> */
    protected array $resources = [];

    public function add(PanelResource $resource): static
    {
        $this->resources[$resource->key()] = $resource;

        return $this;
    }

    public function get(string $key): ?PanelResource
    {
        return $this->resources[$key] ?? null;
    }

    /** @return array<string, PanelResource> */
    public function all(): array
    {
        return $this->resources;
    }

    /** @return array<int, PanelResource> */
    public function forSection(string $sectionKey): array
    {
        return array_values(array_filter(
            $this->resources,
            fn (PanelResource $r) => $r->section() === $sectionKey,
        ));
    }

    /**
     * The route closure a Section hands back from `routes()`. Every resource in
     * the section gets the same seven routes, gated by its own permission.
     */
    public function routesFor(string $sectionKey): Closure
    {
        return function () use ($sectionKey): void {
            foreach ($this->forSection($sectionKey) as $resource) {
                $key = $resource->key();

                Route::prefix("shop/{$key}")
                    ->name("panel.shop.{$key}.")
                    ->middleware('can:'.$resource->permission())
                    ->group(function () use ($key): void {
                        // `defaults` is not one of RouteRegistrar's group
                        // attributes, so the key rides on each route instead —
                        // it is how ResourceController knows which schema it is
                        // serving without parsing the route name.
                        Route::get('/', [ResourceController::class, 'index'])
                            ->defaults('resourceKey', $key)->name('index');
                        Route::get('/create', [ResourceController::class, 'create'])
                            ->defaults('resourceKey', $key)->name('create');
                        Route::post('/', [ResourceController::class, 'store'])
                            ->defaults('resourceKey', $key)->name('store');
                        Route::get('/{record}/edit', [ResourceController::class, 'edit'])
                            ->whereNumber('record')->defaults('resourceKey', $key)->name('edit');
                        Route::put('/{record}', [ResourceController::class, 'update'])
                            ->whereNumber('record')->defaults('resourceKey', $key)->name('update');
                        Route::delete('/{record}', [ResourceController::class, 'destroy'])
                            ->whereNumber('record')->defaults('resourceKey', $key)->name('destroy');

                        // Declared row operations. One route for all of them:
                        // which ones a given row offers is decided per row when
                        // the index is built, not by the route table.
                        Route::post('/{record}/actions/{action}', [ResourceController::class, 'action'])
                            ->whereNumber('record')->defaults('resourceKey', $key)->name('action');
                    });
            }
        };
    }
}
