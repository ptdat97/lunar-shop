<?php

namespace Modules\Promotion\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Core\DiscountTypes\PercentageOff;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Collection;
use Lunar\Core\Models\CollectionGroup;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Discount;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\Url;
use Modules\Promotion\DiscountTypes\ComboPercentageOff;
use Modules\Promotion\DiscountTypes\QuantityPercentageOff;
use Modules\Promotion\Services\MembershipService;

/**
 * Seeds demo automatic promotions + membership tiers. Idempotent — safe to
 * re-run. Builds on Lunar's Discount engine + CustomerGroups (Principle #1).
 *
 *  - Flash Sale            : 20% off, time-boxed (ends in 3 days)
 *  - Buy 2 get 10% off     : QuantityPercentageOff (automatic)
 *  - Shirt + Pants 15% off : ComboPercentageOff across tops/bottoms collections
 *  - Membership discounts  : Silver 5% / Gold 10%, scoped to tier CustomerGroups
 */
class DemoPromotionSeeder extends Seeder
{
    public function run(): void
    {
        $this->flashSale();
        $this->buyTwoGetTenPercent();
        $this->shirtPantsCombo();
        $this->membershipDiscounts();
    }

    /** Flash sale: 20% off the whole cart, ends in 3 days. */
    protected function flashSale(): void
    {
        $discount = Discount::updateOrCreate(
            ['handle' => 'flash-sale'],
            [
                'name' => 'Flash Sale — 20% Off',
                'type' => PercentageOff::class,
                'coupon' => null,
                'starts_at' => now()->subHour(),
                'ends_at' => now()->addDays(3),
                'uses' => 0,
                'max_uses' => null,
                'priority' => 10,
                'stop' => false,
                'data' => [
                    'percentage' => 20,
                    'flash_sale' => true,
                ],
            ],
        );

        $this->enableForAll($discount);
    }

    /** Buy 2 or more (anything), get 10% off those lines — automatic. */
    protected function buyTwoGetTenPercent(): void
    {
        $discount = Discount::updateOrCreate(
            ['handle' => 'buy-2-get-10'],
            [
                'name' => 'Buy 2, Get 10% Off',
                'type' => QuantityPercentageOff::class,
                'coupon' => null,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'uses' => 0,
                'max_uses' => null,
                'priority' => 5,
                'stop' => false,
                'data' => [
                    'min_qty' => 2,
                    'percentage' => 10,
                ],
            ],
        );

        $this->enableForAll($discount);
    }

    /** Buy a top + a bottom together, get 15% off both — automatic combo. */
    protected function shirtPantsCombo(): void
    {
        $tops = $this->ensureCollection('tops', 'Tops');
        $bottoms = $this->ensureCollection('bottoms', 'Bottoms');

        $this->assignDemoProducts($tops, $bottoms);

        $discount = Discount::updateOrCreate(
            ['handle' => 'shirt-pants-combo'],
            [
                'name' => 'Shirt + Pants — 15% Off',
                'type' => ComboPercentageOff::class,
                'coupon' => null,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'uses' => 0,
                'max_uses' => null,
                'priority' => 6,
                'stop' => false,
                'data' => [
                    'combo_collections' => [$tops->id, $bottoms->id],
                    'percentage' => 15,
                ],
            ],
        );

        $this->enableForAll($discount);
    }

    /** Silver/Gold membership: % off scoped to each tier's CustomerGroup. */
    protected function membershipDiscounts(): void
    {
        $membership = app(MembershipService::class);

        foreach ($membership->tiers() as $tier) {
            $group = $membership->groupForTier($tier);

            $discount = Discount::updateOrCreate(
                ['handle' => 'membership-'.$tier['handle']],
                [
                    'name' => $tier['name'].' — '.$tier['discount_percentage'].'% Off',
                    'type' => PercentageOff::class,
                    'coupon' => null,
                    'starts_at' => now()->subDay(),
                    'ends_at' => null,
                    'uses' => 0,
                    'max_uses' => null,
                    'priority' => 1,
                    'stop' => false,
                    'data' => [
                        'percentage' => $tier['discount_percentage'],
                        'membership' => true,
                    ],
                ],
            );

            // Scope strictly to this tier's customer group — Lunar's
            // DiscountManager only surfaces it to carts whose customer is in it.
            $discount->customerGroups()->sync([
                $group->id => [
                    'enabled' => true,
                    'visible' => true,
                    'starts_at' => now()->subDay(),
                    'ends_at' => null,
                ],
            ]);

            foreach (Channel::all() as $channel) {
                $discount->scheduleChannel($channel, now()->subDay());
            }
        }
    }

    /** Enable a discount for every channel + customer group. */
    protected function enableForAll(Discount $discount): void
    {
        foreach (Channel::all() as $channel) {
            $discount->scheduleChannel($channel, now()->subDay());
        }

        foreach (CustomerGroup::all() as $group) {
            $discount->scheduleCustomerGroup($group, now()->subDay());
        }
    }

    /** Find or create a collection by url slug. */
    protected function ensureCollection(string $slug, string $name): Collection
    {
        $collection = Collection::whereHas('urls', fn ($q) => $q->where('slug', $slug))->first();

        if ($collection) {
            return $collection;
        }

        $group = CollectionGroup::first() ?? CollectionGroup::create(['name' => 'Main', 'handle' => 'main']);

        $collection = Collection::create([
            'collection_group_id' => $group->id,
            'name' => ['en' => $name],
        ]);

        Url::create([
            'slug' => $slug,
            'element_type' => $collection->getMorphClass(),
            'element_id' => $collection->id,
            'language_id' => Language::getDefault()?->id,
            'default' => true,
        ]);

        return $collection;
    }

    /**
     * Assign a couple of demo products to the tops/bottoms collections so the
     * combo has something to fire on. Best-effort: skips if no products exist.
     */
    protected function assignDemoProducts(Collection $tops, Collection $bottoms): void
    {
        $products = Product::query()->limit(4)->get();

        if ($products->count() < 2) {
            return;
        }

        // First half → tops, second half → bottoms (only if not yet assigned).
        $tops->products()->syncWithoutDetaching($products->take(2)->pluck('id')->all());
        $bottoms->products()->syncWithoutDetaching($products->slice(2, 2)->pluck('id')->all());
    }
}
