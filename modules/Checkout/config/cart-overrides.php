<?php

/**
 * Module-local overrides for Lunar's `lunar.cart_session` config.
 *
 * Kept here (not in config/lunar/cart_session.php) so `vendor:publish
 * --tag=lunar --force` can't reset them — CheckoutServiceProvider re-applies at boot.
 */
return [
    // Auto-create a cart for the session so the storefront's add-to-cart flow
    // never 404s on a missing cart (Lunar's default is false).
    'auto_create' => true,
];
