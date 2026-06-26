<?php

namespace Modules\Product\Observers;

use Lunar\Models\Product;
use Modules\Platform\Facades\Hook;
use Modules\Platform\Support\Hooks;

/**
 * Bridges Lunar's Product model lifecycle onto the shared hook plane so other
 * modules/plugins react (cache invalidation, feed sync, re-index) without
 * coupling to the model. Covers every write path — admin, import, seeders.
 */
class ProductObserver
{
    public function created(Product $product): void
    {
        Hook::doAction(Hooks::PRODUCT_CREATED, [$product]);
    }

    public function updated(Product $product): void
    {
        Hook::doAction(Hooks::PRODUCT_UPDATED, [$product]);
    }
}
