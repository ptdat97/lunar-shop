/**
 * Translated strings for the theme's JavaScript.
 *
 * The enhancers used to hardcode English — ~45 strings, and they surfaced at
 * the worst moments for a Vietnamese shop: the free-shipping nudge in the cart,
 * the coupon result at checkout, the membership progress line. The translations
 * mostly existed already in lang/{locale}/storefront.php; the JS just had no way
 * to reach them.
 *
 * The payload is embedded by partials/i18n.blade.php as an
 * `application/json` block. Read once and cached: the block never changes
 * within a page load, and every enhancer would otherwise re-parse it.
 */

let cache = null;

function load() {
    if (cache) return cache;

    const tag = document.querySelector('[data-storefront-i18n]');

    if (!tag) {
        // A page without the block still has to work — better an English label
        // than a blank button. Kept empty here on purpose: each call site
        // passes its own fallback, so there is one place per string, not two.
        cache = {};
        return cache;
    }

    try {
        cache = JSON.parse(tag.textContent) || {};
    } catch {
        cache = {};
    }

    return cache;
}

/**
 * Look up `key`, substituting `:name` placeholders from `replacements`.
 *
 * `fallback` is what shows if the key is missing — a missing key must never
 * render as the raw `cart.increase` to a customer.
 *
 * @param {string} key
 * @param {Object<string, string|number>} [replacements]
 * @param {string} [fallback]
 * @returns {string}
 */
export function t(key, replacements = {}, fallback = '') {
    const value = load()[key] ?? fallback ?? key;

    return Object.entries(replacements).reduce(
        (text, [name, replacement]) => text.replaceAll(`:${name}`, String(replacement)),
        String(value),
    );
}

/** Reset the cache. Only for tests — a page load never needs it. */
export function resetI18nCache() {
    cache = null;
}
