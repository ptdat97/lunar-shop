<?php

/**
 * Module-local overrides for Lunar's `lunar.orders` config.
 *
 * Appends our stock-decrement pipeline to Lunar's order-creation pipeline so
 * stock is reserved (decremented) atomically when an order is created — the
 * canonical Lunar extension point, no vendor edits. Kept here (not in
 * config/lunar/orders.php) so `vendor:publish --tag=lunar --force` can't wipe
 * it; InventoryServiceProvider re-applies these at boot.
 *
 * NOTE: the pipeline `creation` array is a LIST, so LunarConfigOverride replaces
 * it wholesale — we restate Lunar's defaults plus our addition. If Lunar changes
 * its default creation pipeline, update this list to match.
 */

use Lunar\Pipelines\Order\Creation\CleanUpOrderLines;
use Lunar\Pipelines\Order\Creation\CreateOrderAddresses;
use Lunar\Pipelines\Order\Creation\CreateOrderLines;
use Lunar\Pipelines\Order\Creation\CreateShippingLine;
use Lunar\Pipelines\Order\Creation\FillOrderFromCart;
use Lunar\Pipelines\Order\Creation\MapDiscountBreakdown;
use Modules\Inventory\Pipelines\DecrementStock;

return [
    'pipelines' => [
        'creation' => [
            FillOrderFromCart::class,
            CreateOrderLines::class,
            CreateOrderAddresses::class,
            CreateShippingLine::class,
            CleanUpOrderLines::class,
            MapDiscountBreakdown::class,
            // Ours: reserve stock once the order + lines exist.
            DecrementStock::class,
        ],
    ],
];
