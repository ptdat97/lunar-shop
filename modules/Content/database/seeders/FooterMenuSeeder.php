<?php

namespace Modules\Content\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Collection as LunarCollection;
use Modules\Content\Models\Menu;
use Modules\Content\Models\MenuItem;

/**
 * Seeds the footer menu: column groups (heading + links). Idempotent.
 */
class FooterMenuSeeder extends Seeder
{
    public function run(): void
    {
        $menu = Menu::firstOrCreate(['handle' => 'footer'], ['name' => 'Footer']);
        $menu->items()->delete();

        $collections = LunarCollection::query()->get()->keyBy(fn ($c) => $c->defaultUrl?->slug);

        $columns = [
            'Information' => [
                ['About Us', '/about'],
                ['Our Stories', '/blog'],
                ['Size Guide', '/size-guide'],
                ['Contact Us', '/contact'],
                ['My Account', '/customer'],
            ],
            'Customer Services' => [
                ['Shipping', '/shipping'],
                ['Return & Refund', '/returns'],
                ['Privacy Policy', '/privacy'],
                ['Terms & Conditions', '/terms'],
                ['Orders FAQs', '/faqs'],
            ],
            'Shop' => [
                ['Women', null, 'women'],
                ['Men', null, 'men'],
                ['Sale', null, 'sale'],
                ['New Arrivals', null, 'new-arrivals'],
            ],
        ];

        $colSort = 0;

        foreach ($columns as $heading => $links) {
            $column = MenuItem::create([
                'menu_id' => $menu->id,
                'type' => 'footer-column',
                'label' => $heading,
                'sort' => $colSort++,
            ]);

            foreach ($links as $linkSort => $link) {
                MenuItem::create([
                    'menu_id' => $menu->id,
                    'parent_id' => $column->id,
                    'type' => 'link',
                    'label' => $link[0],
                    'url' => $link[1] ?? null,
                    'collection_id' => isset($link[2]) ? $collections[$link[2]]?->id : null,
                    'sort' => $linkSort,
                ]);
            }
        }
    }
}
