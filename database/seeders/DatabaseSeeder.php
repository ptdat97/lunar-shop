<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Catalog\Database\Seeders\BaseDataSeeder;
use Modules\Catalog\Database\Seeders\Demo50ProductsSeeder;
use Modules\Catalog\Database\Seeders\DemoOptionsSeeder;
use Modules\Menu\Database\Seeders\FooterMenuSeeder;
use Modules\Menu\Database\Seeders\HeaderMenuSeeder;
use Modules\Promotion\Database\Seeders\DemoCouponSeeder;
use Modules\SectionBuilder\Database\Seeders\HomeSectionsSeeder;

/**
 * Full setup for a fresh install: Lunar essentials → demo catalog → menus,
 * sections and a demo coupon. Run with `php artisan db:seed`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BaseDataSeeder::class,         // channel/currency/language/tax/type
            Demo50ProductsSeeder::class,   // products + options + collections + media
            DemoOptionsSeeder::class,      // ensure size/color assigned to base variants
            DemoCouponSeeder::class,       // SAVE10 coupon
            HeaderMenuSeeder::class,       // header menu (links to collections)
            FooterMenuSeeder::class,       // footer columns
            HomeSectionsSeeder::class,     // home page sections
        ]);
    }
}
