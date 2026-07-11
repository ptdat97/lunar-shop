<?php

namespace Modules\Content\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Content\Models\PageSection;
use Modules\Content\Support\SectionSchemas;

/**
 * Seeds the default Modave "home" page layout with the template's content as
 * editable settings. Idempotent.
 */
class HomeSectionsSeeder extends Seeder
{
    public function run(): void
    {
        $order = [
            'promotions-strip',
            'hero-slider',
            'flash-sale',
            'collection-grid',
            'product-tabs',
            'promotion-slider',
            'lookbook',
            'iconbox',
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
