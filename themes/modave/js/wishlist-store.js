import { reactive } from 'vue';

/*
| Wishlist state (DB-backed, requires login). Holds the set of product ids the
| user has wishlisted so heart buttons across the page reflect membership.
*/
export const wishlist = reactive({
    ids: new Set(),
    count: 0,
    loaded: false,
    authed: true, // flips to false on 401
});

export async function loadWishlist() {
    try {
        const { data } = await window.axios.get('/api/v1/wishlist');
        wishlist.ids = new Set(data.product_ids ?? []);
        wishlist.count = wishlist.ids.size;
        wishlist.authed = true;
    } catch (e) {
        if (e?.response?.status === 401) wishlist.authed = false;
    } finally {
        wishlist.loaded = true;
    }
}

export async function toggleWishlist(productId) {
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
            // Send the guest to login.
            window.location.href = '/lunar/login';
        }
        return wishlist.ids.has(productId);
    }
}

export function has(productId) {
    return wishlist.ids.has(productId);
}
