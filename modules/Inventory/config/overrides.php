<?php

use Lunar\Core\Pipelines\Order\Creation\CleanUpOrderLines;
use Lunar\Core\Pipelines\Order\Creation\CreateOrderAddresses;
use Lunar\Core\Pipelines\Order\Creation\CreateOrderLines;
use Lunar\Core\Pipelines\Order\Creation\CreateShippingLine;
use Lunar\Core\Pipelines\Order\Creation\FillOrderFromCart;
use Lunar\Core\Pipelines\Order\Creation\MapDiscountBreakdown;
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
