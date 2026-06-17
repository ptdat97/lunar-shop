<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\ProductType;
use Lunar\Models\TaxClass;

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

        if (! Language::count()) {
            Language::create(['code' => 'en', 'name' => 'English', 'default' => true]);
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

        if (! TaxClass::whereDefault(true)->exists()) {
            TaxClass::create(['name' => 'Default', 'default' => true]);
        }

        if (! ProductType::count()) {
            ProductType::create(['name' => 'General']);
        }
    }
}
