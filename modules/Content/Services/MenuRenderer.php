<?php

namespace Modules\Content\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Lunar\Models\Collection as LunarCollection;
use Modules\Content\Models\Menu;
use Modules\Content\Models\MenuItem;

/**
 * Renders a menu (by handle) into HTML. Each top-level item is rendered by the
 * theme partial matching its type (theme::menus.{type}); the partial receives
 * the item and its eager-loaded children, so it can render nested megamenus.
 */
class MenuRenderer
{
    /**
     * Per-request cache of loaded root-item trees, keyed by handle. The header
     * renders both desktop (render) and mobile (renderMobile) from the same
     * handle, so without this the whole menu_items + collection + url tree would
     * be queried twice. MenuRenderer is a request-scoped singleton, so this memo
     * lives exactly as long as it should.
     *
     * @var array<string, Collection<int, MenuItem>>
     */
    protected array $rootsCache = [];

    /**
     * Linked collections (with defaultUrl) shared across ALL menus in the
     * request, keyed by collection id (missing ids map to null). Header and
     * footer typically link the same few collections; per-menu eager loading
     * fetched them once per menu — this map fetches each id once per request.
     *
     * @var array<int, LunarCollection|null>
     */
    protected array $collectionsCache = [];

    /**
     * Load a menu's root items with the whole tree eager-loaded (2 levels deep
     * covers mega → column → links), memoised per handle for the request.
     * Linked collections are attached from the shared per-request map instead
     * of per-menu eager loads.
     *
     * @return Collection<int, MenuItem>
     */
    protected function loadRoots(string $handle): Collection
    {
        if (isset($this->rootsCache[$handle])) {
            return $this->rootsCache[$handle];
        }

        $menu = Menu::findByHandle($handle);

        if (! $menu) {
            return $this->rootsCache[$handle] = collect();
        }

        $roots = $menu->rootItems()
            ->with('children.children')
            ->get();

        $this->attachCollections($this->flatten($roots));

        return $this->rootsCache[$handle] = $roots;
    }

    /**
     * Every item in the tree (roots + loaded descendants), flattened.
     *
     * @param  Collection<int, MenuItem>  $items
     * @return Collection<int, MenuItem>
     */
    protected function flatten(Collection $items): Collection
    {
        return $items->flatMap(fn (MenuItem $item) => collect([$item])->merge(
            $item->relationLoaded('children') ? $this->flatten($item->children) : collect(),
        ));
    }

    /**
     * Set each item's `collection` relation from the shared map, loading any
     * ids not seen yet in ONE query (with defaultUrl). Items without a linked
     * collection get null so partials never trigger a lazy load.
     *
     * @param  Collection<int, MenuItem>  $items
     */
    protected function attachCollections(Collection $items): void
    {
        $ids = $items->pluck('collection_id')->filter()->unique()->values();

        $missing = $ids->reject(fn ($id) => array_key_exists((int) $id, $this->collectionsCache));

        if ($missing->isNotEmpty()) {
            $loaded = LunarCollection::query()
                ->with('defaultUrl')
                ->findMany($missing->all())
                ->keyBy('id');

            foreach ($missing as $id) {
                $this->collectionsCache[(int) $id] = $loaded->get((int) $id);
            }
        }

        foreach ($items as $item) {
            $item->setRelation(
                'collection',
                $item->collection_id ? $this->collectionsCache[(int) $item->collection_id] ?? null : null,
            );
        }
    }

    /**
     * Render every root item of a menu, in order.
     */
    public function render(string $handle): HtmlString
    {
        $html = $this->loadRoots($handle)
            ->map(fn (MenuItem $item) => $this->renderItem($item))
            ->implode("\n");

        return new HtmlString($html);
    }

    protected function renderItem(MenuItem $item): string
    {
        $view = "theme::menus.{$item->type}";

        if (! View::exists($view)) {
            $view = 'theme::menus.link';
        }

        return View::make($view, ['item' => $item])->render();
    }

    /**
     * Render a menu for the mobile off-canvas (Bootstrap collapse accordion).
     * Reuses the same menu data; every root item uses the mobile-item partial,
     * which flattens mega/dropdown into nested accordions.
     */
    public function renderMobile(string $handle): HtmlString
    {
        $html = $this->loadRoots($handle)->map(function (MenuItem $item, int $i) use ($handle) {
            return View::make('theme::menus.mobile-item', [
                'item' => $item,
                'uid' => $handle.'-'.$i,
            ])->render();
        })->implode("\n");

        return new HtmlString($html);
    }
}
