<?php

namespace Tests\Feature;

use Lunar\Core\Contracts\Actions\Products\AdjustsStock;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Location;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\Region;
use Lunar\Core\Models\TaxClass;
use Lunar\Core\Models\TaxZone;
use Modules\Catalog\Database\Seeders\BaseDataSeeder;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The store's baseline records — the ones Lunar needs before anything else works.
 *
 * This exists because `migrate:fresh --seed` was broken for the whole of the
 * Lunar 2.0 upgrade and nothing said so. The suite builds its data with
 * {@see CreatesStorefrontData}, never through the demo seeders,
 * so a seeder that could not run at all stayed green — and a fresh dev
 * environment could not be stood up.
 *
 * The specific trap: 2.0 introduced `Location` and `Region`, and the upgrade
 * path backfills both. An UPGRADED database therefore has them and a NEW one
 * does not — so testing only the upgrade hides it completely. That asymmetry is
 * the whole reason this file is here, and the last case below is the one that
 * would have caught it: without a default Location every stock write throws,
 * because `RecordStockMovement` takes a non-nullable `Location` and
 * `Location::getDefault()` returns null.
 */
class BaseDataSeederTest extends TestCase
{
    private function seedBase(): void
    {
        $this->artisan('db:seed', [
            '--class' => BaseDataSeeder::class,
            '--no-interaction' => true,
        ])->assertSuccessful();
    }

    public function test_it_seeds_every_default_lunar_resolves_globally(): void
    {
        $this->seedBase();

        $this->assertNotNull(Channel::getDefault(), 'no default channel');
        $this->assertNotNull(Currency::getDefault(), 'no default currency');
        $this->assertNotNull(Language::getDefault(), 'no default language');
        $this->assertNotNull(TaxClass::getDefault(), 'no default tax class');
        $this->assertNotNull(TaxZone::whereDefault(true)->first(), 'no default tax zone');
        $this->assertNotNull(Location::getDefault(), 'no default location — every stock write will throw');
        $this->assertNotNull(Region::query()->where('default', true)->first(), 'no default region');
    }

    /** Running it twice must not duplicate anything — seeders are re-run often. */
    public function test_it_is_idempotent(): void
    {
        $this->seedBase();
        $this->seedBase();

        $this->assertSame(1, Location::count());
        $this->assertSame(1, Region::count());
        $this->assertSame(1, Channel::whereDefault(true)->count());
        $this->assertSame(1, TaxZone::whereDefault(true)->count());
    }

    /**
     * The failure the missing Location actually produced, reproduced end to end:
     * a stock movement against a variant. This is what the demo seeders do, and
     * what broke `migrate:fresh --seed`.
     */
    public function test_stock_can_be_adjusted_after_seeding(): void
    {
        $this->seedBase();

        $variant = ProductVariant::factory()->create();

        app(AdjustsStock::class)->execute($variant, 7, 'test');

        $this->assertSame(7, (int) $variant->refresh()->stock_on_hand);
    }
}
