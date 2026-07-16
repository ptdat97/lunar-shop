// Add-to-cart, delegated across the whole document so it works for the product
// page form and any per-card button (now or rendered later by _card.js).
//
// Markup contract:
//   <form data-add-to-cart> … <input name="variant_id"> [<input name="quantity">]
// or a button:
//   <button data-add-to-cart data-variant-id="123" [data-quantity="1"]>
//
// On success it emits cart:updated (enhance/cart.js refreshes) and opens the
// mini-cart drawer. The form keeps a real action as a no-JS fallback target.

import api from '../api.js';
import { CART_UPDATED, emit } from '../events.js';

function openDrawer() {
    const el = document.getElementById('shoppingCart');
    if (el && window.bootstrap?.Offcanvas) {
        window.bootstrap.Offcanvas.getOrCreateInstance(el).show();
    }
}

async function add(variantId, quantity, trigger) {
    if (!variantId) return;
    const btn = trigger?.matches('button') ? trigger : trigger?.querySelector('[type="submit"]');
    const label = btn?.textContent;
    if (btn) { btn.disabled = true; btn.textContent = 'Adding…'; }

    try {
        const { data } = await api.post('/cart', { variant_id: Number(variantId), quantity: Number(quantity) || 1 });
        // The POST response IS the updated cart — pass it along so the
        // mini-cart renders it directly instead of re-fetching /cart.
        emit(CART_UPDATED, { variantId, cart: data.data ?? data });
        openDrawer();
    } catch (e) {
        if (btn) btn.textContent = 'Try again';
        return;
    } finally {
        if (btn && btn.textContent === 'Adding…') btn.textContent = label;
        if (btn) setTimeout(() => { btn.disabled = false; if (label) btn.textContent = label; }, 600);
    }
}

export default function (root = document) {
    if (window.__addToCartBound) return;
    window.__addToCartBound = true;

    // Form submit
    document.addEventListener('submit', (e) => {
        const form = e.target.closest('form[data-add-to-cart]');
        if (!form) return;
        e.preventDefault();
        const variantId = form.querySelector('[name="variant_id"]')?.value;
        const quantity = form.querySelector('[name="quantity"]')?.value || 1;
        add(variantId, quantity, form);
    });

    // Button click (per-card quick add)
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-add-to-cart]');
        if (!btn) return;
        e.preventDefault();
        add(btn.dataset.variantId, btn.dataset.quantity || 1, btn);
    });
}
