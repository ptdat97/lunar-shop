<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Lunar\Core\Models\Staff;
use Modules\Content\Models\Menu;
use Tests\TestCase;

/**
 * The navigation editor — the engine's deepest form.
 *
 * A menu is a tree in `menu_items`, and a tree is not a column: it rides in one
 * virtual field the resource converts in both directions. Three levels of
 * nested repeaters, each level a different set of node types.
 */
class PanelMenuTest extends TestCase
{
    private function actingAsAdmin(): static
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');

        return $this;
    }

    private function megaTree(): array
    {
        return [[
            'type' => 'mega',
            'label' => 'Nữ',
            'url' => '/nu',
            'children' => [[
                'type' => 'mega-column',
                'label' => 'Áo',
                'children' => [
                    ['type' => 'link', 'label' => 'Áo khoác', 'url' => '/ao-khoac'],
                    ['type' => 'link', 'label' => 'Áo sơ mi', 'url' => '/ao-so-mi'],
                ],
            ], [
                'type' => 'banner',
                'label' => 'Sale',
                'image' => '/sale.jpg',
                'url' => '/sale',
            ]],
        ]];
    }

    public function test_a_three_level_tree_round_trips(): void
    {
        $menu = Menu::create(['handle' => 'header', 'name' => 'Đầu trang']);

        $this->actingAsAdmin()
            ->put(route('panel.shop.menus.update', $menu->id), [
                'name' => 'Đầu trang',
                'handle' => 'header',
                'tree' => $this->megaTree(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Flattened into menu_items: 1 top + 2 second level + 2 links.
        $this->assertSame(5, $menu->items()->count());

        $top = $menu->rootItems()->first();

        $this->assertSame('mega', $top->type);
        $this->assertSame('Nữ', $top->label);
        $this->assertSame(['Áo', 'Sale'], $top->children->pluck('label')->all());
        $this->assertSame(
            ['Áo khoác', 'Áo sơ mi'],
            $top->children->first()->children->pluck('label')->all(),
        );

        // And back out again, in the same shape the form sent.
        $this->actingAsAdmin()
            ->get(route('panel.shop.menus.edit', $menu->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('record.tree.0.label', 'Nữ')
                ->where('record.tree.0.children.0.label', 'Áo')
                ->where('record.tree.0.children.0.children.1.label', 'Áo sơ mi')
                ->where('record.tree.0.children.1.image', '/sale.jpg'),
            );
    }

    /** Order is the position in the list, at every level. */
    public function test_order_follows_position_at_every_level(): void
    {
        $menu = Menu::create(['handle' => 'header', 'name' => 'Đầu trang']);

        $this->actingAsAdmin()
            ->put(route('panel.shop.menus.update', $menu->id), [
                'name' => 'Đầu trang',
                'handle' => 'header',
                'tree' => $this->megaTree(),
            ])->assertSessionHasNoErrors();

        $column = $menu->rootItems()->first()->children->first();

        $this->assertSame([0, 1], $column->children->pluck('sort')->all());
    }

    /** A label is required at every depth — an unlabelled node renders blank. */
    public function test_nested_rows_are_validated_by_their_path(): void
    {
        $menu = Menu::create(['handle' => 'header', 'name' => 'Đầu trang']);

        $this->actingAsAdmin()
            ->put(route('panel.shop.menus.update', $menu->id), [
                'name' => 'Đầu trang',
                'handle' => 'header',
                'tree' => [[
                    'type' => 'mega',
                    'label' => 'Nữ',
                    'children' => [[
                        'type' => 'mega-column',
                        'label' => 'Áo',
                        'children' => [['type' => 'link', 'label' => '', 'url' => '/x']],
                    ]],
                ]],
            ])
            ->assertSessionHasErrors('tree.0.children.0.children.0.label');
    }

    /**
     * Saving replaces the tree, and menu_items.parent_id cascades onto itself —
     * MySQL refuses a plain delete of a nested menu (error 6575), which is why
     * MenuTree removes leaves first. A second save is what proves it.
     */
    public function test_saving_over_an_existing_nested_menu_works(): void
    {
        $menu = Menu::create(['handle' => 'header', 'name' => 'Đầu trang']);

        foreach ([1, 2] as $_) {
            $this->actingAsAdmin()
                ->put(route('panel.shop.menus.update', $menu->id), [
                    'name' => 'Đầu trang',
                    'handle' => 'header',
                    'tree' => $this->megaTree(),
                ])->assertSessionHasNoErrors();
        }

        $this->assertSame(5, $menu->items()->count());

        // An empty tree clears the menu rather than leaving it as it was.
        $this->actingAsAdmin()
            ->put(route('panel.shop.menus.update', $menu->id), [
                'name' => 'Đầu trang',
                'handle' => 'header',
                'tree' => [],
            ])->assertSessionHasNoErrors();

        $this->assertSame(0, $menu->items()->count());
    }

    /** `tree` is not a column: it must never reach the mass assignment. */
    public function test_creating_a_menu_persists_its_tree(): void
    {
        $this->actingAsAdmin()
            ->post(route('panel.shop.menus.store'), [
                'name' => 'Chân trang',
                'handle' => 'footer',
                'tree' => [[
                    'type' => 'footer-column',
                    'label' => 'Hỗ trợ',
                    'children' => [['type' => 'link', 'label' => 'Liên hệ', 'url' => '/lien-he']],
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $menu = Menu::findByHandle('footer');

        $this->assertNotNull($menu);
        $this->assertSame(2, $menu->items()->count());
        $this->assertSame('Liên hệ', $menu->rootItems()->first()->children->first()->label);
    }

    public function test_duplicate_handle_is_rejected(): void
    {
        Menu::create(['handle' => 'header', 'name' => 'Đầu trang']);

        $this->actingAsAdmin()
            ->post(route('panel.shop.menus.store'), ['name' => 'Khác', 'handle' => 'header'])
            ->assertSessionHasErrors('handle');
    }
}
