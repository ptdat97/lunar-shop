/*
| Add-to-cart enhancement (vanilla, delegated).
| Any [data-add-to-cart][data-variant] button (static or dynamically rendered
| in a re-rendered grid) posts to /api/v1/cart and dispatches `cart:updated`,
| which the Vue cart store (drawer + count) listens for to refresh.
*/

export default function addToCart() {
    if (document.body.dataset.atcBound) return;
    document.body.dataset.atcBound = '1';

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-add-to-cart]');
        if (!btn) return;
        e.preventDefault();

        const variant = Number(btn.dataset.variant);
        if (!variant || btn.disabled) return;

        const original = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Adding…';

        try {
            await window.axios.post('/api/v1/cart', { variant_id: variant, quantity: 1 });
            window.dispatchEvent(new CustomEvent('cart:updated'));
            btn.textContent = 'Added ✓';
            setTimeout(() => { btn.textContent = original; btn.disabled = false; }, 1500);
        } catch (err) {
            btn.textContent = original;
            btn.disabled = false;
        }
    });
}
