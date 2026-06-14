<?php

return [
    // Flat standard rate in minor units (e.g. cents). Phase 1 simple model.
    'standard_rate' => env('SHIPPING_STANDARD_RATE', 3000),

    // Free standard shipping when cart sub-total >= this (minor units). 0 disables.
    'free_threshold' => env('SHIPPING_FREE_THRESHOLD', 0),
];
