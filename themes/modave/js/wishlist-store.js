import { reactive } from 'vue';

/*
| Wishlist state (DB-backed, requires login). Holds the set of product ids the
| user has wishlisted so heart buttons across the page reflect membership.
*/
function isAuthed() {
    return document.querySelector('meta[name="auth-check"]')?.getAttribute('content') === '1';
}

export const wishlist = reactive({
    ids: new Set(),
    count: 0,
    loaded: false,
    authed: isAuthed(),
});

// Single in-flight request shared by every wishlist button on the page.
let loadPromise = null;

export function loadWishlist() {
    // Guests have no wishlist — don't hit the API (avoids repeated 401s).
    if (!wishlist.authed) {
        wishlist.loaded = true;
        return Promise.resolve();
    }
    if (wishlist.loaded) return Promise.resolve();
    if (loadPromise) return loadPromise;

    loadPromise = window.axios.get('/api/v1/wishlist')
        .then(({ data }) => {
            wishlist.ids = new Set(data.product_ids ?? []);
            wishlist.count = wishlist.ids.size;
        })
        .catch((e) => {
            if (e?.response?.status === 401) wishlist.authed = false;
        })
        .finally(() => {
            wishlist.loaded = true;
            loadPromise = null;
        });

    return loadPromise;
}

export async function toggleWishlist(productId) {
    // Guests must log in first.
    if (!wishlist.authed) {
        window.location.href = '/login';
        return false;
    }

    try {
        const { data } = await window.axios.post('/api/v1/wishlist', { product_id: productId });
        if (data.data.in_wishlist) {
            wishlist.ids.add(productId);
        } else {
            wishlist.ids.delete(productId);
        }
        wishlist.count = data.data.count;
        return data.data.in_wishlist;
    } catch (e) {
        if (e?.response?.status === 401) {
            wishlist.authed = false;
            window.location.href = '/login';
        }
        return wishlist.ids.has(productId);
    }
}

export function has(productId) {
    return wishlist.ids.has(productId);
}
