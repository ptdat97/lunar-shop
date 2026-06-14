<?php

namespace Modules\SectionBuilder\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\SectionBuilder\Models\PageSection;

/**
 * Seeds the default Modave "home" page layout. Idempotent.
 */
class HomeSectionsSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            ['type' => 'hero-slider', 'settings' => []],
            ['type' => 'category-grid', 'settings' => ['heading' => 'Shop by categories', 'limit' => 6]],
            ['type' => 'product-tabs', 'settings' => ['limit' => 8]],
            ['type' => 'lookbook', 'settings' => []],
            ['type' => 'testimonial', 'settings' => []],
            ['type' => 'iconbox', 'settings' => []],
            ['type' => 'instagram', 'settings' => []],
        ];

        foreach ($sections as $i => $section) {
            PageSection::updateOrCreate(
                ['page_handle' => 'home', 'type' => $section['type']],
                [
                    'sort' => $i,
                    'enabled' => true,
                    'settings' => $section['settings'],
                ],
            );
        }
    }
}
