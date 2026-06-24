<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Catalog\Database\Seeders\BaseDataSeeder;
use Modules\Catalog\Database\Seeders\Demo50ProductsSeeder;
use Modules\Catalog\Database\Seeders\DemoOptionsSeeder;
use Modules\Catalog\Database\Seeders\MultiSizeProductsSeeder;
use Modules\CMS\Database\Seeders\CmsDemoSeeder;
use Modules\FileManager\Database\Seeders\MediaLibraryDemoSeeder;
use Modules\Location\Database\Seeders\VnLocationSeeder;
use Modules\Product\Database\Seeders\SizeIntelligenceDemoSeeder;
use Modules\Menu\Database\Seeders\FooterMenuSeeder;
use Modules\Menu\Database\Seeders\HeaderMenuSeeder;
use Modules\Promotion\Database\Seeders\DemoCouponSeeder;
use Modules\Promotion\Database\Seeders\DemoPromotionSeeder;
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
            BaseDataSeeder::class,            // channel/currency/language/tax/type
            VnLocationSeeder::class,          // VN provinces + wards (address dropdowns)
            Demo50ProductsSeeder::class,      // products + options + collections + media
            DemoOptionsSeeder::class,         // ensure size/color assigned to base variants
            MultiSizeProductsSeeder::class,   // products with full S/M/L/XL run + dimensions (recommender demo)
            SizeIntelligenceDemoSeeder::class, // variant dimensions + materials (size chart/recommender)
            DemoCouponSeeder::class,          // SAVE10 coupon
            DemoPromotionSeeder::class,       // flash sale, buy-2, shirt+pants combo, membership tiers
            HeaderMenuSeeder::class,          // header menu (links to collections)
            FooterMenuSeeder::class,          // footer columns
            HomeSectionsSeeder::class,        // home page sections
            MediaLibraryDemoSeeder::class,    // media library assets (theme images)
            CmsDemoSeeder::class,             // pages, banners, lookbooks, redirects
        ]);
    }
}
