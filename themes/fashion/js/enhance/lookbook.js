// Shoppable lookbook: "shop the set" button adds every product in the lookbook
// to the cart in one click. Hotspot pins themselves reuse the delegated
// add-to-cart.js (button[data-add-to-cart]) — nothing extra needed for those.

import api from '../api.js';
import { CART_UPDATED, emit } from '../events.js';

function openDrawer() {
    const el = document.getElementById('shoppingCart');
    if (el && window.bootstrap?.Offcanvas) {
        window.bootstrap.Offcanvas.getOrCreateInstance(el).show();
    }
}

export default function (root = document) {
    const btn = root.querySelector('[data-lookbook-add-set]');
    if (!btn || btn.dataset.setInit) return;
    btn.dataset.setInit = '1';

    const status = root.querySelector('[data-lookbook-set-status]');
    const ids = (btn.dataset.variantIds || '')
        .split(',')
        .map((s) => parseInt(s, 10))
        .filter(Boolean);

    btn.addEventListener('click', async () => {
        if (!ids.length) return;
        const label = btn.innerHTML;
        btn.disabled = true;

        // Add sequentially so the server-side cart merges quantities cleanly.
        // Each POST returns the updated cart; the last one is the final state,
        // passed along so the mini-cart renders without re-fetching /cart.
        let added = 0;
        let cart = null;
        for (const variant_id of ids) {
            try {
                const { data } = await api.post('/cart', { variant_id, quantity: 1 });
                cart = data.data ?? data;
                added += 1;
            } catch {
                /* skip a failed line (e.g. out of stock) and continue */
            }
        }

        emit(CART_UPDATED, cart ? { cart } : {});
        btn.disabled = false;
        btn.innerHTML = label;

        if (status) {
            status.textContent = added === ids.length
                ? (status.dataset.allMsg || '')
                : `${added}/${ids.length}`;
            status.className = `small ms-2 ${added ? 'text-success' : 'text-danger'}`;
        }
        if (added) openDrawer();
    });
}
