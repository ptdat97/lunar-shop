<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VNPay
    |--------------------------------------------------------------------------
    |
    | Sandbox: https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
    | tmn_code + hash_secret come from the VNPay merchant portal. return_url is
    | where VNPay redirects the shopper back to after payment.
    |
    */
    'vnpay' => [
        'tmn_code' => env('VNPAY_TMN_CODE', ''),
        'hash_secret' => env('VNPAY_HASH_SECRET', ''),
        'payment_url' => env('VNPAY_PAYMENT_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'return_url' => env('VNPAY_RETURN_URL', env('APP_URL').'/payment/vnpay/return'),
    ],
];
