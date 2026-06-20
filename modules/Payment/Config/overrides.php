<?php

/**
 * Module-local overrides for Lunar's `lunar.payments` config.
 *
 * Kept here (not in config/lunar/payments.php) so `vendor:publish --tag=lunar
 * --force` can't wipe them — PaymentServiceProvider re-applies these at boot.
 * The VNPay driver itself is registered via Payments::extend() in the provider.
 */
return [
    // Cash on delivery is the default checkout method for this storefront.
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

        // VNPay — online gateway (redirect + callback). The custom driver is
        // registered via Payments::extend('vnpay', ...) in PaymentServiceProvider.
        // 'authorized' is the order status set on redirect (before payment);
        // the return/IPN callback moves it to 'payment-received' once paid.
        'vnpay' => [
            'driver' => 'vnpay',
            'authorized' => 'awaiting-payment',
        ],
    ],
];
