<?php

namespace Modules\Theme\Support;

/**
 * The strings the storefront's JavaScript renders, in the visitor's language.
 *
 * The theme's JS had ~45 English strings hardcoded, and they landed at the worst
 * possible moments for a Vietnamese shop: the free-shipping nudge in the cart
 * ("Add 250.000 ₫ more for free shipping."), the coupon result at checkout, the
 * membership progress line. Nothing was wrong with the translations — they
 * mostly existed already in `lang/{locale}/storefront.php`. The JS simply had no
 * way to reach them, because the storefront had no JS i18n mechanism at all.
 *
 * This generalises the one pattern that already worked: the product page emitted
 * `<script type="application/json" data-product-i18n>` and `product-variant.js`
 * read it. Same idea, one payload, every page.
 *
 * Keys are FLAT and namespaced by area (`cart.free_shipping_remaining`), not
 * nested, so the JS side needs no traversal helper — `t('cart.x')` is one lookup.
 *
 * A key here is a CONTRACT with a JS file. StorefrontI18nTest asserts every key
 * resolves to a real translation, because a typo produces the key itself on
 * screen and nothing errors.
 */
class StorefrontI18n
{
    /**
     * @return array<string, string>
     */
    public static function payload(): array
    {
        return [
            // enhance/cart.js — mini-cart + free-shipping nudge
            'cart.decrease' => __('storefront.cart.decrease'),
            'cart.quantity' => __('storefront.cart.quantity'),
            'cart.increase' => __('storefront.cart.increase'),
            'cart.free_shipping_unlocked' => __('storefront.cart.free_shipping_unlocked'),
            // `:amount` stays a Laravel placeholder here and is substituted by
            // the JS helper, not by Laravel — the value is only known client-side.
            'cart.free_shipping_remaining' => __('storefront.cart.free_shipping_remaining'),
            'cart.you_may_also_like' => __('storefront.cart.you_may_also_like'),

            // enhance/cart-page.js + checkout-coupon.js
            'coupon.applied' => __('storefront.cart.coupon_applied'),
            'coupon.invalid' => __('storefront.cart.coupon_invalid'),
            'coupon.removed' => __('storefront.cart.coupon_removed'),
            'coupon.remove_failed' => __('storefront.cart.coupon_remove_failed'),

            // enhance/product-variant.js — trang sản phẩm vốn đã có khối
            // `data-product-i18n` riêng và vẫn giữ; gộp vào đây để có MỘT cơ chế
            // thay vì hai, khối riêng vẫn ghi đè được.
            'product.add_to_cart' => __('storefront.product.add_to_cart'),
            'product.out_of_stock' => __('storefront.product.out_of_stock'),
            'product.select_options' => __('storefront.product.select_options'),
            'product.in_stock' => __('storefront.product.in_stock', ['count' => '%d']),

            // enhance/_card.js, _gallery.js
            'product.add_to_wishlist' => __('storefront.product.add_to_wishlist'),
            'gallery.previous' => __('storefront.common.previous'),

            // enhance/add-to-cart.js, auth.js
            'common.try_again' => __('storefront.common.try_again'),
            'common.please_wait' => __('storefront.common.please_wait'),
            'common.error_generic' => __('storefront.errors.generic'),

            // enhance/membership.js
            'membership.discount_every_order' => __('storefront.account.membership_discount_every_order'),
            'membership.not_a_member' => __('storefront.account.membership_not_a_member'),
            'membership.spend_to_reach' => __('storefront.account.membership_spend_to_reach'),

            // enhance/notify-me.js
            'notify.subscribed' => __('storefront.product.notify_subscribed'),
            'notify.failed' => __('storefront.product.notify_failed'),

            // enhance/size-finder.js
            'size.use_this_size' => __('storefront.product.size_use_this'),
            'size.no_match' => __('storefront.product.size_no_match'),
            'size.also_consider' => __('storefront.product.size_also_consider'),
            'size.need_measurement' => __('storefront.product.size_need_measurement'),
            'size.finding' => __('storefront.product.size_finding'),
            'size.failed' => __('storefront.product.size_failed'),

        ];
    }
}
