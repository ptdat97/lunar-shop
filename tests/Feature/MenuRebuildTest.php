<?php

namespace Tests\Feature;

use Modules\Content\Models\Menu;
use Modules\Content\Models\MenuItem;
use Modules\Content\Services\MenuTree;
use Tests\TestCase;

/**
 * Rebuilding a nested menu.
 *
 * `menu_items.parent_id` is a self-referencing FK with ON DELETE CASCADE.
 * MySQL expands the cascade graph per nesting level and refuses the statement
 * past 30 expansions, so deleting a nested menu in one statement fails with
 * error 6575 — which broke both `db:seed` and saving a menu in admin.
 *
 * These tests run against MySQL (see phpunit.xml), so they exercise the real
 * engine behaviour rather than a permissive SQLite fallback.
 */
class MenuRebuildTest extends TestCase
{
    /** Build a menu three levels deep, mirroring the seeded header menu. */
    private function nestedMenu(string $handle = 'test-menu'): Menu
    {
        $menu = Menu::create(['handle' => $handle, 'name' => 'Test']);

        foreach (range(1, 3) as $r) {
            $root = MenuItem::create([
                'menu_id' => $menu->id, 'type' => 'mega', 'label' => "Root {$r}", 'sort' => $r,
            ]);

            foreach (range(1, 3) as $c) {
                $column = MenuItem::create([
                    'menu_id' => $menu->id, 'parent_id' => $root->id,
                    'type' => 'mega-column', 'label' => "Col {$c}", 'sort' => $c,
                ]);

                foreach (range(1, 3) as $l) {
                    MenuItem::create([
                        'menu_id' => $menu->id, 'parent_id' => $column->id,
                        'type' => 'link', 'label' => "Link {$l}", 'url' => '/x', 'sort' => $l,
                    ]);
                }
            }
        }

        return $menu;
    }

    public function test_deleting_a_nested_menu_does_not_trip_the_self_cascade_limit(): void
    {
        $menu = $this->nestedMenu();

        $this->assertSame(39, $menu->items()->count());

        // The bug: this threw SQLSTATE[HY000] 6575 before deleteItems() existed.
        $menu->deleteItems();

        $this->assertSame(0, $menu->items()->count());
    }

    public function test_deleting_one_menu_leaves_other_menus_untouched(): void
    {
        $keep = $this->nestedMenu('keep-me');
        $drop = $this->nestedMenu('drop-me');

        $drop->deleteItems();

        $this->assertSame(0, $drop->items()->count());
        $this->assertSame(39, $keep->items()->count(), 'deleteItems must be scoped to its own menu');
    }

    public function test_menu_tree_save_replaces_a_nested_tree(): void
    {
        $menu = $this->nestedMenu('savable');

        MenuTree::save($menu, [
            [
                'type' => 'mega', 'label' => 'Shop',
                'children' => [
                    [
                        'type' => 'mega-column', 'label' => 'Categories',
                        'children' => [
                            ['type' => 'link', 'label' => 'Women', 'url' => '/women'],
                        ],
                    ],
                ],
            ],
        ]);

        // The old 39-item tree is gone, replaced by the 3 posted nodes.
        $this->assertSame(3, $menu->items()->count());
        $this->assertSame(1, $menu->rootItems()->count());

        $root = $menu->rootItems()->first();
        $this->assertSame('Shop', $root->label);
        $this->assertSame('Categories', $root->children->first()->label);
        $this->assertSame('Women', $root->children->first()->children->first()->label);
    }
}
