<?php

namespace Modules\Content\Services;

use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
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
     * Render every root item of a menu, in order.
     */
    public function render(string $handle): HtmlString
    {
        $menu = Menu::findByHandle($handle);

        if (! $menu) {
            return new HtmlString('');
        }

        // Eager-load the whole tree (2 levels deep covers mega → column → links).
        $roots = $menu->rootItems()
            ->with([
                'collection.defaultUrl',
                'children.collection.defaultUrl',
                'children.children.collection.defaultUrl',
            ])
            ->get();

        $html = $roots->map(fn (MenuItem $item) => $this->renderItem($item))->implode("\n");

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
        $menu = Menu::findByHandle($handle);

        if (! $menu) {
            return new HtmlString('');
        }

        $roots = $menu->rootItems()
            ->with([
                'collection.defaultUrl',
                'children.collection.defaultUrl',
                'children.children.collection.defaultUrl',
            ])
            ->get();

        $html = $roots->map(function (MenuItem $item, int $i) use ($handle) {
            return View::make('theme::menus.mobile-item', [
                'item' => $item,
                'uid' => $handle . '-' . $i,
            ])->render();
        })->implode("\n");

        return new HtmlString($html);
    }
}
