<?php

namespace Tests\Feature;

use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Modules\Promotion\Database\Seeders\DemoPromotionSeeder;
use Modules\Promotion\DiscountTypes\ComboPercentageOff;
use Modules\Promotion\DiscountTypes\QuantityPercentageOff;
use Modules\Promotion\Services\MembershipService;
use Modules\Promotion\Services\PromotionService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Advanced promotions: flash sale, automatic quantity/combo discounts and
 * spend-based membership tiers. Builds on Lunar's discount engine + customer
 * groups (Principle #1: inherit, don't rebuild).
 */
class PromotionAdvancedTest extends TestCase
{
    use CreatesStorefrontData;

    /** A cart with the default currency + channel. */
    private function makeCart(): Cart
    {
        return Cart::create([
            'currency_id' => Currency::getDefault()->id,
            'channel_id' => Channel::getDefault()->id,
        ]);
    }

    private function enableForAll(Discount $discount): void
    {
        foreach (Channel::all() as $channel) {
            $discount->scheduleChannel($channel, now()->subDay());
        }
        foreach (CustomerGroup::all() as $group) {
            $discount->scheduleCustomerGroup($group, now()->subDay());
        }
    }

    public function test_quantity_discount_applies_only_once_threshold_is_met(): void
    {
        $product = $this->createProduct(['price' => 10000]);
        $variant = $product->variants->first();

        $discount = Discount::create([
            'name' => 'Buy 2, Get 10% Off',
            'handle' => 'buy-2-get-10',
            'type' => QuantityPercentageOff::class,
            'starts_at' => now()->subDay(),
            'uses' => 0,
            'priority' => 5,
            'stop' => false,
            'data' => ['min_qty' => 2, 'percentage' => 10],
        ]);
        $this->enableForAll($discount);

        // One unit → below threshold → no discount.
        $cart = $this->makeCart();
        $cart->add($variant, 1);
        $cart->calculate();
        $this->assertSame(0, $cart->discountTotal?->value ?? 0);

        // Two units → 10% off the 20000 subtotal = 2000.
        $cart = $this->makeCart();
        $cart->add($variant, 2);
        $cart->calculate();
        $this->assertSame(2000, $cart->discountTotal->value);
    }

    public function test_combo_discount_requires_an_item_from_each_group(): void
    {
        $top = $this->createProduct(['price' => 10000, 'name' => 'Shirt']);
        $bottom = $this->createProduct(['price' => 20000, 'name' => 'Pants']);

        $tops = \Lunar\Models\Collection::create([
            'collection_group_id' => \Lunar\Models\CollectionGroup::firstOrCreate(['handle' => 'main'], ['name' => 'Main'])->id,
            'attribute_data' => ['name' => new \Lunar\FieldTypes\Text('Tops')],
        ]);
        $bottoms = \Lunar\Models\Collection::create([
            'collection_group_id' => $tops->collection_group_id,
            'attribute_data' => ['name' => new \Lunar\FieldTypes\Text('Bottoms')],
        ]);
        $tops->products()->attach($top->id);
        $bottoms->products()->attach($bottom->id);

        $discount = Discount::create([
            'name' => 'Shirt + Pants 15% Off',
            'handle' => 'combo',
            'type' => ComboPercentageOff::class,
            'starts_at' => now()->subDay(),
            'uses' => 0,
            'priority' => 6,
            'stop' => false,
            'data' => ['combo_collections' => [$tops->id, $bottoms->id], 'percentage' => 15],
        ]);
        $this->enableForAll($discount);

        // Only a top in the cart → combo not satisfied → no discount.
        $cart = $this->makeCart();
        $cart->add($top->variants->first(), 1);
        $cart->calculate();
        $this->assertSame(0, $cart->discountTotal?->value ?? 0);

        // Top + bottom → 15% off one of each: 1500 + 3000 = 4500.
        $cart = $this->makeCart();
        $cart->add($top->variants->first(), 1);
        $cart->add($bottom->variants->first(), 1);
        $cart->calculate();
        $this->assertSame(4500, $cart->discountTotal->value);
    }

    public function test_describe_summarises_quantity_and_combo_discounts(): void
    {
        $service = app(PromotionService::class);

        $qty = new Discount([
            'type' => QuantityPercentageOff::class,
            'data' => ['min_qty' => 2, 'percentage' => 10],
        ]);
        $this->assertSame('Buy 2, get 10% off', $service->describe($qty));

        $combo = new Discount([
            'type' => ComboPercentageOff::class,
            'data' => ['percentage' => 15],
        ]);
        $this->assertSame('Buy the combo, get 15% off', $service->describe($combo));
    }

    public function test_current_flash_sale_picks_the_time_boxed_flagged_discount(): void
    {
        $this->seed(DemoPromotionSeeder::class);

        $flash = app(PromotionService::class)->currentFlashSale();

        $this->assertNotNull($flash);
        $this->assertSame('flash-sale', $flash->handle);
        $this->assertTrue((bool) $flash->data['flash_sale']);
    }

    public function test_promotions_endpoint_returns_automatic_promos_and_flash_sale(): void
    {
        $this->seed(DemoPromotionSeeder::class);

        $this->getJson('/api/v1/promotions')
            ->assertOk()
            ->assertJsonPath('meta.flash_sale.is_flash_sale', true)
            ->assertJsonStructure(['data' => [['name', 'description', 'ends_at', 'is_flash_sale']]]);
    }

    public function test_demo_seeder_creates_all_promotions_and_tier_groups(): void
    {
        // Give the combo something to assign products to.
        $this->createProduct(['name' => 'A', 'slug' => 'a']);
        $this->createProduct(['name' => 'B', 'slug' => 'b']);
        $this->createProduct(['name' => 'C', 'slug' => 'c']);
        $this->createProduct(['name' => 'D', 'slug' => 'd']);

        $this->seed(DemoPromotionSeeder::class);

        foreach (['flash-sale', 'buy-2-get-10', 'shirt-pants-combo', 'membership-member-silver', 'membership-member-gold'] as $handle) {
            $this->assertTrue(Discount::where('handle', $handle)->exists(), "missing discount {$handle}");
        }

        // Membership discount is scoped strictly to its tier's customer group.
        $gold = CustomerGroup::where('handle', 'member-gold')->first();
        $this->assertNotNull($gold);
        $goldDiscount = Discount::where('handle', 'membership-member-gold')->first();
        $this->assertTrue($goldDiscount->customerGroups->pluck('id')->contains($gold->id));

        // Idempotent: re-running doesn't duplicate.
        $this->seed(DemoPromotionSeeder::class);
        $this->assertSame(1, Discount::where('handle', 'flash-sale')->count());
    }

    public function test_membership_tier_is_resolved_from_lifetime_spend(): void
    {
        $membership = app(MembershipService::class);
        $customer = \Lunar\Models\Customer::create(['first_name' => 'Loyal', 'last_name' => 'Shopper']);

        // No spend → no tier.
        $this->assertNull($membership->syncCustomer($customer));

        // 3,000,000 VND spend (minor units, factor 100 → 300,000,000) → Silver
        // (>= 2,000,000), still below Gold (5,000,000).
        \Lunar\Models\Order::factory()->create([
            'customer_id' => $customer->id,
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'status' => 'payment-received',
            'sub_total' => 300_000_000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => 300_000_000,
        ]);

        $tier = $membership->syncCustomer($customer->fresh());
        $this->assertSame('member-silver', $tier['handle']);

        // The customer is now attached to the Silver customer group.
        $silver = CustomerGroup::where('handle', 'member-silver')->first();
        $this->assertTrue($customer->fresh()->customerGroups->pluck('id')->contains($silver->id));
    }
}
