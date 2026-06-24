<?php

use Modules\Promotion\DiscountTypes\ComboPercentageOff;
use Modules\Promotion\DiscountTypes\QuantityPercentageOff;

return [

    /*
    |--------------------------------------------------------------------------
    | Custom discount types
    |--------------------------------------------------------------------------
    |
    | Fashion-specific discount types built on top of Lunar's discount engine.
    | Registered with Lunar's DiscountManager so they appear in the Filament
    | admin and are applied by the cart pipeline like the native types.
    |
    */
    'discount_types' => [
        QuantityPercentageOff::class,
        ComboPercentageOff::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Membership tiers
    |--------------------------------------------------------------------------
    |
    | Spend-based loyalty tiers. A customer is placed in the highest tier whose
    | `min_spend` (in MAJOR currency units of the default currency) their
    | lifetime paid spend reaches. Each tier maps to a Lunar CustomerGroup
    | (matched/created by `handle`); membership discounts are then scoped to
    | that group natively via Lunar's `customerGroups` relation.
    |
    | `discount_percentage` is informational (used for seeding + display); the
    | actual saving comes from a Discount scoped to the tier's group.
    |
    | Order matters: list ascending by min_spend.
    |
    */
    'membership' => [
        'enabled' => true,

        // Order statuses that count toward lifetime spend (mirror analytics).
        'paid_statuses' => ['payment-received', 'paid', 'completed', 'dispatched'],

        'tiers' => [
            [
                'handle' => 'member-silver',
                'name' => 'Silver Member',
                'min_spend' => 2_000_000,   // 2,000,000 VND lifetime
                'discount_percentage' => 5,
            ],
            [
                'handle' => 'member-gold',
                'name' => 'Gold Member',
                'min_spend' => 5_000_000,   // 5,000,000 VND lifetime
                'discount_percentage' => 10,
            ],
        ],
    ],

];
