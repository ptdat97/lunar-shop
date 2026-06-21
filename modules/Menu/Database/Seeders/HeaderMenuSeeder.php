<?php

namespace Modules\Menu\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Collection as LunarCollection;
use Modules\Menu\Models\Menu;
use Modules\Menu\Models\MenuItem;

/**
 * Seeds a practical fashion header menu wired to real collections.
 * Idempotent: rebuilds the "header" menu from scratch each run.
 */
class HeaderMenuSeeder extends Seeder
{
    public function run(): void
    {
        $menu = Menu::firstOrCreate(['handle' => 'header'], ['name' => 'Header']);

        // Rebuild cleanly so re-seeding doesn't duplicate.
        $menu->items()->delete();

        $collections = LunarCollection::query()->get()->keyBy(
            fn ($c) => $c->defaultUrl?->slug
        );

        // 1) Home — plain link
        $this->item($menu, ['type' => 'link', 'label' => 'Home', 'url' => '/', 'sort' => 0]);

        // 2) Shop — mega menu with columns of collections
        $shop = $this->item($menu, ['type' => 'mega', 'label' => 'Shop', 'sort' => 1]);

        $col1 = $this->item($menu, ['type' => 'mega-column', 'label' => 'Categories', 'parent_id' => $shop->id, 'sort' => 0]);
        foreach (['women' => 'Women', 'men' => 'Men', 'sale' => 'Sale', 'new-arrivals' => 'New Arrivals'] as $slug => $label) {
            $this->item($menu, [
                'type' => 'link',
                'label' => $label,
                'parent_id' => $col1->id,
                'collection_id' => $collections[$slug]?->id,
                'sort' => 0,
            ]);
        }

        $col2 = $this->item($menu, ['type' => 'mega-column', 'label' => 'Quick links', 'parent_id' => $shop->id, 'sort' => 1]);
        $this->item($menu, ['type' => 'link', 'label' => 'All products', 'url' => '/search', 'parent_id' => $col2->id, 'sort' => 0]);
        $this->item($menu, ['type' => 'link', 'label' => 'Search', 'url' => '/search', 'parent_id' => $col2->id, 'sort' => 1]);

        // Optional banner inside the mega menu
        $this->item($menu, [
            'type' => 'banner',
            'label' => 'New season',
            'parent_id' => $shop->id,
            'image' => '/demo/DTT_8954.jpg',
            'url' => '/collections/new-arrivals',
            'sort' => 2,
        ]);

        // 3) Women / Men — direct collection links
        $this->item($menu, ['type' => 'link', 'label' => 'Women', 'collection_id' => $collections['women']?->id, 'sort' => 2]);
        $this->item($menu, ['type' => 'link', 'label' => 'Men', 'collection_id' => $collections['men']?->id, 'sort' => 3]);

        // 4) Sale — link with badge
        $this->item($menu, ['type' => 'link', 'label' => 'Sale', 'collection_id' => $collections['sale']?->id, 'badge' => 'Hot', 'sort' => 4]);

        // 5) Lookbooks — direct link to the lookbook index page
        $this->item($menu, ['type' => 'link', 'label' => 'Lookbooks', 'url' => '/lookbooks', 'sort' => 5]);

        // 6) Blog — dropdown of links
        $blog = $this->item($menu, ['type' => 'dropdown', 'label' => 'Blog', 'sort' => 6]);
        $this->item($menu, ['type' => 'link', 'label' => 'All posts', 'url' => '/blog', 'parent_id' => $blog->id, 'sort' => 0]);
        $this->item($menu, ['type' => 'link', 'label' => 'About us', 'url' => '/about', 'parent_id' => $blog->id, 'sort' => 1]);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    protected function item(Menu $menu, array $attrs): MenuItem
    {
        return MenuItem::create(array_merge(['menu_id' => $menu->id], $attrs));
    }
}
