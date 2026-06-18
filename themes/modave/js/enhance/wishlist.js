import { isAuthed } from './_util.js';

/*
| Wishlist enhancement (vanilla).
| - [data-wishlist-toggle="<productId>"]  → heart buttons anywhere on the page
| - [data-wishlist-count]                  → header badge
| - [data-wishlist-page]                   → wishlist page grid (remove + empty state)
|
| State is DB-backed (requires login). Membership is loaded once and shared so
| every heart on the page reflects it.
*/

const state = {
    ids: new Set(),
    loaded: false,
    authed: isAuthed(),
};

let loadPromise = null;

function loadWishlist() {
    if (!state.authed) { state.loaded = true; return Promise.resolve(); }
    if (state.loaded) return Promise.resolve();
    if (loadPromise) return loadPromise;

    loadPromise = window.axios.get('/api/v1/wishlist')
        .then(({ data }) => { state.ids = new Set(data.product_ids ?? []); })
        .catch((e) => { if (e?.response?.status === 401) state.authed = false; })
        .finally(() => { state.loaded = true; loadPromise = null; });

    return loadPromise;
}

function renderButtons(root = document) {
    root.querySelectorAll('[data-wishlist-toggle]').forEach((btn) => {
        const pid = Number(btn.dataset.wishlistToggle);
        btn.classList.toggle('active', state.ids.has(pid));
    });
}

function renderCount(root = document) {
    root.querySelectorAll('[data-wishlist-count]').forEach((el) => {
        el.textContent = state.ids.size;
        el.style.display = state.ids.size > 0 ? '' : 'none';
    });
}

async function toggle(pid) {
    if (!state.authed) { window.location.href = '/login'; return; }
    try {
        const { data } = await window.axios.post('/api/v1/wishlist', { product_id: pid });
        if (data.data.in_wishlist) state.ids.add(pid); else state.ids.delete(pid);
        renderButtons();
        renderCount();
    } catch (e) {
        if (e?.response?.status === 401) { state.authed = false; window.location.href = '/login'; }
    }
}

export default function wishlist(root = document) {
    const buttons = root.querySelectorAll('[data-wishlist-toggle]');
    const counts = root.querySelectorAll('[data-wishlist-count]');
    const page = root.querySelector('[data-wishlist-page]');

    if (!buttons.length && !counts.length && !page) return;

    // Delegate clicks once (idempotent across re-runs via a flag on <body>).
    if (!document.body.dataset.wishlistBound) {
        document.body.dataset.wishlistBound = '1';
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-wishlist-toggle]');
            if (!btn) return;
            e.preventDefault();
            toggle(Number(btn.dataset.wishlistToggle));
        });
    }

    loadWishlist().then(() => {
        renderButtons(root);
        renderCount(root);
    });
}
