<?php

namespace Modules\Promotion\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\DiscountTypes\AmountOff;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Discountable;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\Url;
use Modules\Promotion\DiscountTypes\ComboPercentageOff;
use Modules\Promotion\DiscountTypes\QuantityPercentageOff;
use Modules\Promotion\Services\MembershipService;

/**
 * Showcase seeder — wires the advanced promotions onto REAL demo products so
 * every feature is visible on the storefront (badge + struck price on cards,
 * flash-sale bar, combo, membership). Idempotent: safe to re-run.
 *
 * Unlike DemoPromotionSeeder (minimal fixtures), this one deliberately scopes
 * each promotion to a distinct set of products so the storefront shows the
 * different label types side by side rather than one cart-wide sale on
 * everything.
 *
 *   - Flash Sale 25% (time-boxed)  → first 6 products  → red "-25%" badge,
 *                                     struck price, promo-bar countdown.
 *   - Buy 2 -10% (quantity)        → next 6 products    → "Buy 2 -10%" badge.
 *   - Shirt + Pants -15% (combo)   → tops / bottoms     → "Combo -15%" badge.
 *   - Membership Silver 5/Gold 10  → scoped to tier customer groups.
 */
class PromotionShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()->orderBy('id')->limit(18)->get();

        if ($products->count() < 4) {
            $this->command?->warn('PromotionShowcaseSeeder: need at least 4 products — run the catalog seeders first.');

            return;
        }

        $flashProducts = $products->slice(0, 6);
        $qtyProducts = $products->slice(6, 6);
        $comboTopProducts = $products->slice(12, 3);
        $comboBottomProducts = $products->slice(15, 3);

        $this->flashSale($flashProducts);
        $this->buyTwoGetTen($qtyProducts);
        $this->shirtPantsCombo($comboTopProducts, $comboBottomProducts);
        $this->membershipDiscounts();

        $this->command?->info('PromotionShowcaseSeeder: flash sale, buy-2, combo and membership promotions seeded.');
    }

    /** 25% off, ends in 2 days, scoped to a handful of products. */
    protected function flashSale($products): void
    {
        $discount = Discount::updateOrCreate(
            ['handle' => 'showcase-flash-sale'],
            [
                'name' => 'Flash Sale — 25% Off',
                'type' => AmountOff::class,
                'coupon' => null,
                'starts_at' => now()->subHour(),
                'ends_at' => now()->addDays(2),
                'uses' => 0,
                'max_uses' => null,
                'priority' => 100,
                'stop' => false,
                'data' => [
                    'percentage' => 25,
                    'fixed_value' => false,
                    'flash_sale' => true,
                ],
            ],
        );

        $this->limitToProducts($discount, $products);
        $this->enableForAll($discount);
    }

    /** Buy 2 or more of these, get 10% off — automatic, no coupon. */
    protected function buyTwoGetTen($products): void
    {
        $discount = Discount::updateOrCreate(
            ['handle' => 'showcase-buy-2-get-10'],
            [
                'name' => 'Buy 2, Get 10% Off',
                'type' => QuantityPercentageOff::class,
                'coupon' => null,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'uses' => 0,
                'max_uses' => null,
                'priority' => 50,
                'stop' => false,
                'data' => ['min_qty' => 2, 'percentage' => 10],
            ],
        );

        // Conditions (eligible lines) for QuantityPercentageOff are read from
        // discountableConditions, so attach as `condition`.
        $this->attachDiscountables($discount, $products, 'condition');
        $this->enableForAll($discount);
    }

    /** Buy a top + a bottom together → 15% off both. */
    protected function shirtPantsCombo($tops, $bottoms): void
    {
        $topsCollection = $this->ensureCollection('tops', 'Tops');
        $bottomsCollection = $this->ensureCollection('bottoms', 'Bottoms');

        $topsCollection->products()->syncWithoutDetaching($tops->pluck('id')->all());
        $bottomsCollection->products()->syncWithoutDetaching($bottoms->pluck('id')->all());

        $discount = Discount::updateOrCreate(
            ['handle' => 'showcase-shirt-pants-combo'],
            [
                'name' => 'Shirt + Pants — 15% Off',
                'type' => ComboPercentageOff::class,
                'coupon' => null,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'uses' => 0,
                'max_uses' => null,
                'priority' => 60,
                'stop' => false,
                'data' => [
                    'combo_collections' => [$topsCollection->id, $bottomsCollection->id],
                    'percentage' => 15,
                ],
            ],
        );

        // Limit the per-product badge to combo products so "Combo -15%" only
        // shows on the tops/bottoms (cart applies the real break).
        $this->limitToProducts($discount, $tops->merge($bottoms));
        $this->enableForAll($discount);
    }

    /** Silver 5% / Gold 10%, scoped to each tier's CustomerGroup. */
    protected function membershipDiscounts(): void
    {
        $membership = app(MembershipService::class);

        foreach ($membership->tiers() as $tier) {
            $group = $membership->groupForTier($tier);

            $discount = Discount::updateOrCreate(
                ['handle' => 'showcase-membership-' . $tier['handle']],
                [
                    'name' => $tier['name'] . ' — ' . $tier['discount_percentage'] . '% Off',
                    'type' => AmountOff::class,
                    'coupon' => null,
                    'starts_at' => now()->subDay(),
                    'ends_at' => null,
                    'uses' => 0,
                    'max_uses' => null,
                    'priority' => 10,
                    'stop' => false,
                    'data' => [
                        'percentage' => $tier['discount_percentage'],
                        'fixed_value' => false,
                        'membership' => true,
                    ],
                ],
            );

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

    /**
     * Scope a discount's eligible lines to the given products (limitation), so
     * both the cart engine and the per-product badge target only them.
     */
    protected function limitToProducts(Discount $discount, $products): void
    {
        $this->attachDiscountables($discount, $products, 'limitation');
    }

    /**
     * Replace a discount's discountables of a given type with the products.
     */
    protected function attachDiscountables(Discount $discount, $products, string $type): void
    {
        Discountable::where('discount_id', $discount->id)
            ->where('type', $type)
            ->where('discountable_type', Product::morphName())
            ->delete();

        foreach ($products as $product) {
            Discountable::firstOrCreate([
                'discount_id' => $discount->id,
                'discountable_type' => Product::morphName(),
                'discountable_id' => $product->id,
                'type' => $type,
            ]);
        }
    }

    protected function enableForAll(Discount $discount): void
    {
        foreach (Channel::all() as $channel) {
            $discount->scheduleChannel($channel, now()->subDay());
        }

        foreach (CustomerGroup::all() as $group) {
            $discount->scheduleCustomerGroup($group, now()->subDay());
        }
    }

    protected function ensureCollection(string $slug, string $name): LunarCollection
    {
        $collection = LunarCollection::whereHas('urls', fn ($q) => $q->where('slug', $slug))->first();

        if ($collection) {
            return $collection;
        }

        $group = CollectionGroup::first() ?? CollectionGroup::create(['name' => 'Main', 'handle' => 'main']);

        $collection = LunarCollection::create([
            'collection_group_id' => $group->id,
            'attribute_data' => ['name' => new Text($name)],
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
}
