<?php

/**
 * Module-local overrides for Lunar's `lunar.cart` config.
 *
 * Kept here (not in config/lunar/cart.php) so `php artisan lunar:install` /
 * `vendor:publish --tag=lunar --force` can never wipe them — CatalogServiceProvider
 * re-applies these over the published Lunar defaults at boot. Only the keys we
 * change are listed.
 *
 * NOTE: `eager_load` is a LIST, so LunarConfigOverride replaces it wholesale —
 * we restate Lunar's defaults minus the one entry we drop. If Lunar changes its
 * default eager-load set, update this list to match.
 */
return [
    // Cart lines are eager-loaded through the `purchasable` morph, which in this
    // project resolves to Modules\Catalog\Models\ProductSku (the flexible variant
    // model), NOT Lunar's ProductVariant.
    //
    // Lunar's default set includes `lines.purchasable.values` — an option-value
    // relation that ProductSku does not define; its option labels are built
    // positionally from the product's `variables` blob instead. Leaving it in
    // makes every cart render throw BadMethodCallException on a missing relation,
    // so the entry is deliberately absent below.
    'eager_load' => [
        'currency',
        'lines.purchasable.taxClass',
        'lines.purchasable.product.thumbnail',
        'lines.purchasable.prices.currency',
        'lines.purchasable.prices.priceable',
        'lines.purchasable.product',
        'lines.cart.currency',
    ],
];
