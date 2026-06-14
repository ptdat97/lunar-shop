import { reactive } from 'vue';

/*
| Shared cart state across islands. Server-side is source of truth (Lunar);
| this just mirrors the latest /api/v1/cart response so the drawer + buttons
| stay in sync without a full SPA.
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
        // Cart may not exist yet; leave defaults.
    } finally {
        cart.loading = false;
    }
}

export async function addToCart(variantId, quantity = 1) {
    const { data } = await window.axios.post('/api/v1/cart', {
        variant_id: variantId,
        quantity,
    });
    apply(data.data);
}

export async function updateLine(lineId, quantity) {
    const { data } = await window.axios.patch(`/api/v1/cart/lines/${lineId}`, { quantity });
    apply(data.data);
}

export async function removeLine(lineId) {
    const { data } = await window.axios.delete(`/api/v1/cart/lines/${lineId}`);
    apply(data.data);
}
