<?php

namespace Modules\Promotion\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Core\DiscountTypes\PercentageOff;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\CustomerGroup;
use Lunar\Core\Models\Discount;

/**
 * Seeds a demo coupon: SAVE10 → 10% off the cart. Idempotent.
 */
class DemoCouponSeeder extends Seeder
{
    public function run(): void
    {
        $discount = Discount::updateOrCreate(
            ['coupon' => 'SAVE10'],
            [
                'name' => '10% Off',
                'handle' => 'save10',
                'type' => PercentageOff::class,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
                'uses' => 0,
                'max_uses' => null,
                'priority' => 1,
                'stop' => false,
                'data' => [
                    'percentage' => 10,
                ],
            ],
        );

        // Discounts are scoped to channels + customer groups; enable for all.
        foreach (Channel::all() as $channel) {
            $discount->scheduleChannel($channel, now()->subDay());
        }

        foreach (CustomerGroup::all() as $group) {
            $discount->scheduleCustomerGroup($group, now()->subDay());
        }
    }
}
