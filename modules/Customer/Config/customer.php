<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Personal access tokens (mobile app / headless clients)
    |--------------------------------------------------------------------------
    |
    | `ttl_days` stamps `expires_at` on each token as it is issued, rather than
    | using Sanctum's global `expiration`. That setting compares against a
    | token's `created_at`, so switching it on would retroactively invalidate
    | every token already in the wild; a per-token expiry only binds the tokens
    | minted after it. Clients roll forward with POST /api/v1/auth/token/refresh.
    |
    | `abilities` scopes what a customer token may do. Cookie sessions are
    | unaffected — Sanctum's TransientToken passes every ability check — so this
    | only constrains bearer tokens. Staff/POS tokens get their own set when
    | those clients arrive, and the routes already enforce.
    |
    */

    'tokens' => [
        'ttl_days' => (int) env('API_TOKEN_TTL_DAYS', 60),

        'abilities' => [
            'customer' => ['customer:*'],
        ],
    ],

];
