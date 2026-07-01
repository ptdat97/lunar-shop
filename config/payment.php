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
        // Merchant API (refund/query) — signed JSON POST, HMAC-SHA512.
        'api_url' => env('VNPAY_API_URL', 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MoMo
    |--------------------------------------------------------------------------
    |
    | Sandbox: https://test-payment.momo.vn/v2/gateway/api/create
    | partner_code + access_key + secret_key come from the MoMo Business portal.
    | The create-payment call is a signed JSON POST that returns a `payUrl` to
    | redirect the shopper to; return_url + ipn_url receive the signed result.
    | Signature is HMAC-SHA256 over an ordered `key=value&…` raw string.
    |
    */
    'momo' => [
        'partner_code' => env('MOMO_PARTNER_CODE', ''),
        'access_key' => env('MOMO_ACCESS_KEY', ''),
        'secret_key' => env('MOMO_SECRET_KEY', ''),
        'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),
        'return_url' => env('MOMO_RETURN_URL', env('APP_URL').'/payment/momo/return'),
        'ipn_url' => env('MOMO_IPN_URL', env('APP_URL').'/payment/momo/ipn'),
        // Refund API — signed JSON POST, HMAC-SHA256.
        'refund_url' => env('MOMO_REFUND_URL', 'https://test-payment.momo.vn/v2/gateway/api/refund'),
    ],
];
