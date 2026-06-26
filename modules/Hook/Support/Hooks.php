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

    /**
     * FILTER — the payment method identifiers offered at checkout.
     * Value: list<string> (e.g. ['cod','bank-transfer','vnpay']). Args: [].
     * The single source of allowed methods: checkout validation reads this, so a
     * plugin can add a gateway by appending its identifier — no core edit.
     */
    public const CHECKOUT_PAYMENT_METHODS = 'checkout.payment_methods';

    /**
     * FILTER — the related/"you may also like" products for a product.
     * Value: \Illuminate\Support\Collection<\Lunar\Models\Product>.
     * Args: [\Lunar\Models\Product $product, int $limit]. Lets a plugin re-rank
     * or swap the related set (e.g. a personalised recommender) without the
     * caller knowing. Keep the result a Collection of Products.
     */
    public const PRODUCT_RELATED = 'product.related';

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

    /**
     * ACTION — an order was dispatched/shipped to the customer.
     * Args: [\Lunar\Models\Order $order, string $previousStatus]. A semantic
     * shortcut over ORDER_STATUS_CHANGED for the `dispatched` transition, so
     * fulfilment/tracking listeners don't have to string-match the status.
     */
    public const ORDER_SHIPPED = 'order.shipped';

    /**
     * ACTION — a new customer account was just registered.
     * Args: [\App\Models\User $user]. Fires for every registration path (web
     * SPA + API token), bridged from Laravel's own Registered event. Use for
     * welcome flows, CRM sync, loyalty enrolment.
     */
    public const CUSTOMER_REGISTERED = 'customer.registered';

    /**
     * ACTION — a customer signed in.
     * Args: [\App\Models\User $user]. Bridged from Laravel's Login event, so it
     * fires for cookie/SPA login (and the auto-login right after registration).
     */
    public const CUSTOMER_LOGGED_IN = 'customer.logged_in';

    /**
     * ACTION — a variant line was added to the cart.
     * Args: [\Lunar\Models\Cart $cart, \Lunar\Models\ProductVariant $variant, int $quantity].
     * Fires after the line is created (and passed the purchasable guard).
     */
    public const CART_LINE_ADDED = 'cart.line_added';

    /**
     * ACTION — a cart line's quantity was updated.
     * Args: [\Lunar\Models\Cart $cart, int $lineId, int $quantity].
     */
    public const CART_LINE_UPDATED = 'cart.line_updated';

    /**
     * ACTION — a cart line was removed.
     * Args: [\Lunar\Models\Cart $cart, int $lineId].
     */
    public const CART_LINE_REMOVED = 'cart.line_removed';

    /**
     * ACTION — a coupon was successfully applied to the cart (it produced a
     * non-zero discount). Args: [\Lunar\Models\Cart $cart, string $code].
     */
    public const CART_COUPON_APPLIED = 'cart.coupon_applied';

    /**
     * ACTION — the cart was forgotten from the session (e.g. after an order is
     * placed, or an explicit empty). Args: []. Fired before the session entry
     * is dropped is not guaranteed; treat as "current cart is gone".
     */
    public const CART_EMPTIED = 'cart.emptied';

    /**
     * ACTION — shipping + billing addresses were set on the checkout cart.
     * Args: [\Lunar\Models\Cart $cart].
     */
    public const CHECKOUT_ADDRESS_SET = 'checkout.address_set';

    /**
     * ACTION — a shipping option was chosen at checkout.
     * Args: [\Lunar\Models\Cart $cart, string $identifier].
     */
    public const CHECKOUT_SHIPPING_SELECTED = 'checkout.shipping_selected';

    /**
     * ACTION — a product detail page was viewed.
     * Args: [\Lunar\Models\Product $product]. Fires from the product detail
     * controllers (storefront + API show) — not from every findBySlug. Powers
     * also-viewed recommendations (Recommend P3) and analytics.
     */
    public const PRODUCT_VIEWED = 'product.viewed';

    /**
     * ACTION — a product was created. Args: [\Lunar\Models\Product $product].
     * Bridged from the product model observer (covers admin + import + seeders).
     */
    public const PRODUCT_CREATED = 'product.created';

    /**
     * ACTION — a product was updated. Args: [\Lunar\Models\Product $product].
     * Bridged from the product model observer. Use to invalidate caches / sync
     * feeds / re-index search.
     */
    public const PRODUCT_UPDATED = 'product.updated';

    /**
     * ACTION — a variant's stock crossed below the low-stock threshold (and is
     * still > 0). Args: [\Lunar\Models\ProductVariant $variant, int $stock].
     */
    public const INVENTORY_LOW_STOCK = 'inventory.low_stock';

    /**
     * ACTION — a variant just went out of stock (>0 → ≤0).
     * Args: [\Lunar\Models\ProductVariant $variant].
     */
    public const INVENTORY_OUT_OF_STOCK = 'inventory.out_of_stock';

    /**
     * ACTION — a variant was restocked (≤0 → >0).
     * Args: [\Lunar\Models\ProductVariant $variant, int $stock]. Complements the
     * existing back-in-stock notifier for other side effects (purchasing, feeds).
     */
    public const INVENTORY_RESTOCKED = 'inventory.restocked';

    /**
     * ACTION — a search was performed.
     * Args: [string $term, int $resultCount]. Use for "no results" reporting and
     * search analytics. Fires for the keyword search (not autocomplete suggest).
     */
    public const SEARCH_PERFORMED = 'search.performed';
}
