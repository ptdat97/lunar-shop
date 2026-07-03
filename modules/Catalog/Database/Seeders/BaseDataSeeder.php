<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\ProductType;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;

/**
 * Lunar essentials for a fresh install (channel, language, currency, customer
 * group, tax class, product type). Mirrors `lunar:install` so the project boots
 * without running the interactive installer. Idempotent.
 */
class BaseDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! Channel::whereDefault(true)->exists()) {
            Channel::create([
                'name' => 'Webstore',
                'handle' => 'webstore',
                'default' => true,
                'url' => env('APP_URL', 'http://localhost'),
            ]);
        }

        // Seed both storefront locales (config/theme.php) so Lunar can resolve
        // translated attribute_data per language. `en` stays the Lunar default;
        // `vi` enables Vietnamese product content. Idempotent per code.
        foreach ([
            ['code' => 'en', 'name' => 'English', 'default' => true],
            ['code' => 'vi', 'name' => 'Tiếng Việt', 'default' => false],
        ] as $lang) {
            if (! Language::where('code', $lang['code'])->exists()) {
                Language::create($lang);
            }
        }

        // Essential countries (checkout needs these; the full list normally
        // comes from `lunar:import:address-data` which requires network access).
        if (! Country::count()) {
            foreach ([
                ['Vietnam', 'VNM', 'VN', '84', 'VND', '🇻🇳', 'U+1F1FB U+1F1F3'],
                ['United States', 'USA', 'US', '1', 'USD', '🇺🇸', 'U+1F1FA U+1F1F8'],
                ['United Kingdom', 'GBR', 'GB', '44', 'GBP', '🇬🇧', 'U+1F1EC U+1F1E7'],
                ['Australia', 'AUS', 'AU', '61', 'AUD', '🇦🇺', 'U+1F1E6 U+1F1FA'],
                ['Canada', 'CAN', 'CA', '1', 'CAD', '🇨🇦', 'U+1F1E8 U+1F1E6'],
                ['Singapore', 'SGP', 'SG', '65', 'SGD', '🇸🇬', 'U+1F1F8 U+1F1EC'],
                ['Japan', 'JPN', 'JP', '81', 'JPY', '🇯🇵', 'U+1F1EF U+1F1F5'],
                ['Germany', 'DEU', 'DE', '49', 'EUR', '🇩🇪', 'U+1F1E9 U+1F1EA'],
                ['France', 'FRA', 'FR', '33', 'EUR', '🇫🇷', 'U+1F1EB U+1F1F7'],
            ] as [$name, $iso3, $iso2, $phone, $currency, $emoji, $emojiU]) {
                Country::create([
                    'name' => $name, 'iso3' => $iso3, 'iso2' => $iso2,
                    'phonecode' => $phone, 'currency' => $currency,
                    'emoji' => $emoji, 'emoji_u' => $emojiU,
                ]);
            }
        }

        if (! Currency::whereDefault(true)->exists()) {
            Currency::create([
                'code' => 'USD',
                'name' => 'US Dollar',
                'exchange_rate' => 1,
                'decimal_places' => 2,
                'default' => true,
                'enabled' => true,
            ]);
        }

        if (! CustomerGroup::whereDefault(true)->exists()) {
            CustomerGroup::create(['name' => 'Retail', 'handle' => 'retail', 'default' => true]);
        }

        $taxClass = TaxClass::whereDefault(true)->first()
            ?? TaxClass::create(['name' => 'Default', 'default' => true]);

        // A default tax zone is required for cart/checkout tax calculation.
        // Seed a 0% zone so the store works out of the box (configure rates later).
        if (! TaxZone::whereDefault(true)->exists()) {
            $zone = TaxZone::create([
                'name' => 'Default',
                'zone_type' => 'country',
                'price_display' => 'tax_exclusive',
                'active' => true,
                'default' => true,
            ]);

            // Cover all countries so any shipping address matches the zone.
            $zone->countries()->createMany(
                Country::query()->pluck('id')->map(fn ($id) => ['country_id' => $id])->all()
            );

            $rate = TaxRate::create([
                'tax_zone_id' => $zone->id,
                'priority' => 1,
                'name' => 'Standard',
            ]);

            TaxRateAmount::create([
                'tax_class_id' => $taxClass->id,
                'tax_rate_id' => $rate->id,
                'percentage' => 0,
            ]);
        }

        if (! ProductType::count()) {
            ProductType::create(['name' => 'General']);
        }
    }
}
