<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Assets\Database\Seeders\MediaLibraryDemoSeeder;
use Modules\Catalog\Database\Seeders\BaseDataSeeder;
use Modules\Catalog\Database\Seeders\Demo50ProductsSeeder;
use Modules\Catalog\Database\Seeders\DemoOptionsSeeder;
use Modules\Catalog\Database\Seeders\DemoReviewSeeder;
use Modules\Catalog\Database\Seeders\MultiSizeProductsSeeder;
use Modules\Catalog\Database\Seeders\ProductSkuMatrixSeeder;
use Modules\Catalog\Database\Seeders\SizeIntelligenceDemoSeeder;
use Modules\Content\Database\Seeders\CmsDemoSeeder;
use Modules\Content\Database\Seeders\FooterMenuSeeder;
use Modules\Content\Database\Seeders\HeaderMenuSeeder;
use Modules\Content\Database\Seeders\HomeSectionsSeeder;
use Modules\Customer\Database\Seeders\VnLocationSeeder;
use Modules\Inventory\Database\Seeders\StockHistorySeeder;
use Modules\Promotion\Database\Seeders\DemoCouponSeeder;
use Modules\Promotion\Database\Seeders\DemoPromotionSeeder;
use Modules\Promotion\Database\Seeders\PromotionShowcaseSeeder;

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
            ProductSkuMatrixSeeder::class,    // colour x size SKU matrix + `variables` blob (storefront picker/stock/price)
            DemoReviewSeeder::class,          // ratings + a moderation queue
            StockHistorySeeder::class,        // stock ledger movements (needs the SKUs above)
            DemoCouponSeeder::class,          // SAVE10 coupon
            DemoPromotionSeeder::class,       // flash sale, buy-2, shirt+pants combo, membership tiers
            PromotionShowcaseSeeder::class,   // wires promotions onto real demo products (visible badges)
            HeaderMenuSeeder::class,          // header menu (links to collections)
            FooterMenuSeeder::class,          // footer columns
            HomeSectionsSeeder::class,        // home page sections
            MediaLibraryDemoSeeder::class,    // media library assets (theme images)
            CmsDemoSeeder::class,             // pages, banners, lookbooks, redirects
        ]);
    }
}
