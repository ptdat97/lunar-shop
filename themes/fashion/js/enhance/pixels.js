// Marketing pixels enhancer (Google Analytics / Facebook Pixel).
// Tracks standard ecommerce events: view_item, add_to_cart, purchase.
// Reads pixel IDs from data attributes injected by the pixels partial.

(function () {
    'use strict';

    const root = document;
    const pixels = root.querySelector('script[data-pixel-google]');
    const googleId = pixels ? pixels.getAttribute('data-pixel-google') : null;
    const fbq = root.querySelector('script[data-pixel-facebook]');
    const facebookId = fbq ? fbq.getAttribute('data-pixel-facebook') : null;

    if (!googleId && !facebookId) {
        return;
    }

    function track(eventName, params = {}) {
        if (googleId && typeof window.gtag === 'function') {
            window.gtag('event', eventName, params);
        }
        if (facebookId && typeof window.fbq === 'function') {
            window.fbq('track', eventName, params);
        }
    }

    // ViewItem: fired on product page when the product detail SSR loads.
    // The product page includes [data-product-detail] with a JSON state payload.
    const productDetail = root.querySelector('[data-product-detail]');
    if (productDetail) {
        const stateEl = productDetail.querySelector('[data-product-state]');
        if (stateEl && stateEl.textContent.trim()) {
            try {
                const state = JSON.parse(stateEl.textContent);
                const product = state.data || state;
                track('view_item', {
                    value: product.price ?? product.displayPrice ?? 0,
                    currency: product.currency ?? 'USD',
                    items: [
                        {
                            item_id: product.id ?? product.sku,
                            item_name: product.name ?? product.translated?.name,
                            item_category: product.brand ?? product.category,
                            price: product.price ?? product.displayPrice ?? 0,
                            quantity: 1,
                        },
                    ],
                });
            } catch (e) {
                // Malformed JSON — fail silently; pixels are non-critical.
            }
        }
    }

    // AddToCart: delegated on the document so it catches both the product
    // page form submit and the mini-cart drawer add-to-cart buttons.
    root.addEventListener('submit', function (event) {
        const form = event.target.closest('[data-add-to-cart]');
        if (!form) {
            return;
        }

        const variantInput = form.querySelector('[data-variant-input]');
        const variantId = variantInput ? variantInput.value : null;
        const productCard = form.closest('[data-product-detail]') || form.closest('.product-card');
        if (!productCard) {
            return;
        }

        const nameEl = productCard.querySelector('.product-card__title a, h1');
        const priceEl = productCard.querySelector('[data-product-price], .product-card__price');
        const name = nameEl ? nameEl.textContent.trim() : null;
        const priceText = priceEl ? priceEl.textContent.trim() : '0';
        const price = parseFloat(priceText.replace(/[^0-9.]/g, '')) || 0;

        track('add_to_cart', {
            value: price,
            currency: 'USD',
            items: [
                {
                    item_id: variantId,
                    item_name: name,
                    price: price,
                    quantity: 1,
                },
            ],
        });
    }, true);

    // Purchase: fired on the order confirmation page.
    // The confirmation page includes [data-order-confirmation] with JSON.
    const confirmation = root.querySelector('[data-order-confirmation]');
    if (confirmation) {
        const stateEl = confirmation.querySelector('[data-order-state]');
        if (stateEl && stateEl.textContent.trim()) {
            try {
                const state = JSON.parse(stateEl.textContent);
                const order = state.data || state;
                const total = order.total ?? order.grand_total ?? 0;
                const items = (order.lines ?? order.items ?? []).map((line) => ({
                    item_id: line.product_id ?? line.sku ?? line.id,
                    item_name: line.product_name ?? line.name ?? line.description,
                    price: line.price ?? line.unit_price ?? 0,
                    quantity: line.quantity ?? 1,
                }));

                track('purchase', {
                    value: total,
                    currency: order.currency ?? 'USD',
                    transaction_id: order.reference ?? order.id,
                    items: items,
                });
            } catch (e) {
                // Malformed JSON — fail silently.
            }
        }
    }
})();