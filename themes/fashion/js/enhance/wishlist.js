// Wishlist: toggle membership (heart on cards/product, remove on the wishlist
// page) and reflect the count. Vanilla; requires auth — a 401 sends the user
// to login. Membership is loaded once per page to mark hearts active.

import api from '../api.js';

const LOGIN_URL = '/login';
let membership = null; // Set<productId> once loaded

function markActive(root = document) {
    if (!membership) return;
    root.querySelectorAll('[data-wishlist-toggle]').forEach((btn) => {
        const id = Number(btn.dataset.productId);
        btn.classList.toggle('active', membership.has(id));
        btn.setAttribute('aria-pressed', membership.has(id) ? 'true' : 'false');
    });
}

function setCount(n) {
    document.querySelectorAll('[data-wishlist-count]').forEach((el) => {
        el.textContent = n;
        el.hidden = n <= 0;
    });
}

async function loadMembership() {
    try {
        // Public endpoint: guests get an empty list (200), not a 401.
        const { data } = await api.get('/wishlist');
        membership = new Set((data.product_ids ?? []).map(Number));
        setCount(membership.size);
        markActive(document);
    } catch {
        // Network/other failure → leave hearts inactive, no count.
        membership = new Set();
    }
}

async function toggle(productId, btn) {
    try {
        const { data } = await api.post('/wishlist', { product_id: Number(productId) });
        const inWishlist = data.data.in_wishlist;
        if (membership) inWishlist ? membership.add(Number(productId)) : membership.delete(Number(productId));
        setCount(data.data.count);

        // On the wishlist page, removing drops the card.
        const item = btn.closest('[data-wishlist-item]');
        if (item && !inWishlist) {
            item.remove();
            const grid = document.querySelector('[data-wishlist-page] [data-grid]');
            if (grid && !grid.children.length) window.location.reload();
        } else {
            btn.classList.toggle('active', inWishlist);
            btn.setAttribute('aria-pressed', inWishlist ? 'true' : 'false');
        }
    } catch (e) {
        if (e.response?.status === 401) window.location.href = LOGIN_URL;
    }
}

export default function (root = document) {
    if (!window.__wishlistBound) {
        window.__wishlistBound = true;
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-wishlist-toggle]');
            if (!btn) return;
            e.preventDefault();
            toggle(btn.dataset.productId, btn);
        });
        // Re-decorate hearts after the catalog grid re-renders (filter/sort/page).
        window.addEventListener('grid:rendered', (e) => markActive(e.detail?.root || document));
        loadMembership();
    } else {
        markActive(root);
    }
}
