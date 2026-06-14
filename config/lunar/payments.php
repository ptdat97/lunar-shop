<?php

return [

    'default' => env('PAYMENTS_TYPE', 'cod'),

    'types' => [
        // Cash on delivery — offline driver, marked paid on fulfilment.
        'cod' => [
            'driver' => 'offline',
            'authorized' => 'payment-offline',
        ],

        // Bank transfer — offline driver; awaits manual confirmation.
        'bank-transfer' => [
            'driver' => 'offline',
            'authorized' => 'awaiting-payment',
        ],

        // Phase 2 gateways (VNPay / MoMo / Stripe / PayPal) register custom
        // drivers via Payments::extend(...) — added later without touching callers.
    ],

];
