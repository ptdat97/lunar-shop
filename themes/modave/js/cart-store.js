import { reactive } from 'vue';

/*
| Shared mini-cart state. Server-side (Lunar) is source of truth; this mirrors
| the latest /api/v1/cart response so the drawer + header count stay in sync.
*/
export const cart = reactive({
    id: null,
    linesCount: 0,
    lines: [],
    totals: {},
    loading: false,
});

function apply(data) {
    cart.id = data.id;
    cart.linesCount = data.lines_count ?? 0;
    cart.lines = data.lines ?? [];
    cart.totals = data.totals ?? {};
}

export async function fetchCart() {
    cart.loading = true;
    try {
        const { data } = await window.axios.get('/api/v1/cart');
        apply(data.data);
    } catch (e) {
        /* cart may not exist yet */
    } finally {
        cart.loading = false;
    }
}

export async function updateLine(lineId, quantity) {
    const { data } = await window.axios.patch(`/api/v1/cart/lines/${lineId}`, { quantity });
    apply(data.data);
}

export async function removeLine(lineId) {
    const { data } = await window.axios.delete(`/api/v1/cart/lines/${lineId}`);
    apply(data.data);
}

// Keep the store fresh whenever any island adds to the cart.
window.addEventListener('cart:updated', fetchCart);
