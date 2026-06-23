<?php

namespace Modules\Hook\Support;

/**
 * Canonical names of the cross-module hooks fired in this app. Referencing these
 * constants (instead of bare strings) keeps producers and consumers in sync and
 * documents every available extension point in one place.
 *
 * Convention:
 *  - `*.resource` FILTERS receive the serialised array of an API Resource and
 *    return it (possibly modified). Args carry the source model so listeners can
 *    enrich without re-querying. Use to ADD fields — don't remove core ones, the
 *    payloads are stable client contracts.
 *  - other FILTERS transform a domain value (e.g. `product.purchasable`).
 *  - ACTIONS (`<domain>.<event>`) fire on something happening; listeners run for
 *    side effects and return nothing.
 */
final class Hooks
{
    // ─── Filters: API Resource payloads ──────────────────────────────────────

    /**
     * FILTER — a serialised product payload (ProductResource).
     * Value: array. Args: [\Lunar\Models\Product $product].
     */
    public const PRODUCT_RESOURCE = 'product.resource';

    /**
     * FILTER — a serialised cart payload (CartResource).
     * Value: array. Args: [\Lunar\Models\Cart $cart].
     */
    public const CART_RESOURCE = 'cart.resource';

    /**
     * FILTER — a serialised collection payload (CollectionResource).
     * Value: array. Args: [\Lunar\Models\Collection $collection].
     */
    public const COLLECTION_RESOURCE = 'collection.resource';

    /**
     * FILTER — a serialised order payload (OrderResource).
     * Value: array. Args: [\Lunar\Models\Order $order].
     */
    public const ORDER_RESOURCE = 'order.resource';

    /**
     * FILTER — the `{ data, facets, meta }` search/listing payload.
     * Value: array. Args: [\Modules\Search\Data\SearchResult $result].
     */
    public const SEARCH_RESULTS = 'search.results';

    // ─── Filters: domain values ──────────────────────────────────────────────

    /**
     * FILTER — whether a variant may be added to the cart at a quantity.
     * Value: bool. Args: [\Lunar\Models\ProductVariant $variant, int $quantity].
     * Inventory hooks this to enforce the oversell guard at add-to-cart time.
     */
    public const PRODUCT_PURCHASABLE = 'product.purchasable';

    /**
     * FILTER — the storefront navigation/menu items array before rendering.
     * Value: array. Args: [string $location].
     */
    public const MENU_ITEMS = 'menu.items';

    // ─── Actions: domain events ──────────────────────────────────────────────

    /**
     * ACTION — an order has just been created (placed) from a cart.
     * Args: [\Lunar\Models\Order $order]. Fires for every order regardless of
     * payment method; stock is already reserved by DecrementStock at this point.
     */
    public const ORDER_PLACED = 'order.placed';

    /**
     * ACTION — an order's payment was confirmed.
     * Args: [\Lunar\Models\Order $order]. Complements the OrderPaid event for
     * non-mail side effects (analytics, loyalty, fulfilment triggers…).
     */
    public const ORDER_PAID = 'order.paid';

    /**
     * ACTION — an order's status changed.
     * Args: [\Lunar\Models\Order $order, string $previousStatus].
     */
    public const ORDER_STATUS_CHANGED = 'order.status_changed';
}
