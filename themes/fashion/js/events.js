// Tiny DOM-event bus for cross-island sync. Kept dependency-free so vanilla
// enhancers (Bước 3) and Vue islands (Bước 4) can share it.
//
//   cart:updated   — someone mutated the cart (add/remove/qty). Pass the
//                    mutation response's cart as detail.cart when you have it
//                    (every cart endpoint returns it) so the mini-cart renders
//                    it directly; without it the enhancer re-fetches
//                    /api/v1/cart. Either way it then emits…
//   cart:refreshed — …so the cart page / count re-render. A refresh must NOT
//                    re-emit cart:updated (avoids an infinite loop).

export const CART_UPDATED = 'cart:updated';
export const CART_REFRESHED = 'cart:refreshed';

export function emit(name, detail = {}) {
    window.dispatchEvent(new CustomEvent(name, { detail }));
}

export function on(name, handler) {
    window.addEventListener(name, handler);
    return () => window.removeEventListener(name, handler);
}
