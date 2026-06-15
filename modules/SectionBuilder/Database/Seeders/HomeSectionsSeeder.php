<?php

namespace Modules\SectionBuilder\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\SectionBuilder\Models\PageSection;
use Modules\SectionBuilder\Support\SectionSchemas;

/**
 * Seeds the default Modave "home" page layout with the template's content as
 * editable settings. Idempotent.
 */
class HomeSectionsSeeder extends Seeder
{
    public function run(): void
    {
        $order = [
            'hero-slider',
            'category-grid',
            'product-tabs',
            'lookbook',
            'testimonial',
            'iconbox',
            'instagram',
        ];

        foreach ($order as $i => $type) {
            PageSection::updateOrCreate(
                ['page_handle' => 'home', 'type' => $type],
                [
                    'sort' => $i,
                    'enabled' => true,
                    'settings' => SectionSchemas::defaults($type),
                ],
            );
        }
    }
}
