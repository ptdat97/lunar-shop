<?php

use Lunar\Core\Validation\Cart\ValidateCartForOrderCreation;
use Modules\Inventory\Validation\CartStockAtOrderCreation;

/**
 * Module-local overrides for Lunar's `lunar.cart` config.
 *
 * Kept here (not in config/lunar/cart.php) so `vendor:publish --tag=lunar
 * --force` can never wipe them — InventoryServiceProvider re-applies them at
 * boot. Only the key we change is listed.
 *
 * NOTE: `validators` is a LIST, so LunarConfigOverride replaces it wholesale.
 * Lunar's own entry is restated first. If Lunar adds a validator to this hook,
 * add it here too.
 */
return [
    'validators' => [
        'order_create' => [
            ValidateCartForOrderCreation::class,
            // Ours: the stock a line was added with may be gone by checkout.
            CartStockAtOrderCreation::class,
        ],
    ],
];
