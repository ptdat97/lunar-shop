<?php

namespace Modules\Menu\Services;

use Modules\Menu\Models\Menu;
use Modules\Menu\Models\MenuItem;

/**
 * Converts between the persisted menu_items tree and the nested array the
 * Filament Repeater works with.
 */
class MenuTree
{
    /**
     * Build a nested array (for the form) from a menu's items.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toArray(Menu $menu): array
    {
        $items = $menu->items()->orderBy('sort')->get();
        $byParent = $items->groupBy('parent_id');

        $build = function ($parentId) use (&$build, $byParent) {
            return ($byParent[$parentId] ?? collect())->map(fn (MenuItem $item) => [
                'type' => $item->type,
                'label' => $item->label,
                'url' => $item->url,
                'collection_id' => $item->collection_id,
                'badge' => $item->badge,
                'image' => $item->image,
                'target' => $item->target,
                'children' => $build($item->id),
            ])->values()->all();
        };

        return $build(null);
    }

    /**
     * Persist a nested array back into menu_items, replacing the existing tree.
     *
     * @param  array<int, array<string, mixed>>  $tree
     */
    public static function save(Menu $menu, array $tree): void
    {
        $menu->items()->delete();

        $persist = function (array $nodes, ?int $parentId) use (&$persist, $menu) {
            foreach (array_values($nodes) as $sort => $node) {
                $item = MenuItem::create([
                    'menu_id' => $menu->id,
                    'parent_id' => $parentId,
                    'type' => $node['type'] ?? 'link',
                    'label' => $node['label'] ?? null,
                    'url' => $node['url'] ?? null,
                    'collection_id' => $node['collection_id'] ?? null,
                    'badge' => $node['badge'] ?? null,
                    'image' => $node['image'] ?? null,
                    'target' => $node['target'] ?? '_self',
                    'sort' => $sort,
                ]);

                if (! empty($node['children']) && is_array($node['children'])) {
                    $persist($node['children'], $item->id);
                }
            }
        };

        $persist($tree, null);
    }
}
